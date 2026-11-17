
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
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Count staff currently on duty
$onDutyResult = $conn->query(
    "SELECT COUNT(DISTINCT staff_id) AS total
     FROM staff_attendance
     WHERE time_out IS NULL"
);

$onDuty = (int)(
    $onDutyResult->fetch_assoc()['total'] ?? 0
);

// Count unique staff who attended today
$todayResult = $conn->query(
    "SELECT COUNT(DISTINCT staff_id) AS total
     FROM staff_attendance
     WHERE DATE(time_in) = CURDATE()"
);

$todayAttendance = (int)(
    $todayResult->fetch_assoc()['total'] ?? 0
);

// Count completed attendance sessions
$completedResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM staff_attendance
     WHERE time_out IS NOT NULL"
);

$completed = (int)(
    $completedResult->fetch_assoc()['total'] ?? 0
);

// Count all attendance sessions
$totalResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM staff_attendance"
);

$totalRecords = (int)(
    $totalResult->fetch_assoc()['total'] ?? 0
);

// Retrieve recent staff attendance records
$stmt = $conn->prepare(
    "SELECT
        sa.id,
        sa.staff_id,
        sa.time_in,
        sa.time_out,
        u.full_name,
        u.email
     FROM staff_attendance sa
     LEFT JOIN users u
        ON sa.staff_id = u.id
     ORDER BY sa.time_in DESC, sa.id DESC
     LIMIT 200"
);

$stmt->execute();

$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Attendance | Gym Admin</title>

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
    max-width: 1200px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
    line-height: 1.6;
}

/* ATTENDANCE STATISTICS */
.cards {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.card {
    background: #1f2937;
    border: 1px solid #374151;
    padding: 25px;
    border-radius: 10px;
}

.card h3 {
    color: #9ca3af;
    font-size: 15px;
    margin-top: 0;
}

.card h1 {
    color: #22c55e;
    font-size: 36px;
    margin-bottom: 0;
}

/* ATTENDANCE HISTORY */
.section {
    background: #1f2937;
    border: 1px solid #374151;
    margin-top: 30px;
    padding: 25px;
    border-radius: 10px;
}

/* SEARCH AND DATE FILTER */
.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters input {
    background: #111827;
    color: white;
    padding: 12px;
    border: 1px solid #374151;
    border-radius: 8px;
}

.filters input:focus {
    outline: none;
    border-color: #22c55e;
}

.search {
    flex: 1;
    min-width: 220px;
}

/* TABLE */
.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th, td {
    text-align: left;
    padding: 14px;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
}

td {
    color: #d1d5db;
}

/* ATTENDANCE STATUS */
.status {
    font-weight: bold;
}

.on-duty {
    color: #4ade80;
}

.completed {
    color: #9ca3af;
}

/* EMPTY TABLE */
.empty {
    text-align: center;
    color: #9ca3af;
    padding: 25px;
}

/* EXPORT BUTTON */
.export-btn {
    display: inline-block;
    background: #22c55e;
    color: #111827;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    margin: 12px 0;
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
        gap: 10px;
    }

    .section {
        padding: 15px;
    }

    .card {
        padding: 18px;
    }

    .card h1 {
        font-size: 30px;
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

    <!-- STAFF ATTENDANCE CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Staff Attendance Monitoring</h1>

            <!-- EXPORT STAFF ATTENDANCE -->
            <a
                href="export_staff_attendance.php"
                class="export-btn"
            >
                Export Staff Attendance
            </a>

            <p class="subtitle">
                Monitor Staff Time-In, Time-Out,
                and daily attendance history.
            </p>

            <!-- ATTENDANCE STATISTICS -->
            <div class="cards">

                <div class="card">
                    <h3>Currently On Duty</h3>

                    <h1>
                        <?= number_format($onDuty) ?>
                    </h1>
                </div>

                <div class="card">
                    <h3>Staff Attended Today</h3>

                    <h1>
                        <?= number_format($todayAttendance) ?>
                    </h1>
                </div>

                <div class="card">
                    <h3>Completed Sessions</h3>

                    <h1>
                        <?= number_format($completed) ?>
                    </h1>
                </div>

                <div class="card">
                    <h3>Total Attendance Records</h3>

                    <h1>
                        <?= number_format($totalRecords) ?>
                    </h1>
                </div>

            </div>

            <!-- STAFF ATTENDANCE HISTORY -->
            <div class="section">

                <h2>Staff Attendance History</h2>

                <!-- SEARCH AND DATE FILTER -->
                <div class="filters">

                    <input
                        type="text"
                        id="searchInput"
                        class="search"
                        placeholder="Search Staff name or email..."
                        oninput="filterAttendance()"
                    >

                    <input
                        type="date"
                        id="dateInput"
                        onchange="filterAttendance()"
                    >

                </div>

                <!-- ATTENDANCE TABLE -->
                <div class="table-wrapper">

                    <table id="attendanceTable">

                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Staff ID</th>
                                <th>Staff Name</th>
                                <th>Email</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($records as $record): ?>

                            <tr
                                data-date="<?= e(
                                    substr($record['time_in'], 0, 10)
                                ) ?>"
                            >

                                <td>
                                    <?= (int)$record['id'] ?>
                                </td>

                                <td>
                                    <?= (int)$record['staff_id'] ?>
                                </td>

                                <td>
                                    <?= e(
                                        $record['full_name'] ?? 'Unknown'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $record['email'] ?? '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($record['time_in']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $record['time_out'] ?? '—'
                                    ) ?>
                                </td>

                                <td>

                                    <?php if ($record['time_out'] === null): ?>

                                        <span class="status on-duty">
                                            On Duty
                                        </span>

                                    <?php else: ?>

                                        <span class="status completed">
                                            Completed
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($records)): ?>

                            <tr>
                                <td colspan="7" class="empty">
                                    No Staff attendance records yet.
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
        .getElementById('searchInput')
        .value
        .toLowerCase();

    const date = document
        .getElementById('dateInput')
        .value;

    const rows = document.querySelectorAll(
        '#attendanceTable tbody tr[data-date]'
    );

    rows.forEach(row => {

        const matchesSearch = row.textContent
            .toLowerCase()
            .includes(search);

        const matchesDate =
            date === '' ||
            row.dataset.date === date;

        row.style.display =
            matchesSearch && matchesDate
                ? ''
                : 'none';

    });

}
</script>

</body>
</html>
