
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

// Escape HTML output
function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Helper for statistics
function getCount($conn, $sql) {
    $result = $conn->query($sql);
    return (int)$result->fetch_row()[0];
}

// ACCOUNT STATISTICS
$totalMembers = getCount(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'member'"
);

$totalStaff = getCount(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'staff'"
);

// MEMBERSHIP STATISTICS
$activeMemberships = getCount(
    $conn,
    "SELECT COUNT(*)
     FROM member_memberships
     WHERE membership_status = 'active'
       AND payment_status = 'paid'
       AND start_date <= CURDATE()
       AND end_date >= CURDATE()"
);

$pendingMemberships = getCount(
    $conn,
    "SELECT COUNT(*)
     FROM member_memberships
     WHERE membership_status = 'pending'"
);

// PAYMENT STATISTICS
$pendingPayments = getCount(
    $conn,
    "SELECT COUNT(*)
     FROM payments
     WHERE payment_status = 'pending'"
);

$paidPayments = getCount(
    $conn,
    "SELECT COUNT(*)
     FROM payments
     WHERE payment_status = 'paid'"
);

$rejectedPayments = getCount(
    $conn,
    "SELECT COUNT(*)
     FROM payments
     WHERE payment_status = 'rejected'"
);

// TOTAL CONFIRMED REVENUE
$revenueResult = $conn->query(
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE payment_status = 'paid'"
);

$totalRevenue = (float)$revenueResult->fetch_row()[0];

// MONTHLY REVENUE DATA
$monthlyResult = $conn->query(
    "SELECT
        DATE_FORMAT(verified_at, '%Y-%m') AS month,
        SUM(amount) AS revenue
     FROM payments
     WHERE payment_status = 'paid'
       AND verified_at IS NOT NULL
     GROUP BY DATE_FORMAT(verified_at, '%Y-%m')
     ORDER BY month ASC"
);

$monthlyLabels = [];
$monthlyRevenue = [];

while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyLabels[] = $row['month'];
    $monthlyRevenue[] = (float)$row['revenue'];
}

// MEMBERSHIP PLAN POPULARITY
$planResult = $conn->query(
    "SELECT
        mp.plan_name,
        COUNT(mm.id) AS total_memberships
     FROM membership_plans mp
     LEFT JOIN member_memberships mm
        ON mm.plan_id = mp.id
     GROUP BY mp.id, mp.plan_name
     ORDER BY total_memberships DESC"
);

$planLabels = [];
$planCounts = [];

while ($row = $planResult->fetch_assoc()) {
    $planLabels[] = $row['plan_name'];
    $planCounts[] = (int)$row['total_memberships'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Reports | 4BROS AND 1BOSS</title>

<!-- SHARED ADMIN SIDEBAR -->
<link rel="stylesheet" href="sidebar.css">

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

/* MAIN CONTENT */
.container {
    max-width: 1250px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
    line-height: 1.6;
}

/* STATISTICS */
.stats {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-top: 25px;
}

.stat-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 22px;
}

.stat-card h3 {
    font-size: 14px;
    color: #9ca3af;
    margin: 0 0 12px;
}

.stat-card .value {
    font-size: 27px;
    font-weight: bold;
    color: #4ade80;
    overflow-wrap: anywhere;
}

/* CHART GRID */
.chart-grid {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(min(100%, 400px), 1fr)
        );
    gap: 20px;
    margin-top: 25px;
}

.chart-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    min-width: 0;
}

.chart-card h2 {
    margin-top: 0;
    font-size: 20px;
}

.chart-wrapper {
    position: relative;
    height: 320px;
}

/* EXPORT BUTTONS */
.export-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 20px 0 25px;
}

.export-btn {
    display: inline-block;
    padding: 12px 18px;
    background: #22c55e;
    color: #111827;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    transition: background 0.2s ease;
}

.export-btn:hover {
    background: #16a34a;
}

/* MOBILE */
@media (max-width: 600px) {

    header {
        flex-direction: column;
        padding: 18px;
        gap: 12px;
    }

    .chart-card {
        padding: 15px;
    }

    .chart-wrapper {
        height: 280px;
    }

    .export-btn {
        text-align: center;
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

    <!-- PERMANENT LEFT SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- REPORTS MAIN CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Reports and Analytics</h1>

            <p class="subtitle">
                Monitor gym memberships, payments,
                revenue, and overall performance.
            </p>

            <!-- EXPORT BUTTONS -->
            <div class="export-buttons">

                <a
                    href="export_report.php?type=members"
                    class="export-btn"
                >
                    Export Members
                </a>

                <a
                    href="export_report.php?type=memberships"
                    class="export-btn"
                >
                    Export Memberships
                </a>

                <a
                    href="export_report.php?type=payments"
                    class="export-btn"
                >
                    Export Payments
                </a>

            </div>

            <!-- STATISTICS -->
            <div class="stats">

                <div class="stat-card">
                    <h3>Total Members</h3>
                    <div class="value">
                        <?= number_format($totalMembers) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Total Staff</h3>
                    <div class="value">
                        <?= number_format($totalStaff) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Active Memberships</h3>
                    <div class="value">
                        <?= number_format($activeMemberships) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Pending Memberships</h3>
                    <div class="value">
                        <?= number_format($pendingMemberships) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <div class="value">
                        <?= number_format($pendingPayments) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Paid Payments</h3>
                    <div class="value">
                        <?= number_format($paidPayments) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Rejected Payments</h3>
                    <div class="value">
                        <?= number_format($rejectedPayments) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Total Confirmed Revenue</h3>
                    <div class="value">
                        ₱<?= number_format($totalRevenue, 2) ?>
                    </div>
                </div>

            </div>

            <!-- CHARTS -->
            <div class="chart-grid">

                <!-- MONTHLY REVENUE -->
                <div class="chart-card">

                    <h2>Monthly Revenue</h2>

                    <div class="chart-wrapper">
                        <canvas id="revenueChart"></canvas>
                    </div>

                </div>

                <!-- MEMBERSHIP PLAN POPULARITY -->
                <div class="chart-card">

                    <h2>Membership Plan Popularity</h2>

                    <div class="chart-wrapper">
                        <canvas id="membershipChart"></canvas>
                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<!-- CHART DATA AND JAVASCRIPT -->
<script>

// Monthly revenue data
const monthlyLabels = <?= json_encode(
    $monthlyLabels,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const monthlyRevenue = <?= json_encode(
    $monthlyRevenue,
    JSON_NUMERIC_CHECK
) ?>;

// Membership popularity data
const planLabels = <?= json_encode(
    $planLabels,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const planCounts = <?= json_encode(
    $planCounts,
    JSON_NUMERIC_CHECK
) ?>;

// MONTHLY REVENUE BAR CHART
new Chart(
    document.getElementById('revenueChart'),
    {
        type: 'bar',

        data: {
            labels: monthlyLabels,

            datasets: [{
                label: 'Revenue (₱)',
                data: monthlyRevenue,
                backgroundColor: '#22c55e',
                borderRadius: 6
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            },

            scales: {
                x: {
                    ticks: {
                        color: '#d1d5db'
                    },
                    grid: {
                        color: '#374151'
                    }
                },

                y: {
                    beginAtZero: true,

                    ticks: {
                        color: '#d1d5db'
                    },

                    grid: {
                        color: '#374151'
                    }
                }
            }
        }
    }
);

// MEMBERSHIP PLAN POPULARITY DOUGHNUT CHART
new Chart(
    document.getElementById('membershipChart'),
    {
        type: 'doughnut',

        data: {
            labels: planLabels,

            datasets: [{
                data: planCounts,

                backgroundColor: [
                    '#22c55e',
                    '#3b82f6',
                    '#f59e0b',
                    '#a855f7',
                    '#ef4444',
                    '#14b8a6'
                ]
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom',

                    labels: {
                        color: '#ffffff'
                    }
                }
            }
        }
    }
);

</script>

</body>
</html>
