
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// Allow logged-in Members only.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'member'
) {
    header('Location: login.php');
    exit;
}

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: weight_tracker.php');
    exit;
}

// Validate CSRF token.
if (
    !isset($_SESSION['weight_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['weight_csrf'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Invalid request token.');
}

$memberId = (int) $_SESSION['user_id'];

$recordId = filter_var(
    $_POST['record_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($recordId === false || $recordId === null) {
    $_SESSION['weight_message'] = 'Invalid weight record.';

    header('Location: weight_tracker.php');
    exit;
}

try {

    // Delete only records belonging to this Member.
    $stmt = $conn->prepare(
        "DELETE FROM weight_records
         WHERE id = ?
           AND member_id = ?"
    );

    $stmt->bind_param(
        'ii',
        $recordId,
        $memberId
    );

    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        $_SESSION['weight_message'] =
            'Weight record deleted successfully!';
    } else {
        $_SESSION['weight_message'] =
            'Weight record not found.';
    }

    $stmt->close();

} catch (Throwable $e) {

    error_log(
        'Weight deletion error: ' . $e->getMessage()
    );

    $_SESSION['weight_message'] =
        'Unable to delete weight record.';
}

header('Location: weight_tracker.php');
exit;
