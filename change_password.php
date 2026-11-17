
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

// Accept POST requests only.
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

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate password fields.
if (
    !is_string($currentPassword) ||
    !is_string($newPassword) ||
    !is_string($confirmPassword)
) {
    $_SESSION['profile_message'] =
        'Invalid password information.';

    header('Location: member_profile.php');
    exit;
}

if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {
    $_SESSION['profile_message'] =
        'Please complete all password fields.';

    header('Location: member_profile.php');
    exit;
}

// Require at least 8 characters.
if (strlen($newPassword) < 8) {
    $_SESSION['profile_message'] =
        'New password must contain at least 8 characters.';

    header('Location: member_profile.php');
    exit;
}

if (strlen($newPassword) > 72) {
    $_SESSION['profile_message'] =
        'New password must not exceed 72 bytes.';

    header('Location: member_profile.php');
    exit;
}

// Check confirmation.
if ($newPassword !== $confirmPassword) {
    $_SESSION['profile_message'] =
        'New passwords do not match.';

    header('Location: member_profile.php');
    exit;
}

try {

    // Retrieve the Member's current password hash.
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

    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$member ||
        !password_verify(
            $currentPassword,
            $member['password_hash']
        )
    ) {
        $_SESSION['profile_message'] =
            'Current password is incorrect.';

    } elseif (
        password_verify(
            $newPassword,
            $member['password_hash']
        )
    ) {
        $_SESSION['profile_message'] =
            'New password must be different from your current password.';

    } else {

        // Securely hash the new password.
        $newHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        // Update the password only if the original hash
        // is still unchanged.
        $update = $conn->prepare(
            "UPDATE users
             SET password_hash = ?
             WHERE id = ?
               AND role = 'member'
               AND password_hash = ?"
        );

        $update->bind_param(
            'sis',
            $newHash,
            $memberId,
            $member['password_hash']
        );

        $update->execute();

        $updated = $update->affected_rows === 1;
        $update->close();

        if ($updated) {
            session_regenerate_id(true);

            $_SESSION['profile_message'] =
                'Password changed successfully!';
        } else {
            $_SESSION['profile_message'] =
                'Password was changed elsewhere. Please try again.';
        }
    }

} catch (Throwable $e) {

    error_log(
        'Password change error: ' . $e->getMessage()
    );

    $_SESSION['profile_message'] =
        'Unable to change password. Please try again.';
}

header('Location: member_profile.php');
exit;
