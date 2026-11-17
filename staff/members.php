
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only logged-in Staff can access this page.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

// Retrieve registered Members and their latest membership.
$sql = "
    SELECT
        u.id AS member_id,
        u.full_name,
        u.email,
        mp.plan_name,
        mm.payment_status,
        mm.membership_status,
        mm.start_date,
        mm.end_date
    FROM users u

    LEFT JOIN member_memberships mm
        ON mm.id = (
            SELECT mm2.id
            FROM member_memberships mm2
            WHERE mm2.user_id = u.id
            ORDER BY mm2.id DESC
            LIMIT 1
        )

    LEFT JOIN membership_plans mp
        ON mp.id = mm.plan_id

    WHERE u.role = 'member'

    ORDER BY u.id DESC
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>View Members | Gym Management</title>

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
    padding: 35px 20px;
    max-width: 1400px;
    margin: auto;
}

h1 {
    margin-bottom: 10px;
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
    border-collapse: collapse;
    min-width: 850px;
}

th {
    background: #273449;
    color: #4ade80;
    text-align: left;
    padding: 15px;
}

td {
    padding: 15px;
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
    color: #9ca3af;
    padding: 25px;
}
.search-box {
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    max-width: 450px;
    padding: 13px 15px;
    background: #1f2937;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
}

.search-box input:focus {
    border-color: #22c55e;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="container">

    <h1>Registered Gym Members</h1>

    <p class="subtitle">
        View registered members and their membership details.
    </p>

    <div class="search-box">
    <input
        type="text"
        id="memberSearch"
        placeholder="Search by Member ID, Name, or Email..."
        onkeyup="searchMembers()"
    >
    </div>

    <div class="table-box">

        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Membership Plan</th>
                    <th>Payment Status</th>
                    <th>Membership Status</th>
                    <th>Start Date</th>
                    <th>Expiration Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($member = $result->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <?= escape($member['member_id']) ?>
                        </td>

                        <td>
                            <?= escape($member['full_name']) ?>
                        </td>

                        <td>
                            <?= escape($member['email']) ?>
                        </td>

                        <td>
                            <?= escape($member['plan_name'] ?? 'No Plan') ?>
                        </td>

                        <td>
                            <span class="status <?= escape($member['payment_status']) ?>">
                                <?= escape($member['payment_status'] ?? 'None') ?>
                            </span>
                        </td>

                        <td>
                            <span class="status <?= escape($member['membership_status']) ?>">
                                <?= escape($member['membership_status'] ?? 'None') ?>
                            </span>
                        </td>

                        <td>
                            <?= escape($member['start_date'] ?? 'Not Started') ?>
                        </td>

                        <td>
                            <?= escape($member['end_date'] ?? 'Not Available') ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="empty">
                        No registered members found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>
<script>
function searchMembers() {
    const search = document
        .getElementById("memberSearch")
        .value
        .toLowerCase()
        .trim();

    const rows = document.querySelectorAll(
        ".table-box tbody tr"
    );

    rows.forEach(function(row) {
        const cells = row.querySelectorAll("td");

        if (cells.length < 3) {
            return;
        }

        const memberId = cells[0].textContent.toLowerCase();
        const fullName = cells[1].textContent.toLowerCase();
        const email = cells[2].textContent.toLowerCase();

        const match =
            memberId.includes(search) ||
            fullName.includes(search) ||
            email.includes(search);

        row.style.display = match ? "" : "none";
    });
}
</script>
</body>
</html>
