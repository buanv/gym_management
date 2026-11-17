
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only Admin can change Staff status
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff.php');
    exit;
}

// Verify CSRF token
if (
    !isset($_SESSION['admin_staff_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['admin_staff_csrf'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Invalid request token.');
}

$staffId = filter_var(
    $_POST['staff_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$newStatus = $_POST['new_status'] ?? '';

if (
    !$staffId ||
    !in_array($newStatus, ['active', 'inactive'], true)
) {
    $_SESSION['admin_staff_message'] =
        'Invalid Staff account information.';

    header('Location: staff.php');
    exit;
}

try {
    // Update only Staff accounts
    $stmt = $conn->prepare(
        "UPDATE users
         SET status = ?
         WHERE id = ?
           AND role = 'staff'"
    );

    $stmt->bind_param(
        'si',
        $newStatus,
        $staffId
    );

    $stmt->execute();

    $updated = $stmt->affected_rows;

    $stmt->close();

    if ($updated > 0) {
        logActivity(
            $conn,
            (int)$_SESSION['user_id'],
            "Changed Staff ID {$staffId} status to {$newStatus}"
        );
    }


    $_SESSION['admin_staff_message'] =
        $updated > 0
            ? 'Staff account status updated successfully!'
            : 'No Staff account status was changed.';

} catch (Throwable $e) {
    error_log(
        'Staff status update error: ' . $e->getMessage()
    );

    $_SESSION['admin_staff_message'] =
        'Unable to update Staff account status.';
}

header('Location: staff.php');
exit;
