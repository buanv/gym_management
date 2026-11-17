
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// Only Members can update their profiles.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'member'
) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: member_profile.php');
    exit;
}

// Verify CSRF token.
if (
    !isset($_SESSION['profile_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['profile_csrf'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Invalid request token.');
}

$memberId = (int) $_SESSION['user_id'];
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$password = $_POST['current_password'] ?? '';

if (
    $fullName === '' ||
    mb_strlen($fullName) > 100
) {
    $_SESSION['profile_message'] =
        'Full name must contain 1 to 100 characters.';

    header('Location: member_profile.php');
    exit;
}

if (!is_string($password) || $password === '') {
    $_SESSION['profile_message'] =
        'Please enter your current password.';

    header('Location: member_profile.php');
    exit;
}

try {
    // Verify the current password before changing the name.
    $stmt = $conn->prepare(
        "SELECT password_hash
         FROM users
         WHERE id = ?
           AND role = 'member'
           AND status = 'active'
         LIMIT 1"
    );

    $stmt->bind_param('i', $memberId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$user ||
        !password_verify($password, $user['password_hash'])
    ) {
        $_SESSION['profile_message'] =
            'Current password is incorrect.';
    } else {

        $updateStmt = $conn->prepare(
            "UPDATE users
             SET full_name = ?
             WHERE id = ?
               AND role = 'member'"
        );

        $updateStmt->bind_param(
            'si',
            $fullName,
            $memberId
        );

        $updateStmt->execute();
        $updateStmt->close();

        // Keep the dashboard session name updated.
        $_SESSION['full_name'] = $fullName;

        $_SESSION['profile_message'] =
            'Profile updated successfully!';
    }

} catch (Throwable $e) {

    error_log(
        'Profile update error: ' . $e->getMessage()
    );

    $_SESSION['profile_message'] =
        'Unable to update your profile. Please try again.';
}

header('Location: member_profile.php');
exit;
