
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

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Escape HTML output
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Validate date format
function validDate($date) {
    if (!is_string($date)) {
        return false;
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

// Default date range: last 7 calendar days
$today = new DateTimeImmutable('today');

$startDate = $_GET['start_date']
    ?? $today->modify('-6 days')->format('Y-m-d');

$endDate = $_GET['end_date']
    ?? $today->format('Y-m-d');

// Validate selected dates
if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    $startDate > $endDate
) {
    http_response_code(400);
    exit('Invalid date range.');
}

// Maximum range: 366 calendar days
$startObject = new DateTimeImmutable($startDate);
$endObject = new DateTimeImmutable($endDate);

if ($startObject->diff($endObject)->days > 365) {
    http_response_code(400);
    exit('Please select a range of up to 366 days.');
}

// Include both selected dates
$startDateTime = $startDate . ' 00:00:00';

$endExclusive = $endObject
    ->modify('+1 day')
    ->format('Y-m-d 00:00:00');

// ======================================
// 1. COMPLETED HOURS AND AVERAGE SESSION
// ======================================

$summaryStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS completed_sessions,

        COALESCE(
            SUM(
                TIMESTAMPDIFF(
                    SECOND,
                    time_in,
                    time_out
                )
            ) / 3600,
            0
        ) AS total_hours,

        COALESCE(
            AVG(
                TIMESTAMPDIFF(
                    SECOND,
                    time_in,
                    time_out
                )
            ) / 3600,
            0
        ) AS average_hours

     FROM staff_attendance

     WHERE time_in >= ?
       AND time_in < ?
       AND time_out IS NOT NULL
       AND time_out >= time_in"
);

$summaryStmt->bind_param(
    'ss',
    $startDateTime,
    $endExclusive
);

$summaryStmt->execute();

$summary = $summaryStmt
    ->get_result()
    ->fetch_assoc();

$totalHours = (float)$summary['total_hours'];
$averageHours = (float)$summary['average_hours'];
$completedSessions = (int)$summary['completed_sessions'];

$summaryStmt->close();

// ======================================
// 2. CURRENTLY ON DUTY
// ======================================

$onDutyResult = $conn->query(
    "SELECT COUNT(DISTINCT staff_id) AS total
     FROM staff_attendance
     WHERE time_out IS NULL"
);

$onDuty = (int)(
    $onDutyResult->fetch_assoc()['total'] ?? 0
);

// ======================================
// 3. STAFF ATTENDED TODAY
// ======================================

$todayResult = $conn->query(
    "SELECT COUNT(DISTINCT staff_id) AS total
     FROM staff_attendance
     WHERE time_in >= CURDATE()
       AND time_in < CURDATE() + INTERVAL 1 DAY"
);

$attendedToday = (int)(
    $todayResult->fetch_assoc()['total'] ?? 0
);

// ======================================
// 4. DAILY ATTENDANCE CHART
// ======================================

$dailyStmt = $conn->prepare(
    "SELECT
        DATE(time_in) AS attendance_date,
        COUNT(DISTINCT staff_id) AS staff_count

     FROM staff_attendance

     WHERE time_in >= ?
       AND time_in < ?

     GROUP BY DATE(time_in)
     ORDER BY attendance_date ASC"
);

$dailyStmt->bind_param(
    'ss',
    $startDateTime,
    $endExclusive
);

$dailyStmt->execute();

$dailyResult = $dailyStmt->get_result();

$dailyCounts = [];

while ($row = $dailyResult->fetch_assoc()) {
    $dailyCounts[$row['attendance_date']] =
        (int)$row['staff_count'];
}

$dailyStmt->close();

// Include dates with zero attendance
$dailyLabels = [];
$dailyValues = [];

$period = new DatePeriod(
    $startObject,
    new DateInterval('P1D'),
    new DateTimeImmutable($endExclusive)
);

foreach ($period as $date) {
    $key = $date->format('Y-m-d');

    $dailyLabels[] = $date->format('M d, Y');
    $dailyValues[] = $dailyCounts[$key] ?? 0;
}

// ======================================
// 5. INDIVIDUAL STAFF SUMMARY
// ======================================

$staffStmt = $conn->prepare(
    "SELECT
        u.id,
        u.full_name,
        u.email,

        COUNT(sa.id) AS total_sessions,

        COUNT(DISTINCT DATE(sa.time_in))
            AS days_attended,

        COALESCE(
            SUM(
                CASE
                    WHEN sa.time_out IS NOT NULL
                     AND sa.time_out >= sa.time_in

                    THEN TIMESTAMPDIFF(
                        SECOND,
                        sa.time_in,
                        sa.time_out
                    )

                    ELSE 0
                END
            ) / 3600,
            0
        ) AS total_hours

     FROM users u

     LEFT JOIN staff_attendance sa
        ON sa.staff_id = u.id
       AND sa.time_in >= ?
       AND sa.time_in < ?

     WHERE u.role = 'staff'

     GROUP BY
        u.id,
        u.full_name,
        u.email

     ORDER BY
        total_hours DESC,
        u.full_name ASC"
);

$staffStmt->bind_param(
    'ss',
    $startDateTime,
    $endExclusive
);

$staffStmt->execute();

$staffRecords = $staffStmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$staffStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Staff Attendance Analytics | 4BROS AND 1BOSS</title>

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
    max-width: 1200px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
    line-height: 1.6;
}

/* STATISTICS */
.cards {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 10px;
    padding: 25px;
}

.card h3 {
    font-size: 15px;
    color: #9ca3af;
    margin-top: 0;
}

.card h1 {
    color: #22c55e;
    font-size: 30px;
    overflow-wrap: anywhere;
}

/* CHART AND TABLE SECTIONS */
.section {
    background: #1f2937;
    border: 1px solid #374151;
    margin-top: 25px;
    padding: 25px;
    border-radius: 10px;
}

.chart-container {
    position: relative;
    height: 320px;
}

/* STAFF SEARCH */
.search-box {
    width: 100%;
    padding: 12px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 8px;
    color: white;
    margin-bottom: 20px;
}

.search-box:focus {
    outline: none;
    border-color: #22c55e;
}

/* TABLE */
.table-wrapper {
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

/* DATE FILTER */
.date-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 15px;
    background: #1f2937;
    border: 1px solid #374151;
    padding: 20px;
    border-radius: 10px;
    margin: 25px 0;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-field label {
    color: #d1d5db;
    font-size: 14px;
}

.filter-field input {
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 12px;
    font-size: 14px;
}

.filter-field input:focus {
    outline: none;
    border-color: #22c55e;
}

/* FILTER BUTTON */
.filter-btn {
    background: #22c55e;
    color: #111827;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.filter-btn:hover {
    background: #16a34a;
}

/* RESET BUTTON */
.reset-btn {
    background: #374151;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}

.reset-btn:hover {
    background: #4b5563;
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

    .chart-container {
        height: 280px;
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

    <!-- STAFF ANALYTICS CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Staff Attendance Analytics</h1>

            <p class="subtitle">
                Monitor Staff attendance, working hours,
                and daily attendance trends.
            </p>

            <!-- DATE RANGE FILTER -->
            <form method="GET" class="date-filter">

                <div class="filter-field">

                    <label for="start_date">
                        Start Date
                    </label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?= e($startDate) ?>"
                        required
                    >

                </div>

                <div class="filter-field">

                    <label for="end_date">
                        End Date
                    </label>

                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="<?= e($endDate) ?>"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="filter-btn"
                >
                    Apply Filter
                </button>

                <a
                    href="staff_analytics.php"
                    class="reset-btn"
                >
                    Reset
                </a>

            </form>

            <p class="subtitle">
                Showing attendance from
                <strong><?= e($startDate) ?></strong>
                to
                <strong><?= e($endDate) ?></strong>.
            </p>

            <!-- SUMMARY CARDS -->
            <div class="cards">

                <div class="card">

                    <h3>
                        Completed Hours (Selected Period)
                    </h3>

                    <h1>
                        <?= number_format($totalHours, 2) ?> hrs
                    </h1>

                </div>

                <div class="card">

                    <h3>
                        Average Session Hours (Selected Period)
                    </h3>

                    <h1>
                        <?= number_format($averageHours, 2) ?> hrs
                    </h1>

                </div>

                <div class="card">

                    <h3>Currently On Duty</h3>

                    <h1>
                        <?= number_format($onDuty) ?>
                    </h1>

                </div>

                <div class="card">

                    <h3>Staff Attended Today</h3>

                    <h1>
                        <?= number_format($attendedToday) ?>
                    </h1>

                </div>

            </div>

            <!-- DAILY ATTENDANCE CHART -->
            <div class="section">

                <h2>
                    Daily Staff Attendance — Selected Period
                </h2>

                <div class="chart-container">
                    <canvas id="dailyAttendanceChart"></canvas>
                </div>

            </div>

            <!-- INDIVIDUAL STAFF SUMMARY -->
            <div class="section">

                <h2>
                    Individual Staff Attendance Summary
                </h2>

                <!-- SEARCH BAR -->
                <input
                    type="text"
                    id="staffSearch"
                    class="search-box"
                    placeholder="Search Staff name or email..."
                >

                <!-- STAFF TABLE -->
                <div class="table-wrapper">

                    <table id="staffTable">

                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Staff Name</th>
                                <th>Email</th>
                                <th>Attendance Sessions</th>
                                <th>Days Attended</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($staffRecords as $staff): ?>

                            <tr>

                                <td>
                                    <?= (int)$staff['id'] ?>
                                </td>

                                <td>
                                    <?= e($staff['full_name']) ?>
                                </td>

                                <td>
                                    <?= e($staff['email']) ?>
                                </td>

                                <td>
                                    <?= (int)$staff['total_sessions'] ?>
                                </td>

                                <td>
                                    <?= (int)$staff['days_attended'] ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$staff['total_hours'],
                                        2
                                    ) ?> hrs
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$staffRecords): ?>

                            <tr>
                                <td colspan="6">
                                    No Staff accounts found.
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

<!-- CHART AND SEARCH JAVASCRIPT -->
<script>

// Daily attendance chart data
const dailyLabels = <?= json_encode(
    $dailyLabels,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const dailyValues = <?= json_encode(
    $dailyValues,
    JSON_NUMERIC_CHECK
) ?>;

// DAILY ATTENDANCE BAR CHART
if (typeof Chart !== 'undefined') {

    new Chart(
        document.getElementById('dailyAttendanceChart'),
        {
            type: 'bar',

            data: {
                labels: dailyLabels,

                datasets: [{
                    label: 'Staff Attended',
                    data: dailyValues,
                    backgroundColor: '#22c55e',
                    borderRadius: 6
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            precision: 0,
                            color: '#d1d5db'
                        },

                        grid: {
                            color: '#374151'
                        }
                    },

                    x: {
                        ticks: {
                            color: '#d1d5db'
                        },

                        grid: {
                            color: '#374151'
                        }
                    }
                },

                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                }
            }
        }
    );

}

// SEARCH STAFF RECORDS
document.getElementById('staffSearch')
    .addEventListener('input', function () {

        const search = this.value.toLowerCase();

        const rows = document.querySelectorAll(
            '#staffTable tbody tr'
        );

        rows.forEach(row => {

            const matches = row.textContent
                .toLowerCase()
                .includes(search);

            row.style.display = matches ? '' : 'none';

        });

    });

// VALIDATE DATE RANGE BEFORE SUBMITTING
document.querySelector('.date-filter')
    .addEventListener('submit', function (event) {

        const start = document
            .getElementById('start_date')
            .value;

        const end = document
            .getElementById('end_date')
            .value;

        if (start > end) {

            event.preventDefault();

            alert(
                'Start Date cannot be after End Date.'
            );

        }

    });

</script>

</body>
</html>
