
<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = "";
$email = "";

// Redirect users according to their roles
function redirectUser($role) {
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;

        case 'staff':
            header('Location: staff/dashboard.php');
            break;

        case 'member':
            header('Location: member_dashboard.php');
            break;

        default:
            header('Location: login.php');
            break;
    }

    exit;
}

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        // Find user by email
        $stmt = $conn->prepare(
            "SELECT
                id,
                full_name,
                email,
                password_hash,
                role,
                is_verified,
                status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        // Check credentials
        if (
            !$user ||
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {

            $error = "Invalid email or password.";

        } elseif ((int)$user['is_verified'] !== 1) {

            $error = "Please verify your email before logging in.";

        } elseif ($user['status'] !== 'active') {

            $error = "Your account is not active. Please contact the administrator.";

        } elseif (!in_array(
            $user['role'],
            ['admin', 'staff', 'member'],
            true
        )) {

            $error = "Unauthorized account role.";

        } else {

            // Successful login
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Redirect to appropriate dashboard
            redirectUser($user['role']);
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

<title>Gym Management Login | 4BROS AND 1BOSS</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 25px;
    font-family: Arial, sans-serif;
    color: white;

    display: flex;
    justify-content: center;
    align-items: center;

    background:
        linear-gradient(
            rgba(17, 24, 39, 0.75),
            rgba(17, 24, 39, 0.40)
        ),
        url('images/bg3.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

/* MAIN LOGIN CONTAINER */
.login-container {
    display: flex;
    width: 750px;
    max-width: 100%;
    min-height: 420px;
    background: #1f2937;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.35);
}

/* LEFT IMAGE */
.login-image {
    width: 50%;
    min-height: 420px;

    background:
        linear-gradient(
            rgba(0, 0, 0, 0.15),
            rgba(0, 0, 0, 0.45)
        ),
        url('images/bg2.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

.login-image::after {
    content: "";
    position: absolute;
    inset: 0;

    background: linear-gradient(
        to top,
        rgba(0,0,0,0.45),
        transparent
    );
}

/* RIGHT LOGIN FORM */
.login-form {
    width: 50%;
    padding: 30px;
    background: rgba(31, 41, 55, 0.96);

    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-form h2 {
    color: #22c55e;
    text-align: center;
    font-size: 21px;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #9ca3af;
    font-size: 14px;
    margin-bottom: 30px;
}

/* INPUT FIELDS */
.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    color: #d1d5db;
}

.form-group input {
    width: 100%;
    padding: 14px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus {
    border-color: #22c55e;
}

/* LOGIN BUTTON */
.login-btn {
    width: 100%;
    padding: 14px;
    background: #22c55e;
    color: #111827;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s ease;
}

.login-btn:hover {
    background: #16a34a;
}

/* LINKS */
.register {
    text-align: center;
    margin-top: 25px;
    color: #9ca3af;
    font-size: 14px;
}

.register a {
    color: #22c55e;
    font-weight: bold;
    text-decoration: none;
}

.register a:hover {
    text-decoration: underline;
}

.back-link {
    display: block;
    margin-top: 18px;
    text-align: center;
    color: #9ca3af;
    text-decoration: none;
    font-size: 13px;
}

.back-link:hover {
    color: #22c55e;
}

/* ERROR MESSAGE */
.error {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
    text-align: center;
    margin-bottom: 20px;
}

/* RESPONSIVE DESIGN */
@media (max-width: 700px) {
    .login-container {
        flex-direction: column;
    }

    .login-image {
        width: 100%;
        min-height: 180px;
    }

    .login-form {
        width: 100%;
        padding: 25px;
    }
}
</style>
</head>

<body>

<div class="login-container">

    <!-- LEFT SIDE: GYM IMAGE -->
    <div
        class="login-image"
        role="img"
        aria-label="Gym interior"
    ></div>

    <!-- RIGHT SIDE: LOGIN FORM -->
    <div class="login-form">

        <h2>4BROS AND 1BOSS</h2>

        <p class="subtitle">
            Welcome back! Log in to continue.
        </p>

        <?php if ($error): ?>

            <div class="error" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars(
                        $email,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button
                type="submit"
                class="login-btn"
            >
                Log In
            </button>

        </form>

        <p class="register">
            Don't have an account?
            <a href="register.php">
                Create Account
            </a>
        </p>

        <a
            href="membership.php"
            class="back-link"
        >
            Back to Membership Plans
        </a>

    </div>

</div>

</body>
</html>
