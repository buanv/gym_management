
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only Admin can export reports
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    exit('Access denied.');
}

// Only accept supported report types
$reportType = $_GET['type'] ?? '';

$allowedTypes = [
    'members',
    'memberships',
    'payments'
];

if (!in_array($reportType, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid report type.');
}

// Prepare report data
switch ($reportType) {

    case 'members':

        $filename = 'members_report';

        $headers = [
            'Member ID',
            'Full Name',
            'Email',
            'Account Status',
            'Email Verified',
            'Date Registered'
        ];

        $sql = "
            SELECT
                id,
                full_name,
                email,
                status,
                CASE
                    WHEN is_verified = 1
                    THEN 'Verified'
                    ELSE 'Not Verified'
                END AS verification_status,
                created_at
            FROM users
            WHERE role = 'member'
            ORDER BY created_at DESC, id DESC
        ";

        break;

    case 'memberships':

        $filename = 'memberships_report';

        $headers = [
            'Membership ID',
            'Member Name',
            'Member Email',
            'Membership Plan',
            'Plan Price',
            'Payment Status',
            'Membership Status',
            'Start Date',
            'End Date'
        ];

        $sql = "
            SELECT
                mm.id,
                u.full_name,
                u.email,
                mp.plan_name,
                mp.price,
                mm.payment_status,
                mm.membership_status,
                mm.start_date,
                mm.end_date

            FROM member_memberships mm

            LEFT JOIN users u
                ON mm.user_id = u.id

            LEFT JOIN membership_plans mp
                ON mm.plan_id = mp.id

            ORDER BY mm.id DESC
        ";

        break;

    case 'payments':

        $filename = 'payments_report';

        $headers = [
            'Payment ID',
            'Member Name',
            'Member Email',
            'Membership Plan',
            'Amount',
            'Payment Method',
            'Reference Number',
            'Payment Status',
            'Verified By',
            'Verified At',
            'Date Submitted'
        ];

        $sql = "
            SELECT
                p.id,
                u.full_name,
                u.email,
                mp.plan_name,
                p.amount,
                p.payment_method,
                p.reference_number,
                p.payment_status,
                verifier.full_name AS verified_by_name,
                p.verified_at,
                p.created_at

            FROM payments p

            LEFT JOIN member_memberships mm
                ON p.membership_id = mm.id

            LEFT JOIN users u
                ON mm.user_id = u.id

            LEFT JOIN membership_plans mp
                ON mm.plan_id = mp.id

            LEFT JOIN users verifier
                ON p.verified_by = verifier.id

            ORDER BY p.created_at DESC, p.id DESC
        ";

        break;
}

// Prevent spreadsheet formula injection
function safeCsvValue($value) {

    if ($value === null) {
        return '';
    }

    $value = (string)$value;

    // Neutralize formula-like values, including
    // those with leading whitespace/control characters.
    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

// Retrieve report records
try {
    $result = $conn->query($sql);
} catch (Throwable $e) {
    error_log('Export report error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to generate report.');
}

// Generate filename
$downloadName = $filename . '_' . date('Y-m-d') . '.csv';

// CSV response headers
header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="' .
    $downloadName . '"'
);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV column headings
fputcsv($output, $headers);

// Export each database record
while ($row = $result->fetch_assoc()) {

    $safeRow = array_map(
        'safeCsvValue',
        array_values($row)
    );

    fputcsv($output, $safeRow);
}

fclose($output);
exit;
