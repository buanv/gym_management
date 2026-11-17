
<?php
session_start();

require_once __DIR__ . '/config/db.php';

$message = '';
$success = false;

// Escape text for safe HTML output
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Read the verification token from the email link
$token = $_GET['token'] ?? '';

// Validate token format
if (
    !is_string($token) ||
    !preg_match('/^[a-f0-9]{64}$/', $token)
) {

    $message = 'Invalid verification link. '
        . 'Please request a new verification email.';

} else {

    // Hash the token to match the value stored in MySQL
    $tokenHash = hash('sha256', $token);

    try {

        // Start transaction to prevent duplicate verification
        $conn->begin_transaction();

        // Find the account associated with this token
        $stmt = $conn->prepare(
            "SELECT
                id,
                is_verified,
                token_expires_at
             FROM users
             WHERE verification_token = ?
               AND role = 'member'
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$user) {

            $message = 'This verification link is invalid '
                . 'or has already been used.';

        } elseif ((int)$user['is_verified'] === 1) {

            $message = 'This account has already been verified. '
                . 'You can proceed to login.';

            $success = true;

        } elseif (
            empty($user['token_expires_at']) ||
            strtotime($user['token_expires_at']) === false ||
            strtotime($user['token_expires_at']) <= time()
        ) {

            $message = 'This verification link has expired. '
                . 'Please request another verification email.';

        } else {

            // Get the user ID
            $userId = (int)$user['id'];

            // Verify the account and invalidate the token
            $update = $conn->prepare(
                "UPDATE users
                 SET
                    is_verified = 1,
                    verification_token = NULL,
                    token_expires_at = NULL
                 WHERE id = ?
                   AND role = 'member'
                   AND is_verified = 0
                   AND verification_token = ?"
            );

            $update->bind_param(
                'is',
                $userId,
                $tokenHash
            );

            $update->execute();

            $affectedRows = $update->affected_rows;

            $update->close();

            if ($affectedRows === 1) {

                $success = true;

                $message = 'Your email has been verified '
                    . 'successfully! You can now log in '
                    . 'to your member account.';

            } else {

                $message = 'Unable to verify this account. '
                    . 'Please request another verification link.';
            }
        }

        // Save the changes
        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        error_log(
            'Email verification error: '
            . $e->getMessage()
        );

        $success = false;

        $message = 'Something went wrong while verifying '
            . 'your email. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Email Verification | 4BROS AND 1BOSS</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 450px;
    padding: 35px;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 14px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}

.logo {
    font-size: 25px;
    color: #22c55e;
    font-weight: bold;
    margin-bottom: 10px;
}

.subtitle {
    color: #9ca3af;
    font-size: 14px;
    margin-bottom: 25px;
}

h2 {
    color: #22c55e;
    margin-top: 10px;
}

.message {
    margin: 20px 0;
    line-height: 1.7;
    font-size: 15px;
    color: #d1d5db;
}

.success {
    color: #4ade80;
}

.error {
    color: #f87171;
}

.icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.button {
    display: inline-block;
    margin-top: 15px;
    padding: 13px 25px;
    background: #22c55e;
    color: #111827;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    transition: background 0.3s ease;
}

.button:hover {
    background: #16a34a;
}

.secondary-link {
    display: block;
    margin-top: 20px;
    color: #9ca3af;
    text-decoration: none;
    font-size: 13px;
}

.secondary-link:hover {
    color: #22c55e;
}

footer {
    margin-top: 30px;
    color: #6b7280;
    font-size: 12px;
}
</style>
</head>

<body>

<div class="card">

    <div class="logo">
        4BROS AND 1BOSS
    </div>

    <p class="subtitle">
        Gym Membership Management System
    </p>

    <?php if ($success): ?>

        <div class="icon">✓</div>

        <h2>Email Verified!</h2>

        <p class="message success">
            <?= e($message) ?>
        </p>

        <a href="login.php" class="button">
            Proceed to Login
        </a>

    <?php else: ?>

        <div class="icon">✉</div>

        <h2>Email Verification</h2>

        <p class="message error">
            <?= e($message) ?>
        </p>

        <a
            href="resend_verification.php"
            class="button"
        >
            Resend Verification Email
        </a>

        <a href="login.php" class="secondary-link">
            Back to Login
        </a>

    <?php endif; ?>

    <footer>
        &copy; <?= date('Y') ?>
        4BROS AND 1BOSS Gym.
        All Rights Reserved.
    </footer>

</div>

</body>
</html>
