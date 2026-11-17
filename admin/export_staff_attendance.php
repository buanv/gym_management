
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Admin access only
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

// Validate optional date filters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

function validDate($date) {
    if ($date === '') {
        return true;
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    ($startDate !== '' && $endDate !== ''
        && $startDate > $endDate)
) {
    http_response_code(400);
    exit('Invalid date range.');
}

// Retrieve Staff attendance records
$sql = "
    SELECT
        sa.id,
        sa.staff_id,
        u.full_name,
        u.email,
        sa.time_in,
        sa.time_out,
        CASE
            WHEN sa.time_out IS NULL THEN 'On Duty'
            ELSE 'Completed'
        END AS attendance_status,
        CASE
            WHEN sa.time_out IS NULL THEN NULL
            ELSE ROUND(
                TIMESTAMPDIFF(
                    SECOND,
                    sa.time_in,
                    sa.time_out
                ) / 3600,
                2
            )
        END AS hours_worked
    FROM staff_attendance sa
    JOIN users u ON sa.staff_id = u.id
    WHERE 1 = 1
";

$types = '';
$params = [];

if ($startDate !== '') {
    $sql .= " AND sa.time_in >= ?";
    $types .= 's';
    $params[] = $startDate . ' 00:00:00';
}

if ($endDate !== '') {
    $nextDay = (new DateTimeImmutable($endDate))
        ->modify('+1 day')
        ->format('Y-m-d');

    $sql .= " AND sa.time_in < ?";
    $types .= 's';
    $params[] = $nextDay . ' 00:00:00';
}

$sql .= " ORDER BY sa.time_in DESC, sa.id DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Prevent spreadsheet formula injection
function safeCsv($value) {
    if ($value === null) {
        return '';
    }

    $value = (string)$value;

    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

$filename = 'staff_attendance_' .
    date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="' .
    $filename . '"'
);
header('Cache-Control: no-store');

$output = fopen('php://output', 'w');

// Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Record ID',
    'Staff ID',
    'Staff Name',
    'Email',
    'Time In',
    'Time Out',
    'Status',
    'Hours Worked'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv(
        $output,
        array_map('safeCsv', array_values($row))
    );
}

fclose($output);
$stmt->close();
exit;
