
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

// Retrieve the 200 most recent activity logs
$stmt = $conn->prepare(
    "SELECT
        al.id,
        al.user_id,
        al.action,
        al.created_at,
        u.full_name,
        u.email,
        u.role
     FROM activity_logs al
     LEFT JOIN users u
        ON al.user_id = u.id
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT 200"
);

$stmt->execute();

$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Activity Logs | 4BROS AND 1BOSS</title>

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
    line-height: 1.6;
}

/* ACTIVITY LOGS CARD */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

/* SEARCH BAR */
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
}

th, td {
    padding: 14px;
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

/* ACCOUNT EMAIL */
.account-email {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 5px;
}

/* ROLE BADGES */
.role {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    background: #374151;
}

.role.admin {
    color: #c4b5fd;
}

.role.staff {
    color: #4ade80;
}

.role.member {
    color: #93c5fd;
}

/* EMPTY TABLE */
.empty {
    text-align: center;
    color: #9ca3af;
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

    th, td {
        padding: 12px;
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

    <!-- ACTIVITY LOGS CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Activity Logs</h1>

            <p class="subtitle">
                Monitor recent system activities performed
                by Admin, Staff, and Members.
            </p>

            <!-- ACTIVITY LOGS CARD -->
            <div class="card">

                <h2>
                    Recent Activities
                </h2>

                <!-- SEARCH BAR -->
                <input
                    type="text"
                    id="logSearch"
                    class="search-box"
                    placeholder="Search name, role, or action..."
                    oninput="searchLogs()"
                >

                <!-- ACTIVITY LOGS TABLE -->
                <div class="table-container">

                    <table id="logsTable">

                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($logs as $log): ?>

                            <tr>

                                <td>
                                    <?= (int)$log['id'] ?>
                                </td>

                                <td>

                                    <?= escape(
                                        $log['full_name'] ?? 'Unknown Account'
                                    ) ?>

                                    <div class="account-email">
                                        <?= escape(
                                            $log['email'] ?? ''
                                        ) ?>
                                    </div>

                                </td>

                                <td>

                                    <span class="role <?= escape(
                                        $log['role'] ?? ''
                                    ) ?>">

                                        <?= escape(
                                            ucfirst(
                                                $log['role'] ?? 'Unknown'
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= escape($log['action']) ?>
                                </td>

                                <td>
                                    <?= escape($log['created_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($logs)): ?>

                            <tr>
                                <td colspan="5" class="empty">
                                    No activity logs found.
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
function searchLogs() {

    const search = document
        .getElementById('logSearch')
        .value
        .toLowerCase();

    const rows = document.querySelectorAll(
        '#logsTable tbody tr'
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
