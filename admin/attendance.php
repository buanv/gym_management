
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

// Total members currently inside
$insideQuery = $conn->query(
    "SELECT COUNT(DISTINCT member_id) AS total
     FROM attendance
     WHERE time_out IS NULL"
);

$currentlyInside = (int)(
    $insideQuery->fetch_assoc()['total'] ?? 0
);

// Today's attendance sessions
$todayQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM attendance
     WHERE DATE(time_in) = CURDATE()"
);

$todayAttendance = (int)(
    $todayQuery->fetch_assoc()['total'] ?? 0
);

// Total attendance records
$totalQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM attendance"
);

$totalAttendance = (int)(
    $totalQuery->fetch_assoc()['total'] ?? 0
);

// Completed attendance records
$completedQuery = $conn->query(
    "SELECT COUNT(*) AS total
     FROM attendance
     WHERE time_out IS NOT NULL"
);

$completedAttendance = (int)(
    $completedQuery->fetch_assoc()['total'] ?? 0
);

// Retrieve attendance records and member information
$stmt = $conn->prepare(
    "SELECT
        a.id,
        a.member_id,
        a.time_in,
        a.time_out,
        u.full_name,
        u.email
     FROM attendance a
     LEFT JOIN users u
        ON a.member_id = u.id
     ORDER BY a.time_in DESC, a.id DESC"
);

$stmt->execute();

$attendanceRecords = $stmt->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Attendance Monitoring | 4BROS AND 1BOSS</title>

<!-- SHARED ADMIN SIDEBAR -->
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

/* ATTENDANCE STATISTICS */
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
}

.stat-card .value {
    font-size: 30px;
    font-weight: bold;
    color: #4ade80;
}

/* ATTENDANCE HISTORY */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

/* FILTERS */
.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters input {
    padding: 12px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
}

.filters input:focus {
    outline: none;
    border-color: #22c55e;
}

.search-box {
    flex: 1;
    min-width: 220px;
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

/* ATTENDANCE STATUS */
.inside {
    color: #4ade80;
    font-weight: bold;
}

.outside {
    color: #9ca3af;
    font-weight: bold;
}

/* EMPTY TABLE */
.empty {
    text-align: center;
    padding: 25px;
    color: #9ca3af;
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

    <!-- PERMANENT LEFT SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- ATTENDANCE MONITORING CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Attendance Monitoring</h1>

            <p class="subtitle">
                Monitor Member time-in, time-out,
                and gym attendance history.
            </p>

            <!-- ATTENDANCE STATISTICS -->
            <div class="stats">

                <div class="stat-card">
                    <h3>Currently Inside</h3>

                    <div class="value">
                        <?= number_format($currentlyInside) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Today's Attendance</h3>

                    <div class="value">
                        <?= number_format($todayAttendance) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Total Attendance Records</h3>

                    <div class="value">
                        <?= number_format($totalAttendance) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <h3>Completed Sessions</h3>

                    <div class="value">
                        <?= number_format($completedAttendance) ?>
                    </div>
                </div>

            </div>

            <!-- ATTENDANCE HISTORY -->
            <div class="card">

                <h2>Attendance History</h2>

                <!-- SEARCH AND DATE FILTER -->
                <div class="filters">

                    <input
                        type="text"
                        id="attendanceSearch"
                        class="search-box"
                        placeholder="Search Member ID, name, email..."
                        oninput="filterAttendance()"
                    >

                    <input
                        type="date"
                        id="attendanceDate"
                        onchange="filterAttendance()"
                    >

                </div>

                <!-- ATTENDANCE TABLE -->
                <div class="table-container">

                    <table id="attendanceTable">

                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Member ID</th>
                                <th>Member Name</th>
                                <th>Email</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($attendanceRecords as $record): ?>

                            <tr
                                data-date="<?= escape(
                                    substr($record['time_in'], 0, 10)
                                ) ?>"
                            >

                                <td>
                                    <?= (int)$record['id'] ?>
                                </td>

                                <td>
                                    <?= (int)$record['member_id'] ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $record['full_name'] ?? 'Unknown'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $record['email'] ?? '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape($record['time_in']) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $record['time_out'] ?? '—'
                                    ) ?>
                                </td>

                                <td>

                                    <?php if ($record['time_out'] === null): ?>

                                        <span class="inside">
                                            Inside
                                        </span>

                                    <?php else: ?>

                                        <span class="outside">
                                            Timed Out
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($attendanceRecords)): ?>

                            <tr>
                                <td colspan="7" class="empty">
                                    No attendance records found.
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

<!-- SEARCH AND DATE FILTER -->
<script>
function filterAttendance() {

    const search = document
        .getElementById('attendanceSearch')
        .value
        .toLowerCase();

    const selectedDate = document
        .getElementById('attendanceDate')
        .value;

    const rows = document.querySelectorAll(
        '#attendanceTable tbody tr[data-date]'
    );

    rows.forEach(row => {

        const text = row.textContent.toLowerCase();

        const rowDate = row.dataset.date;

        const matchesSearch = text.includes(search);

        const matchesDate =
            selectedDate === '' ||
            rowDate === selectedDate;

        row.style.display =
            matchesSearch && matchesDate
                ? ''
                : 'none';

    });

}
</script>

</body>
</html>
