
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only Staff accounts can process attendance.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attendance.php');
    exit;
}

// Verify CSRF token.
if (
    !isset($_SESSION['attendance_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals(
        $_SESSION['attendance_csrf'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Invalid request token.');
}

$memberId = filter_var(
    $_POST['member_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$action = $_POST['action'] ?? '';
$staffId = (int) $_SESSION['user_id'];

if (
    $memberId === false ||
    $memberId === null ||
    !in_array($action, ['time_in', 'time_out'], true)
) {
    $_SESSION['attendance_message'] =
        'Invalid Member ID or attendance action.';

    header('Location: attendance.php');
    exit;
}

try {
    $conn->begin_transaction();

    // Lock the member record to serialize attendance actions.
    $memberStmt = $conn->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
           AND role = 'member'
           AND status = 'active'
         FOR UPDATE"
    );

    $memberStmt->bind_param('i', $memberId);
    $memberStmt->execute();

    $member = $memberStmt->get_result()->fetch_assoc();

    if (!$member) {
        throw new RuntimeException(
            'Member not found or account is inactive.'
        );
    }

    // Find an attendance session that has not ended.
    $openStmt = $conn->prepare(
        "SELECT id
         FROM attendance
         WHERE member_id = ?
           AND time_out IS NULL
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $openStmt->bind_param('i', $memberId);
    $openStmt->execute();

    $openAttendance = $openStmt
        ->get_result()
        ->fetch_assoc();

    if ($action === 'time_in') {

        if ($openAttendance) {
            throw new RuntimeException(
                'This member is already inside the gym.'
            );
        }

        // Check that the membership is active and not expired.
        $membershipStmt = $conn->prepare(
            "SELECT mm.id
             FROM member_memberships mm
             WHERE mm.user_id = ?
               AND mm.payment_status = 'paid'
               AND mm.membership_status = 'active'
               AND mm.start_date <= CURDATE()
               AND mm.end_date >= CURDATE()
             LIMIT 1"
        );

        $membershipStmt->bind_param('i', $memberId);
        $membershipStmt->execute();

        $activeMembership = $membershipStmt
            ->get_result()
            ->fetch_assoc();

        if (!$activeMembership) {
            throw new RuntimeException(
                'This member does not have a valid active membership.'
            );
        }

        // Record Time In.
        $insertStmt = $conn->prepare(
            "INSERT INTO attendance
             (member_id, time_in, recorded_by)
             VALUES (?, NOW(), ?)"
        );

        $insertStmt->bind_param(
            'ii',
            $memberId,
            $staffId
        );

        $insertStmt->execute();

        $message = 'Member Time In recorded successfully!';

    } else {

        if (!$openAttendance) {
            throw new RuntimeException(
                'This member has no active Time In record.'
            );
        }

        $attendanceId = (int) $openAttendance['id'];

        // Record Time Out.
        $updateStmt = $conn->prepare(
            "UPDATE attendance
             SET time_out = NOW()
             WHERE id = ?
               AND time_out IS NULL"
        );

        $updateStmt->bind_param('i', $attendanceId);
        $updateStmt->execute();

        if ($updateStmt->affected_rows !== 1) {
            throw new RuntimeException(
                'Unable to record Time Out.'
            );
        }

        $message = 'Member Time Out recorded successfully!';
    }

    $conn->commit();

    $_SESSION['attendance_message'] = $message;

} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Attendance processing error: ' . $e->getMessage()
    );

    $_SESSION['attendance_message'] =
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to process attendance. Please try again.';
}

header('Location: attendance.php');
exit;
