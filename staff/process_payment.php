
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/activity_logger.php';


function stopPayment($message, $code = 400) {
    http_response_code($code);
    exit(htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    ));
}

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stopPayment('Invalid request.', 405);
}

// Only Staff and Admin can process payments.
if (
    !isset($_SESSION['user_id']) ||
    !in_array(
        $_SESSION['role'] ?? '',
        ['staff', 'admin'],
        true
    )
) {
    stopPayment('Access denied.', 403);
}

// Validate CSRF token.
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    stopPayment('Invalid form request.', 403);
}

$staffId = (int) $_SESSION['user_id'];
$paymentId = filter_var(
    $_POST['payment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$action = $_POST['action'] ?? '';

if (!$paymentId || !in_array(
    $action,
    ['approve', 'reject'],
    true
)) {
    stopPayment('Invalid payment action.');
}

// Confirm Staff/Admin account is verified and active.
$auth = $conn->prepare(
    "SELECT id FROM users
     WHERE id = ?
       AND role IN ('staff', 'admin')
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);

$auth->bind_param('i', $staffId);
$auth->execute();

if (!$auth->get_result()->fetch_assoc()) {
    stopPayment('Staff account unavailable.', 403);
}
$transactionStarted = false;
try {
    $conn->begin_transaction();
    $transactionStarted = true;

    // Lock payment and associated membership.
    $stmt = $conn->prepare(
        "SELECT
            p.id,
            p.membership_id,
            p.payment_status,
            p.amount,
            mm.payment_status AS member_payment_status,
            mm.membership_status,
            mp.price,
            mp.duration_days
         FROM payments p
         JOIN member_memberships mm
           ON p.membership_id = mm.id
         JOIN membership_plans mp
           ON mm.plan_id = mp.id
         WHERE p.id = ?
         FOR UPDATE"
    );

    $stmt->bind_param('i', $paymentId);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        throw new RuntimeException('Payment not found.');
    }

    if ($payment['payment_status'] !== 'pending') {
        throw new RuntimeException(
            'This payment has already been processed.'
        );
    }

    if (
        $payment['member_payment_status'] !== 'pending' ||
        $payment['membership_status'] !== 'pending'
    ) {
        throw new RuntimeException(
            'Membership is not pending.'
        );
    }

    $membershipId = (int) $payment['membership_id'];

    if ($action === 'approve') {

        // Confirm the recorded payment matches the plan price.
        if (
            bccomp(
                (string) $payment['amount'],
                (string) $payment['price'],
                2
            ) !== 0
        ) {
            throw new RuntimeException(
                'Payment amount does not match the plan price.'
            );
        }

        $days = (int) $payment['duration_days'];

        if ($days <= 0) {
            throw new RuntimeException(
                'Invalid membership duration.'
            );
        }

        // Record confirmed payment.
        $updatePayment = $conn->prepare(
            "UPDATE payments
             SET payment_status = 'paid',
                 verified_by = ?,
                 verified_at = NOW()
             WHERE id = ?
               AND payment_status = 'pending'"
        );

        $updatePayment->bind_param(
            'ii',
            $staffId,
            $paymentId
        );

        $updatePayment->execute();

        if ($updatePayment->affected_rows !== 1) {
            throw new RuntimeException(
                'Payment could not be approved.'
            );
        }

        // Activate membership and calculate expiration.
        // The end date is exclusive: a 1-day membership
        // beginning today expires at tomorrow's date.
        $updateMembership = $conn->prepare(
            "UPDATE member_memberships
             SET payment_status = 'paid',
                 membership_status = 'active',
                 start_date = CURDATE(),
                 end_date = DATE_ADD(
                     CURDATE(),
                     INTERVAL ? DAY
                 )
             WHERE id = ?
               AND payment_status = 'pending'
               AND membership_status = 'pending'"
        );

        $updateMembership->bind_param(
            'ii',
            $days,
            $membershipId
        );

        $updateMembership->execute();

        if ($updateMembership->affected_rows !== 1) {
            throw new RuntimeException(
                'Membership could not be activated.'
            );
        }

    } else {

        // Reject payment, leaving membership pending.
        $reject = $conn->prepare(
            "UPDATE payments
             SET payment_status = 'rejected',
                 verified_by = ?,
                 verified_at = NOW()
             WHERE id = ?
               AND payment_status = 'pending'"
        );

        $reject->bind_param(
            'ii',
            $staffId,
            $paymentId
        );

        $reject->execute();

        if ($reject->affected_rows !== 1) {
            throw new RuntimeException(
                'Payment could not be rejected.'
            );
        }
    }

$conn->commit();

$transactionStarted = false;
// Record successful payment action
if ($action === 'approve') {
    $activityMessage = "Approved Payment ID {$paymentId}";
} else {
    $activityMessage = "Rejected Payment ID {$paymentId}";
}

logActivity(
    $conn,
    $staffId,
    $activityMessage
);



    header('Location: payments.php');
    exit;

} catch (Throwable $e) {

    if ($transactionStarted){
    $conn->rollback();
    }

    if ($e instanceof RuntimeException) {
        stopPayment($e->getMessage());
    }

    error_log(
        'Payment processing error: ' . $e->getMessage()
    );

    stopPayment(
        'Unable to process payment. Please try again.',
        500
    );
}
