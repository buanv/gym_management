
<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/send_verification_email.php';

$error = "";
$success = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape HTML output
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Process registration
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    // Public registration is for members only
    $role = "member";

    $submittedCsrf = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedCsrf) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $submittedCsrf
        )
    ) {
        $error = "Invalid form request. Please refresh.";

    } elseif (
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm === ""
    ) {
        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } elseif (strlen($full_name) > 100) {

        $error = "Full name is too long.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm) {

        $error = "Passwords do not match.";

    } else {

        // Validate selected membership plan
        $selected_plan_id = null;

        if (isset($_SESSION["selected_plan_id"])) {

            $plan_id = filter_var(
                $_SESSION["selected_plan_id"],
                FILTER_VALIDATE_INT
            );

            if (!$plan_id || $plan_id <= 0) {

                $error = "Invalid membership selection.";

            } else {

                $plan_stmt = $conn->prepare(
                    "SELECT id
                     FROM membership_plans
                     WHERE id = ?
                       AND status = 'active'
                     LIMIT 1"
                );

                $plan_stmt->bind_param("i", $plan_id);
                $plan_stmt->execute();

                $plan = $plan_stmt
                    ->get_result()
                    ->fetch_assoc();

                $plan_stmt->close();

                if (!$plan) {
                    $error = "Selected membership plan is unavailable.";
                } else {
                    $selected_plan_id = (int)$plan["id"];
                }
            }
        }

        if ($error === "") {

            // Check for duplicate email
            $check = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $check->bind_param("s", $email);
            $check->execute();

            $existing = $check
                ->get_result()
                ->fetch_assoc();

            $check->close();

            if ($existing) {

                $error = "Email already registered. "
                    . "Please log in or resend your "
                    . "verification email.";

            } else {

                // Securely hash the password
                $hashed = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Email verification is REQUIRED
                $verified = 0;

                // Account remains unable to log in
                // until the email is verified
                $status = "active";

                // Generate random verification token
                $verificationToken = bin2hex(
                    random_bytes(32)
                );

                // Store only the token hash
                $tokenHash = hash(
                    "sha256",
                    $verificationToken
                );

                try {

                    $conn->begin_transaction();

                    // Insert new member with
                    // verification information
                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (
                            full_name,
                            email,
                            password_hash,
                            role,
                            is_verified,
                            status,
                            verification_token,
                            token_expires_at
                        )
                        VALUES (
                            ?, ?, ?, ?, ?, ?, ?,
                            DATE_ADD(
                                NOW(),
                                INTERVAL 30 MINUTE
                            )
                        )"
                    );

                    $stmt->bind_param(
                        "ssssiss",
                        $full_name,
                        $email,
                        $hashed,
                        $role,
                        $verified,
                        $status,
                        $tokenHash
                    );

                    $stmt->execute();

                    $new_user_id = (int)$conn->insert_id;

                    $stmt->close();

                    // Save selected membership
                    if ($selected_plan_id !== null) {

                        $membership_stmt = $conn->prepare(
                            "INSERT INTO member_memberships
                            (
                                user_id,
                                plan_id,
                                payment_status,
                                membership_status
                            )
                            VALUES (
                                ?, ?,
                                'pending',
                                'pending'
                            )"
                        );

                        $membership_stmt->bind_param(
                            "ii",
                            $new_user_id,
                            $selected_plan_id
                        );

                        $membership_stmt->execute();
                        $membership_stmt->close();
                    }

                    // Commit account and membership
                    $conn->commit();

                    // Clear selected plan
                    unset($_SESSION["selected_plan_id"]);

                    // Refresh CSRF token
                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                    // Send verification email
                    $emailSent = sendVerificationEmail(
                        $email,
                        $full_name,
                        $verificationToken
                    );

                    if ($emailSent) {

                        $success =
                            "Account created successfully! "
                            . "We have sent a verification "
                            . "link to your email address. "
                            . "Please check your inbox "
                            . "and verify your email "
                            . "before logging in.";

                    } else {

                        $success =
                            "Your account was created, "
                            . "but the verification email "
                            . "could not be sent. "
                            . "Please contact support "
                            . "or request a new "
                            . "verification link.";
                    }

                } catch (Throwable $ex) {

                    $conn->rollback();

                    error_log(
                        "Registration failed: "
                        . $ex->getMessage()
                    );

                    $error =
                        "Unable to create account. "
                        . "Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Create Account | 4BROS AND 1BOSS</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 20px;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;

    display: flex;
    align-items: center;
    justify-content: center;
}

.box {
    background: #1f2937;
    border: 1px solid #374151;
    padding: 35px;
    border-radius: 12px;
    width: 420px;
    max-width: 100%;
}

h2 {
    text-align: center;
    color: #22c55e;
    margin-top: 0;
}

.subtitle {
    text-align: center;
    color: #9ca3af;
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.6;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 6px;
    color: #d1d5db;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 13px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 7px;
    color: white;
    font-size: 14px;
}

input:focus {
    outline: none;
    border-color: #22c55e;
}

button {
    width: 100%;
    padding: 13px;
    margin-top: 22px;
    background: #22c55e;
    color: #111827;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #16a34a;
}

.error {
    color: #f87171;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid #7f1d1d;
    border-radius: 7px;
    padding: 12px;
    font-size: 14px;
    text-align: center;
    line-height: 1.6;
}

.success {
    color: #4ade80;
    background: rgba(34, 197, 94, 0.10);
    border: 1px solid #166534;
    border-radius: 7px;
    padding: 15px;
    font-size: 14px;
    text-align: center;
    line-height: 1.7;
}

.login-link {
    text-align: center;
    color: #9ca3af;
    margin-top: 22px;
    font-size: 14px;
}

a {
    color: #22c55e;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="box">

    <h2>4BROS AND 1BOSS</h2>

    <p class="subtitle">
        Create your Member Account
    </p>

    <?php if ($error !== ""): ?>

        <p class="error" role="alert">
            <?= e($error) ?>
        </p>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div class="success" role="status">

            <h3>Check Your Email!</h3>

            <p><?= e($success) ?></p>

            <p>
                Verification links expire
                after 30 minutes.
            </p>

        </div>

        <p class="login-link">
            Already verified?
            <a href="login.php">
                Go to Login
            </a>
        </p>

        <p class="login-link">
            Didn't receive an email?
            <a href="resend_verification.php">
                Resend Verification
            </a>
        </p>

    <?php else: ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <label for="full_name">
                Full Name
            </label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="Enter your full name"
                maxlength="100"
                value="<?= e($_POST['full_name'] ?? '') ?>"
                autocomplete="name"
                required
            >

            <label for="email">
                Email Address
            </label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= e($_POST['email'] ?? '') ?>"
                autocomplete="email"
                required
            >

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Create password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">
                Confirm Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Confirm password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button type="submit">
                Create Member Account
            </button>

        </form>

        <p class="login-link">
            Already have an account?
            <a href="login.php">Log In</a>
        </p>

    <?php endif; ?>

    <p class="login-link">
        <a href="membership.php">
            Back to Membership Plans
        </a>
    </p>

</div>

</body>
</html>
