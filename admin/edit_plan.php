
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Admin access only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// Create CSRF token
if (empty($_SESSION['plan_csrf'])) {
    $_SESSION['plan_csrf'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

$planId = filter_var(
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['plan_id'] ?? null)
        : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($planId === false || $planId === null) {
    http_response_code(400);
    exit('Invalid membership plan ID.');
}

// Get the existing plan
$stmt = $conn->prepare(
    "SELECT id, plan_name, price, duration_days, status
     FROM membership_plans
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param('i', $planId);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) {
    http_response_code(404);
    exit('Membership plan not found.');
}

$planName = $plan['plan_name'];
$priceInput = $plan['price'];
$durationInput = $plan['duration_days'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['plan_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $planName = trim((string)($_POST['plan_name'] ?? ''));
    $priceInput = trim((string)($_POST['price'] ?? ''));
    $durationInput = trim((string)($_POST['duration_days'] ?? ''));

    if (
        $planName === '' ||
        mb_strlen($planName) > 100
    ) {
        $error = 'Plan name must contain 1 to 100 characters.';

    } elseif (
        !preg_match('/^\d+(?:\.\d{1,2})?$/', $priceInput) ||
        (float)$priceInput <= 0 ||
        (float)$priceInput > 99999999.99
    ) {
        $error = 'Enter a valid price greater than zero.';

    } else {

        $duration = filter_var(
            $durationInput,
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => 1,
                'max_range' => 3650
            ]]
        );

        if ($duration === false) {
            $error = 'Duration must be between 1 and 3650 days.';

        } else {

            try {
                $price = (float)$priceInput;

                $update = $conn->prepare(
                    "UPDATE membership_plans
                     SET plan_name = ?,
                         price = ?,
                         duration_days = ?
                     WHERE id = ?"
                );

                $update->bind_param(
                    'sdii',
                    $planName,
                    $price,
                    $duration,
                    $planId
                );

                $update->execute();
                $update->close();


                logActivity(
                   $conn,
                  (int)$_SESSION['user_id'],
                  "Updated Membership Plan ID {$planId}"
                );


                $_SESSION['plan_csrf'] =
                    bin2hex(random_bytes(32));

                $_SESSION['plan_message'] =
                    'Membership plan updated successfully!';

                header('Location: plans.php');
                exit;

            } catch (Throwable $e) {

                error_log(
                    'Edit membership plan error: ' .
                    $e->getMessage()
                );

                $error = 'Unable to update membership plan.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Membership Plan | 4BROS AND 1BOSS</title>

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
    padding: 20px 30px;
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
    max-width: 650px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 30px;
}

.subtitle {
    color: #9ca3af;
    line-height: 1.6;
}

label {
    display: block;
    margin-top: 20px;
    margin-bottom: 8px;
    font-weight: bold;
}

input {
    width: 100%;
    padding: 13px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #22c55e;
}

button {
    width: 100%;
    padding: 14px;
    margin-top: 25px;
    background: #22c55e;
    color: #111827;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #16a34a;
}

.error {
    background: #450a0a;
    color: #fca5a5;
    padding: 14px;
    border-radius: 8px;
}
</style>
</head>

<body>

<header>
    <h2>GYM ADMIN</h2>
    <a href="plans.php">Back to Plans</a>
</header>

<div class="container">

    <div class="card">

        <h1>Edit Membership Plan</h1>

        <p class="subtitle">
            Update the selected membership plan.
        </p>

        <?php if ($error !== ''): ?>
            <p class="error"><?= escape($error) ?></p>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['plan_csrf']) ?>"
            >

            <input
                type="hidden"
                name="plan_id"
                value="<?= (int)$planId ?>"
            >

            <label for="plan_name">Plan Name</label>

            <input
                type="text"
                id="plan_name"
                name="plan_name"
                value="<?= escape($planName) ?>"
                maxlength="100"
                required
            >

            <label for="price">Price (₱)</label>

            <input
                type="number"
                id="price"
                name="price"
                min="0.01"
                max="99999999.99"
                step="0.01"
                value="<?= escape($priceInput) ?>"
                required
            >

            <label for="duration_days">
                Duration (Days)
            </label>

            <input
                type="number"
                id="duration_days"
                name="duration_days"
                min="1"
                max="3650"
                step="1"
                value="<?= escape($durationInput) ?>"
                required
            >

            <button type="submit">
                Save Changes
            </button>

        </form>

    </div>

</div>

</body>
</html>
