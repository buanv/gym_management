
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// Members only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'member'
) {
    header('Location: login.php');
    exit;
}

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: weight_tracker.php');
    exit;
}

// Verify CSRF token
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

$weightInput = trim(
    (string) ($_POST['weight_kg'] ?? '')
);

// Validate the record ID
if ($recordId === false || $recordId === null) {
    $_SESSION['weight_message'] = 'Invalid record ID.';
    header('Location: weight_tracker.php');
    exit;
}

// Validate the new weight
if (
    !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $weightInput) ||
    (float) $weightInput < 1 ||
    (float) $weightInput > 500
) {
    $_SESSION['weight_message'] =
        'Enter a valid weight between 1 and 500 kg.';

    header('Location: weight_tracker.php');
    exit;
}

try {
    $weight = (float) $weightInput;

    // Update only the logged-in Member's record
    $stmt = $conn->prepare(
        "UPDATE weight_records
         SET weight_kg = ?
         WHERE id = ?
           AND member_id = ?"
    );

    $stmt->bind_param(
        'dii',
        $weight,
        $recordId,
        $memberId
    );

    $stmt->execute();

    $_SESSION['weight_message'] =
        'Weight record updated successfully!';

    $stmt->close();

} catch (Throwable $e) {
    error_log('Weight update error: ' . $e->getMessage());

    $_SESSION['weight_message'] =
        'Unable to update weight record.';
}

header('Location: weight_tracker.php');
exit;
