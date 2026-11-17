
<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$adminLinks = [
    'dashboard.php' => 'Dashboard',
    'members.php' => 'Manage Members',
    'staff.php' => 'Manage Staff',
    'plans.php' => 'Membership Plans',
    'payments.php' => 'Manage Payments',
    'attendance.php' => 'Attendance Monitoring',
    'reports.php' => 'Reports & Analytics',
    'activity_logs.php' => 'Activity Logs',
    'staff_attendance.php' => 'Staff Attendance',
    'staff_analytics.php' => 'Staff Attendance Analytics'
];
?>

<aside class="admin-sidebar">

    <h3>Admin Management Tools</h3>

    <nav class="admin-nav">

        <?php foreach ($adminLinks as $file => $label): ?>

            <a
                href="<?= htmlspecialchars(
                    $file,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="<?= $currentPage === $file ? 'active' : '' ?>"
            >
                <?= htmlspecialchars(
                    $label,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </a>

        <?php endforeach; ?>

    </nav>

</aside>
