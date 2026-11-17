
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

$error = '';

$planName = '';
$priceInput = '';
$durationInput = '';

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

    // Validate plan name
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
                $status = 'active';

                $stmt = $conn->prepare(
                    "INSERT INTO membership_plans
                     (plan_name, price, duration_days, status)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'sdis',
                    $planName,
                    $price,
                    $duration,
                    $status
                );


                $stmt->execute();

                $newPlanId = $conn->insert_id;

                $stmt->close();

                logActivity(
                $conn,
                    (int)$_SESSION['user_id'],
                    "Created Membership Plan ID {$newPlanId}"
                );


                // Rotate CSRF token after success
                $_SESSION['plan_csrf'] = bin2hex(random_bytes(32));

                $_SESSION['plan_message'] =
                    'Membership plan added successfully!';

                header('Location: plans.php');
                exit;

            } catch (Throwable $e) {

                error_log(
                    'Add membership plan error: ' .
                    $e->getMessage()
                );

                $error = 'Unable to add membership plan.';
            }
        }
    }
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Membership Plan | 4BROS AND 1BOSS</title>

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
    margin-bottom: 20px;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="plans.php">Back to Plans</a>
</header>

<div class="container">

    <div class="card">

        <h1>Add Membership Plan</h1>

        <p class="subtitle">
            Create a new gym membership plan.
            New plans will automatically be available
            for Member registration.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['plan_csrf']) ?>"
            >

            <label for="plan_name">
                Membership Plan Name
            </label>

            <input
                type="text"
                id="plan_name"
                name="plan_name"
                placeholder="Example: Premium Membership"
                value="<?= escape($planName) ?>"
                maxlength="100"
                required
            >

            <label for="price">
                Membership Price (₱)
            </label>

            <input
                type="number"
                id="price"
                name="price"
                placeholder="Example: 1500.00"
                min="0.01"
                max="99999999.99"
                step="0.01"
                value="<?= escape($priceInput) ?>"
                required
            >

            <label for="duration_days">
                Membership Duration (Days)
            </label>

            <input
                type="number"
                id="duration_days"
                name="duration_days"
                placeholder="Example: 30"
                min="1"
                max="3650"
                step="1"
                value="<?= escape($durationInput) ?>"
                required
            >

            <button type="submit">
                Add Membership Plan
            </button>

        </form>

    </div>

</div>

</body>
</html>
