
<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Only authenticated, verified Members.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "member"
) {
    http_response_code(403);
    exit("Access denied. Members only.");
}

$user_id = (int)$_SESSION["user_id"];

$user_stmt = $conn->prepare(
    "SELECT id FROM users
     WHERE id = ?
       AND role = 'member'
       AND status = 'active'
       AND is_verified = 1
     LIMIT 1"
);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();

if (!$user_stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    exit("Member account unavailable or unverified.");
}

// Retrieve this Member's latest membership.
$stmt = $conn->prepare(
    "SELECT
        mm.id,
        mm.payment_status,
        mm.membership_status,
        mp.plan_name,
        mp.price
     FROM member_memberships mm
     JOIN membership_plans mp
         ON mm.plan_id = mp.id
     WHERE mm.user_id = ?
     ORDER BY mm.created_at DESC, mm.id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$membership = $stmt->get_result()->fetch_assoc();

if (!$membership) {
    header("Location: membership.php");
    exit;
}

// Replace these values with the gym owner's
// approved GCash payment details.
$gcash_name = "GYM OWNER NAME";
$gcash_number = "09XXXXXXXXX";

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$is_paid = $membership["payment_status"] === "paid";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Membership Payment</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #111827;
    color: white;
}

header {
    padding: 20px 30px;
    background: #1f2937;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

.container {
    max-width: 650px;
    margin: 35px auto;
    padding: 20px;
}

.card {
    background: #1f2937;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.info {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #374151;
}

.price {
    color: #4ade80;
    font-size: 24px;
}

.option {
    display: block;
    padding: 15px;
    background: #374151;
    border-radius: 8px;
    margin: 12px 0;
    cursor: pointer;
}

.option input {
    margin-right: 10px;
}

.details {
    display: none;
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #4b5563;
    border-radius: 8px;
}

.details h3 {
    color: #4ade80;
}

.notice {
    color: #fbbf24;
    line-height: 1.6;
}

button, .button {
    display: inline-block;
    background: #22c55e;
    color: #111827;
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

a {
    color: #4ade80;
}

@media (max-width: 600px) {
    .info {
        flex-direction: column;
    }

    header {
        flex-wrap: wrap;
    }
}
.details input[type="text"],
.details input[type="file"] {
    display: block;
    width: 100%;
    margin: 10px 0 20px;
    padding: 12px;
    background: #374151;
    color: white;
    border: 1px solid #4b5563;
    border-radius: 6px;
}

.details label {
    display: block;
    margin-top: 15px;
}
</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="member_dashboard.php">
        Back to Dashboard
    </a>
</header>

<div class="container">

    <h1>Complete Your Payment</h1>

    <div class="card">

        <h2>Membership Summary</h2>

        <div class="info">
            <span>Selected Plan</span>
            <strong>
                <?= e($membership["plan_name"]) ?>
            </strong>
        </div>

        <div class="info">
            <span>Amount Due</span>
            <strong class="price">
                ₱<?= number_format(
                    (float)$membership["price"],
                    2
                ) ?>
            </strong>
        </div>

        <div class="info">
            <span>Payment Status</span>
            <strong>
                <?= e(ucfirst(
                    $membership["payment_status"]
                )) ?>
            </strong>
        </div>

    </div>

    <?php if ($is_paid): ?>

        <div class="card">
            <h2>Payment Confirmed</h2>
            <p>
                Your membership payment has
                already been confirmed.
            </p>

            <a class="button"
               href="member_dashboard.php">
                Return to Dashboard
            </a>
        </div>

    <?php else: ?>

        <div class="card">

            <h2>Select Payment Method</h2>

            <label class="option">
                <input
                    type="radio"
                    name="payment_method"
                    value="cash"
                    onchange="showMethod('cash')"
                >
                Cash — Pay at Gym
            </label>

            <label class="option">
                <input
                    type="radio"
                    name="payment_method"
                    value="gcash"
                    onchange="showMethod('gcash')"
                >
                GCash — Send to Gym Owner
            </label>

            
        <div id="cashDetails" class="details">

            <h3>Cash Payment</h3>

         <p>
           Please visit the gym's reception
           and pay the required amount.
         </p>

          <p class="notice">
           Your membership will remain pending
           until Staff confirms your payment.
          </p>

         <!-- CASH PAYMENT FORM -->
         <form action="submit_payment.php" method="POST">

         <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION["csrf_token"]) ?>"
           >

          <input
            type="hidden"
            name="payment_method"
            value="cash"
            >

         <button type="submit">
             Request Cash Payment
          </button>

         </form>

        </div>


            <div id="gcashDetails" class="details">

                <h3>GCash Payment</h3>

                <p>
                    Send your payment to:
                </p>

                <p>
                    <strong>Account Name:</strong>
                    <?= e($gcash_name) ?>
                </p>

                <p>
                    <strong>GCash Number:</strong>
                    <?= e($gcash_number) ?>
                </p>

                <p>
                    <strong>Amount:</strong>
                    ₱<?= number_format(
                        (float)$membership["price"],
                        2
                    ) ?>
                </p>

                <p class="notice">
                    After sending payment,
                    keep your GCash reference
                    number and screenshot.
                    Staff must verify the
                    actual transaction before
                    your membership is activated.
                </p>

                
                <form action="submit_payment.php"
                method="POST"
                enctype="multipart/form-data">

                 <input
                  type="hidden"
                  name="csrf_token"
                  value="<?= e($_SESSION["csrf_token"]) ?>"
                  >

                 <input
                    type="hidden"
                     name="payment_method"
                   value="gcash"
                  >

               <label for="reference_number">
                  GCash Reference Number
               </label>

              <input
               type="text"
                id="reference_number"
              name="reference_number"
              maxlength="100"
              placeholder="Enter GCash reference number"
                required
                 >

                 <label for="proof">
                  Upload Payment Screenshot
                </label>

              <input
               type="file"
               id="proof"
               name="proof"
               accept=".jpg,.jpeg,.png"
               required
                >

              <p class="notice">
               Maximum file size: 2 MB.
              Staff will verify your payment
              before activating your membership.
             </p>

             <button type="submit">
              Submit GCash Payment
            </button>

</form>


            </div>

        </div>

    <?php endif; ?>

</div>

<script>
function showMethod(method) {
    const cash = document.getElementById("cashDetails");
    const gcash = document.getElementById("gcashDetails");

    if (!cash || !gcash) return;

    cash.style.display =
        method === "cash" ? "block" : "none";

    gcash.style.display =
        method === "gcash" ? "block" : "none";
}
</script>

</body>
</html>
