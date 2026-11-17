
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Admin access only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: plans.php');
    exit;
}

// Verify CSRF token
$token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['plan_csrf']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['plan_csrf'], $token)
) {
    http_response_code(403);
    exit('Invalid request token.');
}

// Get form information
$planId = filter_var(
    $_POST['plan_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$newStatus = $_POST['new_status'] ?? '';

// Validate input
if (
    !$planId ||
    !in_array($newStatus, ['active', 'inactive'], true)
) {
    $_SESSION['plan_message'] =
        'Invalid membership plan information.';

    header('Location: plans.php');
    exit;
}

try {

    // Update membership plan status
    $stmt = $conn->prepare(
        "UPDATE membership_plans
         SET status = ?
         WHERE id = ?
           AND status <> ?"
    );

    $stmt->bind_param(
        'sis',
        $newStatus,
        $planId,
        $newStatus
    );

    $stmt->execute();

    $updated = $stmt->affected_rows;

    $stmt->close();


    if ($updated > 0) {
        logActivity(
            $conn,
            (int)$_SESSION['user_id'],
            "Changed Membership Plan ID {$planId} status to {$newStatus}"
        );
    }


    if ($updated > 0) {

        $_SESSION['plan_message'] =
            $newStatus === 'active'
                ? 'Membership plan activated successfully!'
                : 'Membership plan deactivated successfully!';

    } else {

        $_SESSION['plan_message'] =
            'No membership plan status was changed.';
    }

} catch (Throwable $e) {

    error_log(
        'Update plan status error: ' . $e->getMessage()
    );

    $_SESSION['plan_message'] =
        'Unable to update membership plan status.';
}

header('Location: plans.php');
exit;
