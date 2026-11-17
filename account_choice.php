
<?php
session_start();

require_once __DIR__ . '/config/db.php';

$plan_id = filter_input(
    INPUT_GET,
    'plan_id',
    FILTER_VALIDATE_INT
);

if (!$plan_id || $plan_id < 1) {
    header("Location: membership.php");
    exit;
}

// Confirm that the plan exists and is active.
$stmt = $conn->prepare(
    "SELECT id, plan_name
     FROM membership_plans
     WHERE id = ? AND status = 'active'"
);

$stmt->bind_param("i", $plan_id);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    header("Location: membership.php");
    exit;
}

// Remember the selected plan during login
// or account registration.
$_SESSION['selected_plan_id'] = (int)$plan['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Account Selection</title>

<style>
body {
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
}

.box {
    background: #1f2937;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    width: 350px;
    max-width: 90%;
}

a {
    display: block;
    padding: 14px;
    margin-top: 15px;
    border-radius: 6px;
    background: #22c55e;
    color: #111827;
    text-decoration: none;
    font-weight: bold;
}

a:hover {
    background: #16a34a;
}

.secondary {
    background: #374151;
    color: white;
}
</style>
</head>

<body>

<div class="box">

    <h2>Do you have an account?</h2>

    <p>
        Selected Plan:
        <strong>
            <?= htmlspecialchars(
                $plan['plan_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

    <a href="login.php">
        Yes, I Have an Account
    </a>

    <a href="register.php" class="secondary">
        No, Create an Account
    </a>

</div>

</body>
</html>
