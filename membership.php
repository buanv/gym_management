
<?php
session_start();

require_once __DIR__ . '/config/db.php';

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$isMember = isset($_SESSION['user_id'])
    && ($_SESSION['role'] ?? '') === 'member';

$error = '';

if (empty($_SESSION['membership_csrf'])) {
    $_SESSION['membership_csrf'] = bin2hex(random_bytes(32));
}

// Handle plan selection for logged-in members.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isMember) {
        header('Location: login.php');
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['membership_csrf'], $token)
    ) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $planId = filter_var(
            $_POST['plan_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$planId || $planId <= 0) {
            $error = 'Please select a valid membership plan.';
        } else {

            // Check that the selected plan is active.
            $planStmt = $conn->prepare(
                "SELECT id
                 FROM membership_plans
                 WHERE id = ?
                   AND status = 'active'
                 LIMIT 1"
            );

            $planStmt->bind_param('i', $planId);
            $planStmt->execute();

            $plan = $planStmt->get_result()->fetch_assoc();
            $planStmt->close();

            if (!$plan) {
                $error = 'This membership plan is unavailable.';
            } else {

                $userId = (int)$_SESSION['user_id'];

                try {
                    $conn->begin_transaction();

                    // Verify the logged-in member account.
                    $memberStmt = $conn->prepare(
                        "SELECT id
                         FROM users
                         WHERE id = ?
                           AND role = 'member'
                           AND status = 'active'
                           AND is_verified = 1
                         LIMIT 1"
                    );

                    $memberStmt->bind_param('i', $userId);
                    $memberStmt->execute();

                    $member = $memberStmt
                        ->get_result()
                        ->fetch_assoc();

                    $memberStmt->close();

                    if (!$member) {
                        throw new RuntimeException(
                            'Member account is not available.'
                        );
                    }

                    // Check the latest membership.
                    $existingStmt = $conn->prepare(
                        "SELECT id, payment_status, membership_status
                         FROM member_memberships
                         WHERE user_id = ?
                         ORDER BY id DESC
                         LIMIT 1
                         FOR UPDATE"
                    );

                    $existingStmt->bind_param('i', $userId);
                    $existingStmt->execute();

                    $existing = $existingStmt
                        ->get_result()
                        ->fetch_assoc();

                    $existingStmt->close();

                    // Do not overwrite an active membership.
                    if (
                        $existing &&
                        $existing['membership_status'] === 'active'
                    ) {
                        $conn->rollback();

                        $error = 'You already have an active membership. '
                            . 'Please contact staff to change your plan.';

                    } elseif (
                        $existing &&
                        $existing['payment_status'] === 'pending' &&
                        $existing['membership_status'] === 'pending'
                    ) {

                        // Update an existing unpaid, pending membership.
                        $membershipId = (int)$existing['id'];

                        $updateStmt = $conn->prepare(
                            "UPDATE member_memberships
                             SET plan_id = ?,
                                 payment_status = 'pending',
                                 membership_status = 'pending',
                                 start_date = NULL,
                                 end_date = NULL
                             WHERE id = ?
                               AND user_id = ?"
                        );

                        $updateStmt->bind_param(
                            'iii',
                            $planId,
                            $membershipId,
                            $userId
                        );

                        $updateStmt->execute();
                        $updateStmt->close();

                        $conn->commit();

                        header('Location: member_dashboard.php');
                        exit;

                    } else {

                        // Create a new pending membership.
                        $insertStmt = $conn->prepare(
                            "INSERT INTO member_memberships
                                (
                                    user_id,
                                    plan_id,
                                    payment_status,
                                    membership_status
                                )
                             VALUES (?, ?, 'pending', 'pending')"
                        );

                        $insertStmt->bind_param(
                            'ii',
                            $userId,
                            $planId
                        );

                        $insertStmt->execute();
                        $insertStmt->close();

                        $conn->commit();

                        header('Location: member_dashboard.php');
                        exit;
                    }

                } catch (Throwable $exception) {

                    $conn->rollback();

                    error_log(
                        'Membership selection error: '
                        . $exception->getMessage()
                    );

                    $error = 'Unable to save your membership. '
                        . 'Please try again.';
                }
            }
        }
    }
}

// Load available plans.
$result = $conn->query(
    "SELECT
        id,
        plan_name,
        price,
        duration_days,
        description
     FROM membership_plans
     WHERE status = 'active'
     ORDER BY price ASC"
);

if (!$result) {
    http_response_code(500);
    exit('Unable to load membership plans.');
}

$plans = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Membership Plans | 4BROS AND 1BOSS</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, sans-serif;
    color: white;

    background:
        linear-gradient(
            rgba(17, 24, 39, 0.78),
            rgba(17, 24, 39, 0.88)
        ),
        url('images/background.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

header {
    background: rgba(31, 41, 55, 0.85);
    padding: 25px 20px;
    text-align: center;
}

header h1 {
    color: #a3f348;
    margin: 10px 0;
    font-size: 40px;
}

header p {
    color: #57ed2a;
    font-size: 18px;
}

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 20px;
}

.membership-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 25px;
}

.mini-login,
.member-welcome {
    background: rgba(31, 41, 55, 0.92);
    border: 1px solid #374151;
    padding: 30px;
    border-radius: 12px;
    width: 100%;
    max-width: 430px;
    text-align: center;
}

.mini-login h3,
.member-welcome h3 {
    color: #22c55e;
    margin-top: 0;
}

.subtitle {
    color: #9ca3af;
    font-size: 14px;
    line-height: 1.6;
}

.login-group {
    margin-bottom: 18px;
    text-align: left;
}

.login-group label {
    display: block;
    font-size: 14px;
    color: #d1d5db;
    margin-bottom: 8px;
}

.login-group input {
    width: 100%;
    padding: 13px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    font-size: 14px;
}

.login-btn,
.choose-btn,
.dashboard-btn {
    display: inline-block;
    background: #22c55e;
    color: #111827;
    border: none;
    padding: 13px 20px;
    border-radius: 7px;
    font-weight: bold;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
}

.login-btn,
.choose-btn {
    width: 100%;
}

.login-btn:hover,
.choose-btn:hover,
.dashboard-btn:hover {
    background: #16a34a;
}

.login-footer {
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    margin-top: 20px;
}

.membership-section {
    width: 100%;
}

.membership-section h2 {
    text-align: center;
    margin-bottom: 30px;
}

.plans {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
}

.card {
    background: rgba(31, 41, 55, 0.92);
    padding: 30px 25px;
    border-radius: 12px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 1px solid #374151;
}

.card h3 {
    font-size: 21px;
    margin: 10px 0 20px;
}

.price {
    font-size: 32px;
    color: #22c55e;
    font-weight: bold;
    margin: 10px 0;
}

.duration {
    color: #d1d5db;
    font-size: 15px;
}

.description {
    color: #9ca3af;
    font-size: 14px;
    line-height: 1.6;
    margin: 15px 0;
}

.card form {
    margin-top: auto;
    padding-top: 20px;
    width: 100%;
}

.empty-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 30px;
    background: #1f2937;
    border-radius: 10px;
    color: #9ca3af;
}

.error {
    width: 100%;
    max-width: 650px;
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border: 1px solid #ef4444;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

footer {
    text-align: center;
    padding: 25px;
    color: #9ca3af;
    font-size: 13px;
    margin-top: 30px;
}

@media (max-width: 900px) {
    .plans {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    header h1 {
        font-size: 26px;
    }

    .container {
        margin: 15px auto;
        padding: 15px;
    }

    .plans {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 25px;
    }
}
</style>
</head>

<body>

<header>
    <h1>4BROS AND 1BOSS</h1>
    <p>Five Minds. One Mission. Stronger Together.</p>
</header>

<div class="container">

    <div class="membership-layout">

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($isMember): ?>

            <!-- EXISTING LOGGED-IN MEMBER -->
            <div class="member-welcome">

                <h3>
                    Welcome, <?= e(
                        $_SESSION['full_name'] ?? 'Member'
                    ) ?>!
                </h3>

                <p class="subtitle">
                    Choose a membership plan below.
                    Your selection will be saved to
                    your existing member account.
                </p>

                <a
                    href="member_dashboard.php"
                    class="dashboard-btn"
                >
                    Back to Member Dashboard
                </a>

            </div>

        <?php else: ?>

            <!-- VISITOR LOGIN -->
            <div class="mini-login">

                <h3>Already a Member?</h3>

                <p class="subtitle">
                    Log in to access your account.
                </p>

                <form action="login.php" method="POST">

                    <div class="login-group">
                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="login-group">
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

                    <button type="submit" class="login-btn">
                        Log In
                    </button>

                </form>

                <p class="login-footer">
                    Don't have an account?
                    Choose a membership plan below to register.
                </p>

            </div>

        <?php endif; ?>

        <!-- MEMBERSHIP PLANS -->
        <div class="membership-section">

            <h2>Our Membership Plans</h2>

            <div class="plans">

                <?php if (!empty($plans)): ?>

                    <?php foreach ($plans as $plan): ?>

                        <div class="card">

                            <h3>
                                <?= e($plan['plan_name']) ?>
                            </h3>

                            <p class="price">
                                ₱<?= number_format(
                                    (float)$plan['price'],
                                    2
                                ) ?>
                            </p>

                            <p class="duration">
                                <?= (int)$plan['duration_days'] ?>
                                Days Access
                            </p>

                            <p class="description">
                                <?= e($plan['description'] ?? '') ?>
                            </p>

                            <?php if ($isMember): ?>

                                <!-- SAVE TO EXISTING MEMBER -->
                                <form
                                    action="membership.php"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(
                                            $_SESSION['membership_csrf']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="plan_id"
                                        value="<?= (int)$plan['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="choose-btn"
                                    >
                                        Choose Membership
                                    </button>

                                </form>

                            <?php else: ?>

                                <!-- NEW VISITOR ACCOUNT FLOW -->
                                <form
                                    action="account_choice.php"
                                    method="GET"
                                >

                                    <input
                                        type="hidden"
                                        name="plan_id"
                                        value="<?= (int)$plan['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="choose-btn"
                                    >
                                        Choose Membership
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        No membership plans are currently available.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<footer>
    &copy; <?= date('Y') ?>
    Gym Membership Management System.
    All Rights Reserved.
</footer>

</body>
</html>
