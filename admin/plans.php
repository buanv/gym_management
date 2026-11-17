
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Admin only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// Get feedback message
$planMessage = $_SESSION['plan_message'] ?? '';
unset($_SESSION['plan_message']);

// Generate CSRF token
if (empty($_SESSION['plan_csrf'])) {
    $_SESSION['plan_csrf'] = bin2hex(random_bytes(32));
}

// Escape HTML output
function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Retrieve membership plans
$stmt = $conn->prepare(
    "SELECT
        id,
        plan_name,
        price,
        duration_days,
        status
     FROM membership_plans
     ORDER BY id DESC"
);

$stmt->execute();

$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Manage Membership Plans | Admin</title>

<link rel="stylesheet" href="sidebar.css">

<style>

/* GENERAL */
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
}

/* HEADER */
header {
    background: #1f2937;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

header a {
    color: #22c55e;
    text-decoration: none;
}

header a:hover {
    color: #4ade80;
}

/* CONTENT */
.container {
    max-width: 1100px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
}

/* CARD */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

/* TABLE */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
    white-space: nowrap;
}

td {
    color: #d1d5db;
}

/* STATUS */
td.active {
    color: #4ade80;
    font-weight: bold;
}

td.inactive {
    color: #f87171;
    font-weight: bold;
}

/* ADD PLAN BUTTON */
.add-btn {
    display: inline-block;
    background: #22c55e;
    color: #111827;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    margin-top: 15px;
}

.add-btn:hover {
    background: #16a34a;
}

/* MESSAGE */
.plan-message {
    background: #273449;
    color: #4ade80;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

/* EDIT BUTTON */
.edit-btn {
    display: inline-block;
    background: #2563eb;
    color: white;
    padding: 9px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
}

.edit-btn:hover {
    background: #1d4ed8;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons form {
    margin: 0;
}

.action-buttons button {
    padding: 9px 14px;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

/* ACTIVATE */
.activate-btn {
    background: #22c55e;
    color: #111827;
}

.activate-btn:hover {
    background: #16a34a;
}

/* DEACTIVATE */
.deactivate-btn {
    background: #dc2626;
    color: white;
}

.deactivate-btn:hover {
    background: #b91c1c;
}

/* MOBILE */
@media (max-width: 600px) {

    header {
        flex-direction: column;
        padding: 18px;
        gap: 12px;
    }

    .card {
        padding: 15px;
    }

}

</style>
</head>

<body>

<!-- HEADER -->
<header>

    <h2>4BROS AND 1BOSS</h2>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</header>

<!-- ADMIN LAYOUT -->
<div class="admin-layout">

    <!-- LEFT SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Membership Plan Management</h1>

            <p class="subtitle">
                Manage gym membership plans, pricing,
                and duration.
            </p>

            <!-- FEEDBACK MESSAGE -->
            <?php if ($planMessage !== ''): ?>

                <p class="plan-message">
                    <?= escape($planMessage) ?>
                </p>

            <?php endif; ?>

            <!-- ADD PLAN -->
            <a href="add_plan.php" class="add-btn">
                + Add Membership Plan
            </a>

            <!-- PLANS CARD -->
            <div class="card">

                <h2>Existing Membership Plans</h2>

                <div class="table-container">

                    <table>

                        <thead>
                            <tr>
                                <th>Plan ID</th>
                                <th>Plan Name</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($plans as $plan): ?>

                            <tr>

                                <td>
                                    <?= (int)$plan['id'] ?>
                                </td>

                                <td>
                                    <?= escape($plan['plan_name']) ?>
                                </td>

                                <td>
                                    ₱<?= number_format(
                                        (float)$plan['price'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int)$plan['duration_days'] ?> days
                                </td>

                                <td class="<?= $plan['status'] === 'active'
                                    ? 'active'
                                    : 'inactive' ?>">
                                    <?= escape(
                                        ucfirst($plan['status'])
                                    ) ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <!-- EDIT PLAN -->
                                        <a
                                            href="edit_plan.php?id=<?= (int)$plan['id'] ?>"
                                            class="edit-btn"
                                        >
                                            Edit
                                        </a>

                                        <!-- CHANGE STATUS -->
                                        <form
                                            action="update_plan_status.php"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to change this membership plan status?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escape(
                                                    $_SESSION['plan_csrf']
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="plan_id"
                                                value="<?= (int)$plan['id'] ?>"
                                            >

                                            <?php if ($plan['status'] === 'active'): ?>

                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="inactive"
                                                >

                                                <button
                                                    type="submit"
                                                    class="deactivate-btn"
                                                >
                                                    Deactivate
                                                </button>

                                            <?php else: ?>

                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="active"
                                                >

                                                <button
                                                    type="submit"
                                                    class="activate-btn"
                                                >
                                                    Activate
                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($plans)): ?>

                            <tr>
                                <td colspan="6">
                                    No membership plans found.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>
