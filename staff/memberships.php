
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Staff access only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

// Retrieve all membership records
$sql = "
    SELECT
        mm.id AS membership_id,
        u.id AS member_id,
        u.full_name,
        u.email,
        mp.plan_name,
        mp.price,
        mm.payment_status,
        mm.membership_status,
        mm.start_date,
        mm.end_date
    FROM member_memberships mm
    INNER JOIN users u
        ON mm.user_id = u.id
    INNER JOIN membership_plans mp
        ON mm.plan_id = mp.id
    WHERE u.role = 'member'
    ORDER BY mm.id DESC
";

$result = $conn->query($sql);

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

<title>4BROS AND 1BOSS</title>

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
    max-width: 1400px;
    margin: auto;
    padding: 35px 20px;
}

.subtitle {
    color: #9ca3af;
    margin-bottom: 25px;
}

.table-box {
    background: #1f2937;
    border-radius: 12px;
    padding: 20px;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}

th {
    background: #273449;
    color: #4ade80;
    text-align: left;
    padding: 14px;
}

td {
    padding: 14px;
    border-bottom: 1px solid #374151;
}

tr:hover {
    background: #263244;
}

.status {
    font-weight: bold;
    text-transform: capitalize;
}

.active, .paid {
    color: #4ade80;
}

.pending {
    color: #facc15;
}

.expired, .cancelled {
    color: #f87171;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #9ca3af;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="container">

    <h1>Membership Management</h1>

    <p class="subtitle">
        View gym membership plans, payments,
        activation dates, and expiration dates.
    </p>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>Membership ID</th>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>Expiration Date</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= escape($row['membership_id']) ?></td>
                        <td><?= escape($row['member_id']) ?></td>
                        <td><?= escape($row['full_name']) ?></td>
                        <td><?= escape($row['email']) ?></td>
                        <td><?= escape($row['plan_name']) ?></td>
                        <td>₱<?= number_format((float)$row['price'], 2) ?></td>

                        <td>
                            <span class="status <?= escape($row['payment_status']) ?>">
                                <?= escape($row['payment_status']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="status <?= escape($row['membership_status']) ?>">
                                <?= escape($row['membership_status']) ?>
                            </span>
                        </td>

                        <td><?= escape($row['start_date'] ?? 'Not Started') ?></td>
                        <td><?= escape($row['end_date'] ?? 'Not Available') ?></td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="10" class="empty">
                        No membership records found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
