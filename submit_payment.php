
<?php
session_start();
require_once __DIR__ . '/config/db.php';

function failPayment($message, $status = 400) {
    http_response_code($status);
    exit(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

// Only POST requests are accepted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failPayment('Invalid request.', 405);
}

// Member login required.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'member'
) {
    failPayment('Access denied. Members only.', 403);
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
    failPayment('Invalid form request.', 403);
}

$user_id = (int) $_SESSION['user_id'];
$method = $_POST['payment_method'] ?? '';

if (!in_array($method, ['cash', 'gcash'], true)) {
    failPayment('Invalid payment method.');
}

// Confirm the Member is verified and active.
$userStmt = $conn->prepare(
    "SELECT id FROM users
     WHERE id = ?
       AND role = 'member'
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);
$userStmt->bind_param('i', $user_id);
$userStmt->execute();

if (!$userStmt->get_result()->fetch_assoc()) {
    failPayment('Member account is unavailable or unverified.', 403);
}

$reference = null;
$upload = null;
$extension = null;

// Validate GCash proof if GCash is selected.
if ($method === 'gcash') {
    $reference = trim($_POST['reference_number'] ?? '');

    if ($reference === '' || strlen($reference) > 100) {
        failPayment('Enter a valid GCash reference number.');
    }

    if (
        !isset($_FILES['proof']) ||
        $_FILES['proof']['error'] !== UPLOAD_ERR_OK
    ) {
        failPayment('Please upload your payment screenshot.');
    }

    $upload = $_FILES['proof'];

    if (
        $upload['size'] <= 0 ||
        $upload['size'] > 2 * 1024 * 1024
    ) {
        failPayment('Screenshot must be 2 MB or smaller.');
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        failPayment('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($upload['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowed[$mime])) {
        failPayment('Only JPG and PNG images are allowed.');
    }

    $extension = $allowed[$mime];
}

$savedFile = null;
$absolutePath = null;

try {
    $conn->begin_transaction();

    // Lock this Member's membership while checking payments.
    $stmt = $conn->prepare(
        "SELECT
            mm.id,
            mm.payment_status,
            mm.membership_status,
            mp.price
         FROM member_memberships mm
         JOIN membership_plans mp
           ON mm.plan_id = mp.id
         WHERE mm.user_id = ?
         ORDER BY mm.created_at DESC, mm.id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $membership = $stmt->get_result()->fetch_assoc();

    if (!$membership) {
        throw new RuntimeException(
            'No membership found. Please select a plan first.'
        );
    }

    if (
        $membership['payment_status'] === 'paid' ||
        $membership['membership_status'] !== 'pending'
    ) {
        throw new RuntimeException(
            'This membership is not eligible for payment.'
        );
    }

    $membershipId = (int) $membership['id'];

    // Avoid duplicate pending or paid payment records.
    $check = $conn->prepare(
        "SELECT id FROM payments
         WHERE membership_id = ?
           AND payment_status IN ('pending', 'paid')
         LIMIT 1"
    );

    $check->bind_param('i', $membershipId);
    $check->execute();

    if ($check->get_result()->fetch_assoc()) {
        throw new RuntimeException(
            'A pending or completed payment already exists.'
        );
    }

    // Save the GCash screenshot outside htdocs.
    if ($method === 'gcash') {
        $uploadDir = dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'gym_private_uploads';

        if (
            !is_dir($uploadDir) &&
            !mkdir($uploadDir, 0700, true)
        ) {
            throw new RuntimeException(
                'Unable to create the upload directory.'
            );
        }

        $savedFile = bin2hex(random_bytes(16))
            . '.' . $extension;

        $absolutePath = $uploadDir
            . DIRECTORY_SEPARATOR
            . $savedFile;

        if (!move_uploaded_file(
            $upload['tmp_name'],
            $absolutePath
        )) {
            throw new RuntimeException(
                'Unable to save the payment screenshot.'
            );
        }
    }

    // Amount comes from the database, not the Member's form.
    $amount = (string) $membership['price'];

    $insert = $conn->prepare(
        "INSERT INTO payments (
            membership_id,
            payment_method,
            amount,
            payment_status,
            reference_number,
            proof_of_payment
        ) VALUES (?, ?, ?, 'pending', ?, ?)"
    );

    $insert->bind_param(
        'issss',
        $membershipId,
        $method,
        $amount,
        $reference,
        $savedFile
    );

    $insert->execute();

    $conn->commit();

    // Rotate the form token after successful submission.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: payment_submitted.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    // Remove the uploaded screenshot if saving fails.
    if (
        $absolutePath !== null &&
        is_file($absolutePath)
    ) {
        unlink($absolutePath);
    }

    if ($e instanceof RuntimeException) {
        failPayment($e->getMessage());
    }

    error_log('Payment error: ' . $e->getMessage());

    failPayment(
        'Unable to submit payment. Please try again.',
        500
    );
}
