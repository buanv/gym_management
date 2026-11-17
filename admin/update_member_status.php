
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity_logger.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: members.php');
    exit;
}

if (
    !isset($_SESSION['admin_member_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['admin_member_csrf'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Invalid request token.');
}

$memberId = filter_var(
    $_POST['member_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$newStatus = $_POST['new_status'] ?? '';

if (
    !$memberId ||
    !in_array($newStatus, ['active', 'inactive'], true)
) {
    $_SESSION['admin_member_message'] =
        'Invalid account information.';

    header('Location: members.php');
    exit;
}

try {
    $stmt = $conn->prepare(
        "UPDATE users
         SET status = ?
         WHERE id = ?
           AND role = 'member'"
    );

    $stmt->bind_param('si', $newStatus, $memberId);
    $stmt->execute();

    $updated = $stmt->affected_rows;
    $stmt->close();


    if ($updated > 0) {
        logActivity(
            $conn,
            (int)$_SESSION['user_id'],
            "Changed Member ID {$memberId} status to {$newStatus}"
        );
    }


    $_SESSION['admin_member_message'] =
        $updated > 0
            ? 'Member account status updated successfully!'
            : 'No account status was changed.';

} catch (Throwable $e) {
    error_log('Member status update: ' . $e->getMessage());

    $_SESSION['admin_member_message'] =
        'Unable to update Member status.';
}

header('Location: members.php');
exit;
