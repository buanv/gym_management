
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// CSRF protection for payment actions.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only Staff and Admin can access this page.
if (
    !isset($_SESSION['user_id']) ||
    !in_array(
        $_SESSION['role'] ?? '',
        ['staff', 'admin'],
        true
    )
) {
    http_response_code(403);
    exit('Access denied. Staff or Admin only.');
}

// Confirm account is active and verified.
$user_id = (int) $_SESSION['user_id'];

$auth = $conn->prepare(
    "SELECT id FROM users
     WHERE id = ?
       AND role IN ('staff', 'admin')
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);

$auth->bind_param('i', $user_id);
$auth->execute();

if (!$auth->get_result()->fetch_assoc()) {
    http_response_code(403);
    exit('Account unavailable or unverified.');
}

// Get payment records and Member information.
$sql = "
    SELECT
        p.id AS payment_id,
        p.payment_method,
        p.amount,
        p.payment_status,
        p.reference_number,
        p.created_at,

        u.full_name,
        u.email,

        mp.plan_name

    FROM payments p

    INNER JOIN member_memberships mm
        ON p.membership_id = mm.id

    INNER JOIN users u
        ON mm.user_id = u.id

    INNER JOIN membership_plans mp
        ON mm.plan_id = mp.id

    ORDER BY
        CASE
            WHEN p.payment_status = 'pending' THEN 0
            ELSE 1
        END,
        p.created_at DESC,
        p.id DESC
";

$result = $conn->query($sql);

function e($value) {
    return htmlspecialchars(
        (string) $value,
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

<title>Staff Payment Management</title>

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

header {
    background: #1f2937;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

header h2 {
    margin: 0;
    color: #22c55e;
}

header a {
    color: #4ade80;
    text-decoration: none;
}

.container {
    max-width: 1250px;
    margin: 35px auto;
    padding: 20px;
}

h1 {
    margin-bottom: 10px;
}

.subtitle {
    color: #9ca3af;
    margin-bottom: 25px;
}

.table-wrapper {
    background: #1f2937;
    padding: 20px;
    border-radius: 12px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
    background: #263244;
}

td {
    color: #e5e7eb;
}

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.pending {
    background: #78350f;
    color: #fcd34d;
}

.paid {
    background: #14532d;
    color: #86efac;
}

.rejected {
    background: #7f1d1d;
    color: #fca5a5;
}

.empty {
    text-align: center;
    color: #9ca3af;
    padding: 30px;
}

@media (max-width: 600px) {
    header {
        flex-wrap: wrap;
    }

    .container {
        padding: 12px;
    }
}
.payment-actions {
    display: flex;
    gap: 8px;
}

.approve-btn,
.reject-btn {
    padding: 9px 14px;
    border: none;
    border-radius: 6px;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.approve-btn {
    background: #16a34a;
}

.approve-btn:hover {
    background: #15803d;
}

.reject-btn {
    background: #dc2626;
}

.reject-btn:hover {
    background: #b91c1c;
}

.completed-label {
    color: #9ca3af;
    font-size: 13px;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>

    <a href="dashboard.php">
        Back to Dashboard
    </a>
</header>

<div class="container">

    <h1>Payment Management</h1>

    <p class="subtitle">
        Review Cash and GCash payment requests
        submitted by gym Members.
    </p>

    <div class="table-wrapper">

        <table>

            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Member</th>
                    <th>Membership Plan</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    $status = $row['payment_status'];
                    ?>

                    <tr>

                        <td>
                            #<?= (int) $row['payment_id'] ?>
                        </td>

                        <td>
                            <strong>
                                <?= e($row['full_name']) ?>
                            </strong>

                            <div style="color:#9ca3af;font-size:12px;">
                                <?= e($row['email']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($row['plan_name']) ?>
                        </td>

                        <td>
                            <?= e(strtoupper(
                                $row['payment_method']
                            )) ?>
                        </td>

                        <td>
                            ₱<?= number_format(
                                (float) $row['amount'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $row['reference_number']
                                ?: 'N/A'
                            ) ?>
                        </td>

                        <td>
                            <span class="status <?= e($status) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($row['created_at']) ?>
                        </td>


                        <td>
                            <?php if ($row['payment_status'] === 'pending'): ?>

                            <form
                                action="process_payment.php"
                                method="POST"
                                class="payment-actions"
                            >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <input
                                type="hidden"
                                name="payment_id"
                                value="<?= (int) $row['payment_id'] ?>"
                            >

                            <button
                                type="submit"
                                name="action"
                                value="approve"
                                class="approve-btn"
                                onclick="return confirm('Have you verified that the gym owner received this payment?')"
                                >
                                Approve
                            </button>

                            <button
                            type="submit"
                            name="action"
                            value="reject"
                            class="reject-btn"
                            onclick="return confirm('Are you sure you want to reject this payment?')"
                            >
                            Reject
                            </button>
                </form>

    <?php else: ?>

        <span class="completed-label">
            Processed
        </span>

    <?php endif; ?>
</td>


                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9" class="empty">
                        No payment requests found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
