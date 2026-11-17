
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// Only logged-in Members can save weight.
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

// Verify CSRF token.
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
$weightInput = trim((string)($_POST['weight_kg'] ?? ''));

// Validate weight format and range.
if (
    !preg_match(
        '/^\d{1,3}(?:\.\d{1,2})?$/',
        $weightInput
    ) ||
    (float)$weightInput < 1 ||
    (float)$weightInput > 500
) {
    $_SESSION['weight_message'] =
        'Please enter a valid weight between 1 and 500 kg.';

    header('Location: weight_tracker.php');
    exit;
}

try {
    // Save the record for the logged-in Member.
    $stmt = $conn->prepare(
        "INSERT INTO weight_records
         (member_id, weight_kg, recorded_at)
         VALUES (?, ?, NOW())"
    );

    $weight = (float)$weightInput;

    $stmt->bind_param(
        'id',
        $memberId,
        $weight
    );

    $stmt->execute();
    $stmt->close();

    $_SESSION['weight_message'] =
        'Weight recorded successfully!';

} catch (Throwable $e) {

    error_log(
        'Weight saving error: ' . $e->getMessage()
    );

    $_SESSION['weight_message'] =
        'Unable to save weight. Please try again.';
}

header('Location: weight_tracker.php');
exit;
