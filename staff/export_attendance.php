
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only logged-in Staff can export attendance.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    http_response_code(403);
    exit('Access denied.');
}

// Validate dates.
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

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    $startDate > $endDate
) {
    http_response_code(400);
    exit('Invalid date range.');
}

// Retrieve attendance records.
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
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();

$result = $stmt->get_result();

// Create the CSV filename.
$filename = "attendance_{$startDate}_to_{$endDate}.csv";

// Prevent caching of private attendance data.
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Open output stream.
$output = fopen('php://output', 'w');

if ($output === false) {
    exit;
}

// UTF-8 BOM for Excel compatibility.
fwrite($output, "\xEF\xBB\xBF");

// CSV column headings.
fputcsv($output, [
    'Attendance ID',
    'Member ID',
    'Member Name',
    'Time In',
    'Time Out',
    'Status'
]);

// Prevent spreadsheet formula injection.
function safeCsvValue($value) {
    $value = (string)($value ?? '');

    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

// Write attendance records.
while ($row = $result->fetch_assoc()) {

    $status = $row['time_out'] === null
        ? 'Inside Gym'
        : 'Completed';

    fputcsv($output, [
        safeCsvValue($row['id']),
        safeCsvValue($row['member_id']),
        safeCsvValue($row['full_name']),
        safeCsvValue($row['time_in']),
        safeCsvValue($row['time_out'] ?? 'Not Yet'),
        safeCsvValue($status)
    ]);
}

fclose($output);
$stmt->close();
exit;
