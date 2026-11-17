
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

// Generate CSRF token
if (empty($_SESSION['admin_member_csrf'])) {
    $_SESSION['admin_member_csrf'] = bin2hex(random_bytes(32));
}

// Get status update message
$message = $_SESSION['admin_member_message'] ?? '';
unset($_SESSION['admin_member_message']);

// Escape HTML output
function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Retrieve registered members
$stmt = $conn->prepare(
    "SELECT
        id,
        full_name,
        email,
        status,
        is_verified,
        created_at
     FROM users
     WHERE role = 'member'
     ORDER BY created_at DESC, id DESC"
);

$stmt->execute();

$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Manage Members | Admin</title>

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

/* MAIN LAYOUT */
.admin-layout {
    display: flex;
    min-height: calc(100vh - 82px);
}

/* SIDEBAR */
.admin-sidebar {
    width: 250px;
    flex-shrink: 0;
    background: #1f2937;
    padding: 25px 18px;
    border-right: 1px solid #374151;
}

.admin-sidebar h3 {
    color: #22c55e;
    font-size: 18px;
    margin: 0 0 25px;
}

.admin-nav {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.admin-nav a {
    display: block;
    background: #374151;
    color: white;
    padding: 14px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}

.admin-nav a:hover {
    background: #4b5563;
}

.admin-nav a.active {
    background: #16a34a;
    color: white;
    font-weight: bold;
}

/* MAIN CONTENT */
.admin-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.subtitle {
    color: #9ca3af;
}

/* MEMBER CARD */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    padding: 25px;
    border-radius: 12px;
    margin-top: 25px;
}

/* SEARCH */
.search-box {
    width: 100%;
    padding: 13px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 8px;
    color: white;
    font-size: 15px;
    margin-bottom: 20px;
}

.search-box:focus {
    outline: none;
    border-color: #22c55e;
}

/* TABLE */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 15px;
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

/* ACCOUNT STATUS */
td.active {
    color: #4ade80;
    font-weight: bold;
}

td.inactive {
    color: #f87171;
    font-weight: bold;
}

/* BUTTONS */
button {
    border: none;
    padding: 9px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.deactivate-btn {
    background: #dc2626;
    color: white;
}

.deactivate-btn:hover {
    background: #b91c1c;
}

.activate-btn {
    background: #22c55e;
    color: #111827;
}

.activate-btn:hover {
    background: #16a34a;
}

/* STATUS MESSAGE */
.message {
    background: #273449;
    color: #4ade80;
    padding: 15px;
    border-radius: 8px;
}

/* RESPONSIVE */
@media (max-width: 768px) {

    .admin-layout {
        flex-direction: column;
    }

    .admin-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #374151;
    }

    .admin-content {
        padding: 20px;
    }

}

@media (max-width: 600px) {

    header {
        flex-direction: column;
        padding: 18px;
        gap: 12px;
    }

    .card {
        padding: 15px;
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

    <!-- LEFT SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="admin-content">

        <div class="container">

            <h1>Member Management</h1>

            <p class="subtitle">
                View, search, activate, and deactivate
                Member accounts.
            </p>

            <!-- SUCCESS MESSAGE -->
            <?php if ($message !== ''): ?>

                <p class="message">
                    <?= escape($message) ?>
                </p>

            <?php endif; ?>

            <!-- MEMBERS CARD -->
            <div class="card">

                <h2>
                    Registered Members
                    (<?= count($members) ?>)
                </h2>

                <!-- SEARCH -->
                <input
                    type="text"
                    id="memberSearch"
                    class="search-box"
                    placeholder="Search by Member ID, name, or email..."
                    oninput="searchMembers()"
                >

                <!-- TABLE -->
                <div class="table-container">

                    <table id="membersTable">

                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Email Verified</th>
                                <th>Account Status</th>
                                <th>Date Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($members as $member): ?>

                            <tr>

                                <td>
                                    <?= (int)$member['id'] ?>
                                </td>

                                <td>
                                    <?= escape($member['full_name']) ?>
                                </td>

                                <td>
                                    <?= escape($member['email']) ?>
                                </td>

                                <td>
                                    <?= $member['is_verified']
                                        ? 'Verified'
                                        : 'Not Verified' ?>
                                </td>

                                <td class="<?= $member['status'] === 'active'
                                    ? 'active'
                                    : 'inactive' ?>">
                                    <?= escape(
                                        ucfirst($member['status'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape($member['created_at']) ?>
                                </td>

                                <td>

                                    <form
                                        action="update_member_status.php"
                                        method="POST"
                                        onsubmit="return confirm('Are you sure you want to change this account status?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escape(
                                                $_SESSION['admin_member_csrf']
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="member_id"
                                            value="<?= (int)$member['id'] ?>"
                                        >

                                        <?php if ($member['status'] === 'active'): ?>

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="inactive"
                                            >

                                            <button
                                                type="submit"
                                                class="deactivate-btn"
                                            >
                                                Deactivate
                                            </button>

                                        <?php else: ?>

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="active"
                                            >

                                            <button
                                                type="submit"
                                                class="activate-btn"
                                            >
                                                Activate
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($members)): ?>

                            <tr>
                                <td colspan="7">
                                    No registered Members found.
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

<!-- SEARCH FUNCTION -->
<script>
function searchMembers() {

    const search = document
        .getElementById('memberSearch')
        .value
        .toLowerCase();

    const rows = document.querySelectorAll(
        '#membersTable tbody tr'
    );

    rows.forEach(row => {

        const text = row.textContent.toLowerCase();

        row.style.display = text.includes(search)
            ? ''
            : 'none';

    });

}
</script>

</body>
</html>
