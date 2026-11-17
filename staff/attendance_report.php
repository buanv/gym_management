
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Staff access only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Validate dates in YYYY-MM-DD format.
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

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$error = '';
$records = [];

if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    $startDate > $endDate
) {
    $error = 'Please select a valid date range.';
} else {

    $sql = "
        SELECT
            a.id,
            a.member_id,
            u.full_name,
            a.time_in,
            a.time_out
        FROM attendance a
        INNER JOIN users u
            ON a.member_id = u.id
        WHERE a.time_in >= ?
          AND a.time_in < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY a.time_in DESC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'ss',
        $startDate,
        $endDate
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    $stmt->close();
}

$totalVisits = count($records);
$completedVisits = 0;

foreach ($records as $record) {
    if ($record['time_out'] !== null) {
        $completedVisits++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Attendance Reports</title>

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
    max-width: 1200px;
    margin: auto;
    padding: 35px 20px;
}

.card {
    background: #1f2937;
    padding: 25px;
    border-radius: 12px;
    margin-top: 25px;
}

.filters {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.field label {
    display: block;
    margin-bottom: 8px;
}

input {
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 12px;
}

button, .export-btn {
    background: #22c55e;
    color: #111827;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.export-btn {
    margin-top: 15px;
}

.summary {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.summary-box {
    flex: 1;
    min-width: 180px;
    background: #273449;
    padding: 20px;
    border-radius: 10px;
}

.summary-box strong {
    display: block;
    color: #4ade80;
    font-size: 30px;
    margin-top: 10px;
}

.table-box {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    background: #273449;
    color: #4ade80;
}

.error {
    color: #f87171;
}

.empty {
    text-align: center;
    color: #9ca3af;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="attendance.php">Back to Attendance</a>
</header>

<div class="container">

    <h1>Attendance Reports</h1>

    <p>
        View attendance records within a selected date range.
    </p>

    <div class="card">

        <h2>Filter Attendance</h2>

        <form method="GET" class="filters">

            <div class="field">
                <label for="start_date">Start Date</label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= escape($startDate) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="end_date">End Date</label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= escape($endDate) ?>"
                    required
                >
            </div>

            <button type="submit">
                Generate Report
            </button>

        </form>

        <?php if ($error !== ''): ?>
            <p class="error"><?= escape($error) ?></p>
        <?php endif; ?>

    </div>

    <?php if ($error === ''): ?>

    <div class="card">

        <h2>Attendance Summary</h2>

        <div class="summary">

            <div class="summary-box">
                Total Visits
                <strong><?= $totalVisits ?></strong>
            </div>

            <div class="summary-box">
                Completed Visits
                <strong><?= $completedVisits ?></strong>
            </div>

        </div>

        <a
            class="export-btn"
            href="export_attendance.php?start_date=<?= urlencode($startDate) ?>&amp;end_date=<?= urlencode($endDate) ?>"
        >
            Export to CSV
        </a>

    </div>

    <div class="card">

        <h2>Attendance Records</h2>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Attendance ID</th>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($totalVisits > 0): ?>

                    <?php foreach ($records as $record): ?>

                        <tr>
                            <td><?= escape($record['id']) ?></td>
                            <td><?= escape($record['member_id']) ?></td>
                            <td><?= escape($record['full_name']) ?></td>
                            <td><?= escape($record['time_in']) ?></td>

                            <td>
                                <?= escape(
                                    $record['time_out'] ?? 'Not Yet'
                                ) ?>
                            </td>

                            <td>
                                <?= $record['time_out'] === null
                                    ? 'Inside Gym'
                                    : 'Completed' ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No attendance records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

    <?php endif; ?>

</div>

</body>
</html>
