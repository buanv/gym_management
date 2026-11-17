
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'staff'
) {
    header('Location: ../login.php');
    exit;
}

$staffId = (int)$_SESSION['user_id'];

if (empty($_SESSION['attendance_csrf'])) {
    $_SESSION['attendance_csrf'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['attendance_csrf'],
            $_POST['csrf_token'] ?? ''
        )
    ) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    try {

        $conn->begin_transaction();

        // Lock Staff account to serialize attendance actions.
        $lock = $conn->prepare(
            "SELECT id FROM users
             WHERE id = ?
               AND role = 'staff'
               AND status = 'active'
               AND is_verified = 1
             FOR UPDATE"
        );

        $lock->bind_param('i', $staffId);
        $lock->execute();

        if (!$lock->get_result()->fetch_assoc()) {
            throw new RuntimeException(
                'Staff account is not active or verified.'
            );
        }

        // Find an open attendance session.
        $check = $conn->prepare(
            "SELECT id FROM staff_attendance
             WHERE staff_id = ?
               AND time_out IS NULL
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );

        $check->bind_param('i', $staffId);
        $check->execute();

        $openSession = $check->get_result()->fetch_assoc();

        if ($action === 'time_in') {

            if ($openSession) {
                throw new RuntimeException(
                    'You are already timed in.'
                );
            }

            $insert = $conn->prepare(
                "INSERT INTO staff_attendance
                 (staff_id, time_in)
                 VALUES (?, NOW())"
            );

            $insert->bind_param('i', $staffId);
            $insert->execute();

            $message = 'Time-In recorded successfully.';

        } elseif ($action === 'time_out') {

            if (!$openSession) {
                throw new RuntimeException(
                    'You are not currently timed in.'
                );
            }

            $attendanceId = (int)$openSession['id'];

            $update = $conn->prepare(
                "UPDATE staff_attendance
                 SET time_out = NOW()
                 WHERE id = ?
                   AND time_out IS NULL"
            );

            $update->bind_param('i', $attendanceId);
            $update->execute();

            $message = 'Time-Out recorded successfully.';

        } else {
            throw new RuntimeException('Invalid action.');
        }

        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to record attendance.';

        if (!($e instanceof RuntimeException)) {
            error_log($e->getMessage());
        }
    }
}

// Check current attendance status
$stmt = $conn->prepare(
    "SELECT id, time_in
     FROM staff_attendance
     WHERE staff_id = ?
       AND time_out IS NULL
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param('i', $staffId);
$stmt->execute();

$currentSession = $stmt->get_result()->fetch_assoc();

// Recent attendance history
$history = $conn->prepare(
    "SELECT time_in, time_out
     FROM staff_attendance
     WHERE staff_id = ?
     ORDER BY time_in DESC
     LIMIT 20"
);

$history->bind_param('i', $staffId);
$history->execute();

$records = $history->get_result()->fetch_all(MYSQLI_ASSOC);

function e($value) {
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Attendance</title>

<style>
body {
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
    margin: 0;
}

.container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
}

.card {
    background: #1f2937;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
}

h1 {
    color: #22c55e;
}

.status {
    font-size: 20px;
    font-weight: bold;
}

button {
    background: #22c55e;
    border: 0;
    padding: 14px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    background: #16a34a;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
}

a {
    color: #4ade80;
}

.message {
    color: #4ade80;
}
</style>
</head>

<body>

<div class="container">

    <a href="dashboard.php">Back to Dashboard</a>

    <h1>My Attendance</h1>

    <div class="card">

        <h2>Current Status</h2>

        <?php if ($currentSession): ?>

            <p class="status">Currently On Duty</p>

            <p>
                Time In:
                <?= e($currentSession['time_in']) ?>
            </p>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['attendance_csrf']) ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="time_out"
                >
                    Time Out
                </button>
            </form>

        <?php else: ?>

            <p class="status">Currently Off Duty</p>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['attendance_csrf']) ?>"
                >

                <button
                    type="submit"
                    name="action"
                    value="time_in"
                >
                    Time In
                </button>
            </form>

        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <p class="message"><?= e($message) ?></p>
        <?php endif; ?>

    </div>

    <div class="card">

        <h2>Recent Attendance</h2>

        <div style="overflow-x:auto;">

            <table>
                <thead>
                    <tr>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($records as $record): ?>

                    <tr>
                        <td><?= e($record['time_in']) ?></td>

                        <td>
                            <?= e($record['time_out'] ?? '—') ?>
                        </td>

                        <td>
                            <?= $record['time_out'] === null
                                ? 'On Duty'
                                : 'Completed' ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$records): ?>
                    <tr>
                        <td colspan="3">
                            No attendance records yet.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</body>
</html>
