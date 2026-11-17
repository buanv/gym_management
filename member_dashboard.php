
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// ======================================
// 1. MEMBER ACCESS ONLY
// ======================================

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "member"
) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Escape HTML output
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ======================================
// 2. GET MEMBER INFORMATION
// ======================================

$stmt = $conn->prepare(
    "SELECT
        id,
        full_name,
        email
     FROM users
     WHERE id = ?
       AND role = 'member'
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$member = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$member) {
    http_response_code(403);
    exit("Member account unavailable or unverified.");
}

// Generate formatted Member ID
$member_id = "GYM-" . str_pad(
    (string)$member["id"],
    5,
    "0",
    STR_PAD_LEFT
);

// ======================================
// 3. GET LATEST MEMBERSHIP
// ======================================

$membership_stmt = $conn->prepare(
    "SELECT
        mm.id,
        mm.plan_id,
        mm.payment_status,
        mm.membership_status,
        mm.start_date,
        mm.end_date,
        mp.plan_name,
        mp.price,
        mp.duration_days

     FROM member_memberships AS mm

     LEFT JOIN membership_plans AS mp
        ON mm.plan_id = mp.id

     WHERE mm.user_id = ?

     ORDER BY mm.id DESC
     LIMIT 1"
);

$membership_stmt->bind_param("i", $user_id);
$membership_stmt->execute();

$membership = $membership_stmt
    ->get_result()
    ->fetch_assoc();

$membership_stmt->close();

// Safe membership values
$hasMembership = ($membership !== null);

$paymentStatus = $hasMembership
    ? ($membership["payment_status"] ?? "pending")
    : null;

$membershipStatus = $hasMembership
    ? ($membership["membership_status"] ?? "pending")
    : null;

$planName = $hasMembership
    ? ($membership["plan_name"] ?? "Plan unavailable")
    : null;

$planPrice = $hasMembership
    ? (float)($membership["price"] ?? 0)
    : 0;

$durationDays = $hasMembership
    ? (int)($membership["duration_days"] ?? 0)
    : 0;

$startDate = $hasMembership
    ? ($membership["start_date"] ?? null)
    : null;

$endDate = $hasMembership
    ? ($membership["end_date"] ?? null)
    : null;

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Member Dashboard | 4BROS AND 1BOSS</title>

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
    flex-wrap: wrap;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

.logout {
    background: #ef4444;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
}

.logout:hover {
    background: #dc2626;
}

/* MAIN CONTAINER */
.container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 20px;
}

/* WELCOME */
.welcome {
    color: #d1d5db;
    margin-bottom: 25px;
}

/* CARDS */
.card {
    background: #1f2937;
    border: 1px solid #374151;
    padding: 25px;
    margin-top: 25px;
    border-radius: 12px;
}

.card h2,
.card h3 {
    color: #22c55e;
    margin-top: 0;
}

/* MEMBER INFORMATION */
.info {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding: 14px 0;
    border-bottom: 1px solid #374151;
}

.info:last-child {
    border-bottom: none;
}

.info span {
    color: #9ca3af;
}

.info strong {
    text-align: right;
    overflow-wrap: anywhere;
}

/* STATUS */
.pending {
    color: #fbbf24;
}

.paid,
.active {
    color: #4ade80;
}

.rejected,
.expired {
    color: #f87171;
}

/* BUTTONS */
.button {
    display: inline-block;
    background: #22c55e;
    color: #111827;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    margin-top: 18px;
}

.button:hover {
    background: #16a34a;
}

.button-secondary {
    background: #374151;
    color: white;
}

.button-secondary:hover {
    background: #4b5563;
}

/* OTHER FEATURES */
.feature-description {
    color: #9ca3af;
    line-height: 1.6;
}

/* RESPONSIVE DESIGN */
@media (max-width: 600px) {

    .container {
        padding: 15px;
        margin-top: 15px;
    }

    header {
        padding: 18px;
    }

    .card {
        padding: 20px;
    }

    .info {
        flex-direction: column;
        gap: 6px;
    }

    .info strong {
        text-align: left;
    }

}

</style>
</head>

<body>

<!-- HEADER -->
<header>

    <h2>4BROS AND 1BOSS</h2>

    <a href="logout.php" class="logout">
        Logout
    </a>

</header>

<!-- MAIN DASHBOARD -->
<div class="container">

    <h1>Member Dashboard</h1>

    <p class="welcome">
        Welcome,
        <strong><?= e($member["full_name"]) ?></strong>!
    </p>

    <!-- ================================= -->
    <!-- MEMBER INFORMATION -->
    <!-- ================================= -->

    <div class="card">

        <h2>My Member Information</h2>

        <div class="info">
            <span>Member ID</span>

            <strong>
                <?= e($member_id) ?>
            </strong>
        </div>

        <div class="info">
            <span>Full Name</span>

            <strong>
                <?= e($member["full_name"]) ?>
            </strong>
        </div>

        <div class="info">
            <span>Email Address</span>

            <strong>
                <?= e($member["email"]) ?>
            </strong>
        </div>

    </div>

    <!-- ================================= -->
    <!-- MEMBERSHIP INFORMATION -->
    <!-- ================================= -->

    <div class="card">

        <h2>My Membership</h2>

        <?php if (!$hasMembership): ?>

            <p>
                You do not have a saved membership yet.
            </p>

            <p class="feature-description">
                Please choose a membership plan
                to continue your registration.
            </p>

            <a href="membership.php" class="button">
                Choose Membership
            </a>

        <?php else: ?>

            <div class="info">
                <span>Selected Plan</span>

                <strong>
                    <?= e($planName) ?>
                </strong>
            </div>

            <div class="info">
                <span>Price</span>

                <strong>
                    ₱<?= number_format($planPrice, 2) ?>
                </strong>
            </div>

            <div class="info">
                <span>Duration</span>

                <strong>
                    <?= $durationDays ?> Days
                </strong>
            </div>

            <div class="info">
                <span>Payment Status</span>

                <strong class="<?= e($paymentStatus) ?>">
                    <?= e(ucwords($paymentStatus)) ?>
                </strong>
            </div>

            <div class="info">
                <span>Membership Status</span>

                <strong class="<?= e($membershipStatus) ?>">
                    <?= e(ucwords($membershipStatus)) ?>
                </strong>
            </div>

            <div class="info">
                <span>Start Date</span>

                <strong>
                    <?= $startDate
                        ? e($startDate)
                        : "Not yet activated"
                    ?>
                </strong>
            </div>

            <div class="info">
                <span>Expiration Date</span>

                <strong>
                    <?= $endDate
                        ? e($endDate)
                        : "Not yet assigned"
                    ?>
                </strong>
            </div>

            <!-- PAYMENT BUTTON -->
            <?php if ($paymentStatus === "pending"): ?>

                <a href="payment.php" class="button">
                    Proceed to Payment
                </a>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- ================================= -->
    <!-- WEIGHT TRACKER -->
    <!-- ================================= -->

    <div class="card">

        <h3>My Weight Tracker</h3>

        <p class="feature-description">
            Record your weight and monitor
            your fitness progress.
        </p>

        <a href="weight_tracker.php" class="button">
            Open Weight Tracker
        </a>

    </div>

    <!-- ================================= -->
    <!-- MEMBER PROFILE -->
    <!-- ================================= -->

    <div class="card">

        <h3>My Profile</h3>

        <p class="feature-description">
            View and manage your personal
            account information.
        </p>

        <a href="member_profile.php" class="button">
            View My Profile
        </a>

    </div>

</div>

</body>
</html>
