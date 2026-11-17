
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Allow staff accounts only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header("Location: ../login.php");
    exit;
}

// Verify staff account is active and verified
$user_id = (int) $_SESSION['user_id'];

$auth = $conn->prepare(
    "SELECT full_name
     FROM users
     WHERE id = ?
       AND role = 'staff'
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);

$auth->bind_param("i", $user_id);
$auth->execute();

$staff = $auth->get_result()->fetch_assoc();
$auth->close();

if (!$staff) {
    http_response_code(403);
    exit("Staff account unavailable or unverified.");
}

// Get staff name
$staffName = $staff['full_name'];

// Count registered members
$memberQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'member'"
);

$totalMembers = (int) $memberQuery
    ->fetch_assoc()['total'];

$memberQuery->free();

// Count active membership plans
$planQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM membership_plans
     WHERE status = 'active'"
);

$totalPlans = (int) $planQuery
    ->fetch_assoc()['total'];

$planQuery->free();

// Count submitted payments awaiting confirmation
$pendingStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM payments
     WHERE payment_status = 'pending'"
);

$pendingStmt->execute();

$pendingPaymentCount = (int) $pendingStmt
    ->get_result()
    ->fetch_assoc()['total'];

$pendingStmt->close();

// Escape HTML output
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
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

<title>Staff Dashboard | 4BROS AND 1BOSS</title>

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
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

header h2 {
    color: #22c55e;
    margin: 0;
    font-size: 23px;
}

/* LOGOUT */
.logout {
    background: #ef4444;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 14px;
}

.logout:hover {
    background: #dc2626;
}

/* DASHBOARD LAYOUT */
.dashboard-layout {
    display: flex;
    min-height: calc(100vh - 82px);
}

/* LEFT SIDEBAR */
.sidebar {
    width: 260px;
    flex-shrink: 0;

    background: #1f2937;
    padding: 30px 18px;

    border-right: 1px solid #374151;
}

.sidebar h3 {
    color: #22c55e;
    font-size: 18px;
    margin: 0 0 25px;
    text-align: center;
}

/* SIDEBAR NAVIGATION */
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* SIDEBAR BUTTONS */
.sidebar-nav a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;

    width: 100%;
    padding: 15px 18px;

    background: #374151;
    color: white;

    text-decoration: none;
    font-size: 14px;
    border-radius: 8px;

    transition: background 0.3s ease,
                color 0.3s ease;
}

.sidebar-nav a:hover {
    background: #22c55e;
    color: #111827;
}

/* ACTIVE PAGE */
.sidebar-nav a.active {
    background: #16a34a;
    color: white;
    font-weight: bold;
}

/* PAYMENT NOTIFICATION BADGE */
.payment-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 24px;
    height: 24px;
    padding: 0 7px;

    background: #dc2626;
    color: #ffffff;

    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
    flex-shrink: 0;
}

/* MAIN CONTENT */
.main-content {
    flex: 1;
    min-width: 0;
    padding: 40px;
}

/* INNER CONTAINER */
.container {
    max-width: 1100px;
    margin: 0 auto;
}

/* PAGE TITLE */
.page-title {
    font-size: 30px;
    margin: 0 0 18px;
}

.welcome {
    font-size: 15px;
    color: #d1d5db;
    margin-bottom: 25px;
}

/* STATISTICS CARDS */
.cards {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;
    margin-top: 25px;
}

/* STATISTICS CARD */
.card {
    background: #1f2937;
    padding: 30px;
    border-radius: 10px;

    border: 1px solid #374151;

    transition: transform 0.3s ease,
                box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.card h3 {
    color: #9ca3af;
    font-size: 17px;
    margin: 0 0 25px;
}

.card h1 {
    color: #22c55e;
    font-size: 36px;
    margin: 0;
}

/* WELCOME SECTION */
.section {
    background: #1f2937;
    margin-top: 30px;
    padding: 25px;
    border-radius: 10px;
}

.section h2 {
    margin-top: 0;
}

.section p {
    color: #9ca3af;
    line-height: 1.7;
}

/* TABLET */
@media (max-width: 900px) {

    .sidebar {
        width: 220px;
    }

    .main-content {
        padding: 25px;
    }
}

/* MOBILE */
@media (max-width: 700px) {

    header {
        padding: 15px;
    }

    header h2 {
        font-size: 18px;
    }

    .dashboard-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        padding: 20px;
        border-right: none;
        border-bottom: 1px solid #374151;
    }

    .sidebar h3 {
        text-align: left;
    }

    .sidebar-nav {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .sidebar-nav a {
        padding: 12px;
        font-size: 13px;
    }

    .main-content {
        padding: 20px 15px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .page-title {
        font-size: 25px;
    }
}

@media (max-width: 400px) {

    .sidebar-nav {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<!-- TOP HEADER -->
<header>

    <h2>4BROS AND 1BOSS</h2>

    <a href="../logout.php" class="logout">
        Logout
    </a>

</header>

<!-- DASHBOARD LAYOUT -->
<div class="dashboard-layout">

    <!-- LEFT SIDEBAR -->
    <aside class="sidebar">

        <h3>Staff Management Tools</h3>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="active">
                Dashboard
            </a>

            <a href="members.php">
                View Members
            </a>

            <a href="memberships.php">
                Memberships
            </a>

            <!-- PAYMENT MANAGEMENT WITH NOTIFICATION -->
            <a href="payments.php">

                <span>
                    Payment Management
                </span>

                <?php if ($pendingPaymentCount > 0): ?>

                    <span
                        class="payment-badge"
                        title="<?= $pendingPaymentCount ?> pending payments"
                        aria-label="<?= $pendingPaymentCount ?> pending payments"
                    >
                        <?= $pendingPaymentCount > 99
                            ? '99+'
                            : $pendingPaymentCount ?>
                    </span>

                <?php endif; ?>

            </a>

            <a href="attendance.php">
                Attendance
            </a>

            <a href="my_attendance.php">
                My Attendance
            </a>

        </nav>

    </aside>

    <!-- RIGHT MAIN CONTENT -->
    <main class="main-content">

        <div class="container">

            <!-- DASHBOARD TITLE -->
            <h1 class="page-title">
                Staff Dashboard
            </h1>

            <p class="welcome">
                Welcome, <?= e($staffName) ?>!
            </p>

            <!-- STATISTICS -->
            <div class="cards">

                <!-- TOTAL MEMBERS -->
                <div class="card">

                    <h3>Total Members</h3>

                    <h1>
                        <?= $totalMembers ?>
                    </h1>

                </div>

                <!-- AVAILABLE PLANS -->
                <div class="card">

                    <h3>
                        Available Membership Plans
                    </h3>

                    <h1>
                        <?= $totalPlans ?>
                    </h1>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>
