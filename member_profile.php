
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

$memberId = (int) $_SESSION['user_id'];


if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$profileMessage = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);


// Retrieve the logged-in Member's information.
$stmt = $conn->prepare(
    "SELECT id, full_name, email, status, created_at
     FROM users
     WHERE id = ?
       AND role = 'member'
     LIMIT 1"
);

$stmt->bind_param('i', $memberId);
$stmt->execute();

$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    http_response_code(404);
    exit('Member account not found.');
}

function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Profile | Gym Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
}

header {
    background: #1f2937;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

header a {
    color: #22c55e;
    text-decoration: none;
}

.container {
    max-width: 850px;
    margin: auto;
    padding: 35px 20px;
}

.subtitle {
    color: #9ca3af;
}

.profile-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 30px;
    margin-top: 25px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
}

.avatar {
    width: 75px;
    height: 75px;
    border-radius: 50%;
    background: #22c55e;
    color: #111827;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 30px;
    font-weight: bold;
}

.profile-header h2 {
    margin: 0 0 8px;
}

.profile-header p {
    color: #9ca3af;
    margin: 0;
}

.profile-row {
    padding: 18px 0;
    border-bottom: 1px solid #374151;
}

.profile-row:last-child {
    border-bottom: none;
}

.profile-label {
    color: #9ca3af;
    font-size: 14px;
    margin-bottom: 8px;
}

.profile-value {
    font-size: 17px;
    overflow-wrap: anywhere;
}

.active {
    color: #4ade80;
    font-weight: bold;
}

.inactive {
    color: #f87171;
    font-weight: bold;
}

@media (max-width: 600px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
}

.profile-card form {
    margin-top: 20px;
}

.profile-card label {
    display: block;
    margin: 18px 0 8px;
    color: #d1d5db;
    font-weight: bold;
}

.profile-card input[type="text"],
.profile-card input[type="password"] {
    display: block;
    width: 100%;
    padding: 13px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 8px;
    color: white;
    font-size: 15px;
}

.profile-card input:focus {
    outline: none;
    border-color: #22c55e;
}

.profile-card button {
    background: #22c55e;
    color: #111827;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 22px;
}

.profile-card button:hover {
    background: #16a34a;
}

.profile-message {
    background: #273449;
    color: #4ade80;
    padding: 14px;
    border-radius: 8px;
}

</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="member_dashboard.php">
        Back to Dashboard
    </a>
</header>

<div class="container">

    <h1>My Profile</h1>

    <p class="subtitle">
        View your gym membership account information.
    </p>

    <div class="profile-card">

        <div class="profile-header">

            <div class="avatar">
                <?= escape(
                    strtoupper(
                        substr($member['full_name'], 0, 1)
                    )
                ) ?>
            </div>

            <div>
                <h2><?= escape($member['full_name']) ?></h2>
                <p>Gym Member</p>
            </div>

        </div>

        <div class="profile-row">
            <div class="profile-label">Member ID</div>
            <div class="profile-value">
                <?= escape($member['id']) ?>
            </div>
        </div>

        <div class="profile-row">
            <div class="profile-label">Full Name</div>
            <div class="profile-value">
                <?= escape($member['full_name']) ?>
            </div>
        </div>

        <div class="profile-row">
            <div class="profile-label">Email Address</div>
            <div class="profile-value">
                <?= escape($member['email']) ?>
            </div>
        </div>

        <div class="profile-row">
            <div class="profile-label">Account Status</div>
            <div class="profile-value">
                <span class="<?= $member['status'] === 'active'
                    ? 'active'
                    : 'inactive' ?>">
                    <?= escape(ucfirst($member['status'])) ?>
                </span>
            </div>
        </div>

        <div class="profile-row">
            <div class="profile-label">Date Registered</div>
            <div class="profile-value">
                <?= escape($member['created_at']) ?>
            </div>
        </div>

    </div>

<div class="profile-card">

    <h2>Edit Profile</h2>

    <p class="subtitle">
        Update your full name. Enter your current
        password to confirm the change.
    </p>

    <?php if ($profileMessage !== ''): ?>
        <p class="profile-message">
            <?= escape($profileMessage) ?>
        </p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['profile_csrf']) ?>"
        >

        <label for="full_name">Full Name</label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            value="<?= escape($member['full_name']) ?>"
            maxlength="100"
            autocomplete="name"
            required
        >

        <label for="current_password">
            Current Password
        </label>

        <input
            type="password"
            id="current_password"
            name="current_password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Save Changes
        </button>

    </form>
<?php if ($profileMessage !== ''): ?>
    <p class="profile-message">
        <?= escape($profileMessage) ?>
    </p>
<?php endif; ?>

<div class="profile-card">

    <h2>Change Password</h2>

    <p class="subtitle">
        Update your account password to keep
        your gym membership account secure.
    </p>
    <form action="change_password.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['profile_csrf']) ?>"
        >

        <label for="password_current">
            Current Password
        </label>

        <input
            type="password"
            id="password_current"
            name="current_password"
            autocomplete="current-password"
            required
        >

        <label for="password_new">
            New Password
        </label>

        <input
            type="password"
            id="password_new"
            name="new_password"
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <label for="password_confirm">
            Confirm New Password
        </label>

        <input
            type="password"
            id="password_confirm"
            name="confirm_password"
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <button type="submit">
            Change Password
        </button>

    </form>

</div>


</div>

</div>

</body>
</html>
