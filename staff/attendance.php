
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['attendance_csrf'])) {
    $_SESSION['attendance_csrf'] = bin2hex(random_bytes(32));
}

$attendanceMessage = $_SESSION['attendance_message'] ?? '';
unset($_SESSION['attendance_message']);


// Allow Staff accounts only.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

// Retrieve attendance history.
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
    ORDER BY a.time_in DESC
    LIMIT 100
";

$result = $conn->query($sql);


// Retrieve members currently inside the gym.
$insideSql = "
    SELECT
        a.id,
        a.member_id,
        u.full_name,
        a.time_in
    FROM attendance a
    INNER JOIN users u
        ON a.member_id = u.id
    WHERE a.time_out IS NULL
    ORDER BY a.time_in DESC
";

$insideResult = $conn->query($insideSql);
$insideCount = $insideResult->num_rows;


// Total attendance visits started today.
$todaySql = "
    SELECT COUNT(*) AS total
    FROM attendance
    WHERE DATE(time_in) = CURDATE()
";

$todayResult = $conn->query($todaySql);
$todayVisits = (int) $todayResult->fetch_assoc()['total'];

// Completed attendance visits started today.
$completedSql = "
    SELECT COUNT(*) AS total
    FROM attendance
    WHERE DATE(time_in) = CURDATE()
      AND time_out IS NOT NULL
";

$completedResult = $conn->query($completedSql);
$completedVisits = (int) $completedResult->fetch_assoc()['total'];


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
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Attendance Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
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

.subtitle {
    color: #9ca3af;
}

.card {
    background: #1f2937;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
}

input {
    width: 100%;
    max-width: 400px;
    padding: 13px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    font-size: 15px;
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

button {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.time-in {
    background: #22c55e;
    color: #111827;
}

.time-out {
    background: #facc15;
    color: #111827;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.table-box {
    overflow-x: auto;
    margin-top: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
    background: #273449;
}

.empty {
    text-align: center;
    color: #9ca3af;
}
.inside-status {
    color: #4ade80;
    font-weight: bold;
}

.attendance-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin: 20px 0;
}

.attendance-filters input {
    width: 250px;
    max-width: 100%;
    padding: 12px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
}

.attendance-filters input:focus {
    outline: none;
    border-color: #22c55e;
}

.clear-btn {
    background: #374151;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.clear-btn:hover {
    background: #4b5563;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 25px;
}

.summary-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
}

.summary-card h3 {
    color: #9ca3af;
    font-size: 16px;
    margin: 0;
}

.summary-number {
    color: #4ade80;
    font-size: 42px;
    font-weight: bold;
    margin: 15px 0;
}

.summary-card span {
    color: #9ca3af;
    font-size: 13px;
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="container">

    <h1>Attendance Management</h1>

    <p>
     <a href="attendance_report.php"
         style="color:#4ade80; font-weight:bold;">
           View Attendance Reports
     </a>
    </p>

    <p class="subtitle">
        Monitor Member Time In and Time Out.
    </p>


<div class="summary-grid">

    <div class="summary-card">
        <h3>Currently Inside</h3>
        <p class="summary-number">
            <?= $insideCount ?>
        </p>
        <span>Members inside the gym</span>
    </div>

    <div class="summary-card">
        <h3>Today's Visits</h3>
        <p class="summary-number">
            <?= $todayVisits ?>
        </p>
        <span>Total visits started today</span>
    </div>

    <div class="summary-card">
        <h3>Completed Visits</h3>
        <p class="summary-number">
            <?= $completedVisits ?>
        </p>
        <span>Today's completed visits</span>
    </div>

</div>


    <div class="card">

        
        <h2>Record Member Attendance</h2>

            <?php if ($attendanceMessage !== ''): ?>
            <p class="attendance-message">
            <?= escape($attendanceMessage) ?>
            </p>
            <?php endif; ?>

            <form action="process_attendance.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['attendance_csrf']) ?>"
            >

        <label for="member_id">Member ID</label>

            <input
            type="number"
            id="member_id"
            name="member_id"
            min="1"
            step="1"
            placeholder="Enter Member ID"
            required
        >

        <div class="actions">
            <button
            type="submit"
            name="action"
            value="time_in"
            class="time-in"
        >
            Time In
        </button>

        <button
            type="submit"
            name="action"
            value="time_out"
            class="time-out"
        >
            Time Out
        </button>
    </div>

</form>

    <div class="card">

        
<div class="card">

    <h2>
        Currently Inside Gym
        (<?= $insideCount ?>)
    </h2>

    <p class="subtitle">
        Members who have recorded Time In
        but have not yet recorded Time Out.
    </p>

    <div class="table-box">
        <table id="attendanceHistoryTable">
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Time In</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($insideCount > 0): ?>

                <?php while ($inside = $insideResult->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?= escape($inside['member_id']) ?>
                        </td>

                        <td>
                            <?= escape($inside['full_name']) ?>
                        </td>

                        <td>
                            <?= escape($inside['time_in']) ?>
                        </td>

                        <td class="inside-status">
                            Inside Gym
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4" class="empty">
                        No members currently inside the gym.
                    </td>
                </tr>

            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>


        <h2>Attendance History</h2>


<div class="attendance-filters">

    <input
        type="text"
        id="attendanceSearch"
        placeholder="Search Member ID or Name..."
    >

    <input
        type="date"
        id="attendanceDate"
    >

    <button
        type="button"
        id="clearFilters"
        class="clear-btn"
    >
        Clear Filters
    </button>

</div>

<p id="filterMessage" class="subtitle"></p>


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

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>
                            <td><?= escape($row['id']) ?></td>

                            <td>
                                <?= escape($row['member_id']) ?>
                            </td>

                            <td>
                                <?= escape($row['full_name']) ?>
                            </td>

                            <td>
                                <?= escape($row['time_in']) ?>
                            </td>

                            <td>
                                <?= escape(
                                    $row['time_out'] ?? 'Not Yet'
                                ) ?>
                            </td>

                            <td>
                                <?= $row['time_out'] === null
                                    ? 'Inside Gym'
                                    : 'Completed' ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

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

</div>

<script>
const searchInput = document.getElementById("attendanceSearch");
const dateInput = document.getElementById("attendanceDate");
const clearButton = document.getElementById("clearFilters");

const historyTable = document.getElementById("attendanceHistoryTable");
const historyRows = historyTable.querySelectorAll("tbody tr");
const filterMessage = document.getElementById("filterMessage");

function filterAttendance() {

    const search = searchInput.value.toLowerCase().trim();
    const selectedDate = dateInput.value;

    let visibleCount = 0;
    let totalCount = 0;

    historyRows.forEach(function(row) {

        const cells = row.querySelectorAll("td");

        // Skip the empty-table message row.
        if (cells.length !== 6) {
            return;
        }

        totalCount++;

        const memberId = cells[1].textContent.toLowerCase().trim();
        const memberName = cells[2].textContent.toLowerCase().trim();
        const timeIn = cells[3].textContent.trim();

        const matchesSearch =
            memberId.includes(search) ||
            memberName.includes(search);

        // MySQL DATETIME starts with YYYY-MM-DD.
        const matchesDate =
            selectedDate === "" ||
            timeIn.startsWith(selectedDate);

        const matches = matchesSearch && matchesDate;

        row.style.display = matches ? "" : "none";

        if (matches) {
            visibleCount++;
        }
    });

    if (totalCount === 0) {
        filterMessage.textContent = "No attendance records available.";
    } else if (visibleCount === 0) {
        filterMessage.textContent = "No matching attendance records found.";
    } else {
        filterMessage.textContent =
            "Showing " + visibleCount + " of " +
            totalCount + " attendance records.";
    }
}

searchInput.addEventListener("input", filterAttendance);
dateInput.addEventListener("change", filterAttendance);

clearButton.addEventListener("click", function() {
    searchInput.value = "";
    dateInput.value = "";
    filterAttendance();
});

filterAttendance();
</script>

</body>
</html>
