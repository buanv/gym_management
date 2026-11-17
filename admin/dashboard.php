
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only authenticated admins can access.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    exit("Access denied. Admin only.");
}

// Total members
$members = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'member'"
);
$total_members = (int)$members->fetch_assoc()['total'];

// Total staff
$staff = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'staff'"
);
$total_staff = (int)$staff->fetch_assoc()['total'];

// Total membership plans
$plans = $conn->query(
    "SELECT COUNT(*) AS total
     FROM membership_plans"
);
$total_plans = (int)$plans->fetch_assoc()['total'];

// Recent activity logs
$logs = $conn->query(
    "SELECT action, related_record
     FROM activity_logs
     ORDER BY id DESC
     LIMIT 5"
);

$admin_name = htmlspecialchars(
    $_SESSION['full_name'] ?? 'Admin',
    ENT_QUOTES,
    'UTF-8'
);

// Total registered members
$totalMembers = $total_members;

// Active memberships
$result = $conn->query(
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM member_memberships
     WHERE membership_status = 'active'
       AND payment_status = 'paid'
       AND start_date <= CURDATE()
       AND end_date >= CURDATE()"
);
$activeMemberships = (int)$result->fetch_assoc()['total'];

// Pending payments
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM payments
     WHERE payment_status = 'pending'"
);
$pendingPayments = (int)$result->fetch_assoc()['total'];

// Members currently inside the gym
$result = $conn->query(
    "SELECT COUNT(DISTINCT member_id) AS total
     FROM attendance
     WHERE time_out IS NULL"
);
$membersInside = (int)$result->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | 4BROS AND 1BOSS</title>

<link rel="stylesheet" href="sidebar.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #111827;
    color: white;
}

/* HEADER */
header {
    background: #1f2937;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

.logout {
    background: #ef4444;
    padding: 10px 18px;
    border-radius: 6px;
    color: white;
    text-decoration: none;
}

.logout:hover {
    background: #dc2626;
}

/* ADMIN MAIN LAYOUT */
.admin-layout {
    display: flex;
    min-height: calc(100vh - 82px);
}

/* SIDEBAR */
.admin-sidebar {
    width: 250px;
    flex-shrink: 0;
    background: #1f2937;
    padding: 25px 18px;
    border-right: 1px solid #374151;
}

.admin-sidebar h3 {
    color: #22c55e;
    font-size: 18px;
    margin: 0 0 25px;
}

.admin-nav {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.admin-nav a {
    display: block;
    background: #374151;
    color: white;
    padding: 14px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    transition: background 0.2s ease;
}

.admin-nav a:hover {
    background: #4b5563;
}

.admin-nav a.active {
    background: #16a34a;
    font-weight: bold;
}

/* MAIN CONTENT */
.admin-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.page-title {
    margin-top: 0;
    color: #ffffff;
}

.welcome {
    color: #d1d5db;
    margin-bottom: 30px;
}

/* ADMIN STATISTICS */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-top: 25px;
    margin-bottom: 30px;
}

.stat-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
}

.stat-card h3 {
    color: #9ca3af;
    font-size: 15px;
    margin: 0 0 15px;
}

.stat-card p {
    color: #4ade80;
    font-size: 32px;
    font-weight: bold;
    margin: 0;
}

/* SECONDARY STATISTICS */
.cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.card {
    background: #1f2937;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #374151;
}

.card h3 {
    color: #9ca3af;
    font-size: 16px;
}

.card h1 {
    color: #22c55e;
    font-size: 36px;
    margin-bottom: 0;
}

/* ACTIVITY LOGS */
.section {
    background: #1f2937;
    padding: 25px;
    margin-top: 30px;
    border-radius: 10px;
}

.section h2 {
    margin-top: 0;
}

.section ul {
    padding-left: 20px;
}

.section li {
    margin-bottom: 12px;
    color: #d1d5db;
    line-height: 1.6;
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .cards {
        grid-template-columns: 1fr;
    }

    .admin-content {
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .admin-layout {
        flex-direction: column;
    }

    .admin-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #374151;
    }

    .admin-content {
        padding: 20px;
    }
}

@media (max-width: 500px) {
    header {
        padding: 18px;
    }

    header h2 {
        font-size: 18px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- HEADER -->
<header>

    <h2>4BROS AND 1BOSS</h2>

    <a href="../logout.php" class="logout">
        Logout
    </a>

</header>

<!-- ADMIN LAYOUT -->
<div class="admin-layout">

    <!-- PERMANENT SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1 class="page-title">
                Admin Dashboard
            </h1>

            <p class="welcome">
                Welcome, <?= $admin_name ?>!
            </p>

            <h2>Gym Overview</h2>

            <!-- MAIN STATISTICS -->
            <div class="stats-grid">

                <div class="stat-card">
                    <h3>Total Members</h3>
                    <p><?= number_format($totalMembers) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Active Memberships</h3>
                    <p><?= number_format($activeMemberships) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <p><?= number_format($pendingPayments) ?></p>
                </div>

                <div class="stat-card">
                    <h3>Members Inside Gym</h3>
                    <p><?= number_format($membersInside) ?></p>
                </div>

            </div>

            <!-- SECONDARY STATISTICS -->
            <div class="cards">

                <div class="card">
                    <h3>Total Members</h3>
                    <h1><?= number_format($total_members) ?></h1>
                </div>

                <div class="card">
                    <h3>Total Staff</h3>
                    <h1><?= number_format($total_staff) ?></h1>
                </div>

                <div class="card">
                    <h3>Membership Plans</h3>
                    <h1><?= number_format($total_plans) ?></h1>
                </div>

            </div>

            <!-- RECENT ACTIVITY LOGS -->
            <div class="section">

                <h2>Recent Activity Logs</h2>

                <?php if ($logs && $logs->num_rows > 0): ?>

                    <ul>
                        <?php while ($log = $logs->fetch_assoc()): ?>

                            <li>
                                <?= htmlspecialchars(
                                    $log['action'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $log['related_record'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </li>

                        <?php endwhile; ?>
                    </ul>

                <?php else: ?>

                    <p>No recent activities.</p>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>
</html>
