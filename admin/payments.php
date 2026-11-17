
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

// PAYMENT SUMMARY
$summary = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'rejected' => 0,
    'revenue' => 0
];

$summarySql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(
            SUM(payment_status = 'pending'),
            0
        ) AS pending,
        COALESCE(
            SUM(payment_status = 'paid'),
            0
        ) AS paid,
        COALESCE(
            SUM(payment_status = 'rejected'),
            0
        ) AS rejected,
        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'paid'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS revenue
    FROM payments
";

$result = $conn->query($summarySql);

$summary = $result->fetch_assoc();

// RETRIEVE ALL PAYMENT TRANSACTIONS
$sql = "
    SELECT
        p.id,
        p.amount,
        p.payment_method,
        p.payment_status,
        p.reference_number,
        p.created_at,
        p.verified_at,

        u.full_name AS member_name,
        u.email AS member_email,

        mp.plan_name,

        verifier.full_name AS verified_by_name

    FROM payments p

    LEFT JOIN member_memberships mm
        ON p.membership_id = mm.id

    LEFT JOIN users u
        ON mm.user_id = u.id

    LEFT JOIN membership_plans mp
        ON mm.plan_id = mp.id

    LEFT JOIN users verifier
        ON p.verified_by = verifier.id

    ORDER BY p.created_at DESC, p.id DESC
";

$result = $conn->query($sql);

$payments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Payment Management | Gym Admin</title>

<!-- SHARED ADMIN SIDEBAR CSS -->
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

/* MAIN CONTENT */
.container {
    max-width: 1250px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
}

/* PAYMENT STATISTICS */
.stats {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(190px, 1fr));
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
    font-size: 28px;
    font-weight: bold;
    color: #4ade80;
}

/* PAYMENT TABLE CARD */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

/* SEARCH */
.search-box {
    width: 100%;
    padding: 13px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 8px;
    color: white;
    font-size: 15px;
    margin-bottom: 20px;
}

.search-box:focus {
    outline: none;
    border-color: #22c55e;
}

/* TABLE */
.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
}

td {
    color: #d1d5db;
}

/* PAYMENT STATUS */
.status {
    font-weight: bold;
}

.paid {
    color: #4ade80;
}

.pending {
    color: #facc15;
}

.rejected {
    color: #f87171;
}

/* EMPTY TABLE */
.empty {
    color: #9ca3af;
    text-align: center;
    padding: 25px;
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

    .stat-card {
        padding: 18px;
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

    <!-- PERMANENT ADMIN SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- PAYMENT MANAGEMENT CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Payment Management</h1>

            <p class="subtitle">
                Monitor membership payments
                and Staff verification.
            </p>

            <!-- PAYMENT SUMMARY -->
            <div class="stats">

                <div class="stat-card">
                    <h3>Total Transactions</h3>

                    <div class="value">
                        <?= (int)$summary['total'] ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Pending Payments</h3>

                    <div class="value">
                        <?= (int)$summary['pending'] ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Paid Payments</h3>

                    <div class="value">
                        <?= (int)$summary['paid'] ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Rejected Payments</h3>

                    <div class="value">
                        <?= (int)$summary['rejected'] ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Total Confirmed Revenue</h3>

                    <div class="value">
                        ₱<?= number_format(
                            (float)$summary['revenue'],
                            2
                        ) ?>
                    </div>
                </div>

            </div>

            <!-- PAYMENT TRANSACTIONS -->
            <div class="card">

                <h2>Payment Transactions</h2>

                <!-- SEARCH BAR -->
                <input
                    type="text"
                    id="paymentSearch"
                    class="search-box"
                    placeholder="Search member, plan, reference, status..."
                    oninput="searchPayments()"
                >

                <!-- TABLE -->
                <div class="table-container">

                    <table id="paymentsTable">

                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Member</th>
                                <th>Membership Plan</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Verified At</th>
                                <th>Date Submitted</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>
                                    <?= (int)$payment['id'] ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $payment['member_name'] ?? 'Unknown'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $payment['plan_name'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    ₱<?= number_format(
                                        (float)$payment['amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        strtoupper(
                                            $payment['payment_method'] ?? ''
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $payment['reference_number'] ?: '—'
                                    ) ?>
                                </td>

                                <td>

                                    <span class="status <?= escape(
                                        $payment['payment_status']
                                    ) ?>">

                                        <?= escape(
                                            ucfirst(
                                                $payment['payment_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= escape(
                                        $payment['verified_by_name'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $payment['verified_at'] ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $payment['created_at']
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($payments)): ?>

                            <tr>
                                <td colspan="10" class="empty">
                                    No payment transactions found.
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

<!-- SEARCH FUNCTION -->
<script>
function searchPayments() {

    const search = document
        .getElementById('paymentSearch')
        .value
        .toLowerCase();

    const rows = document.querySelectorAll(
        '#paymentsTable tbody tr'
    );

    rows.forEach(row => {

        const text = row.textContent.toLowerCase();

        row.style.display = text.includes(search)
            ? ''
            : 'none';

    });

}
</script>

</body>
</html>
