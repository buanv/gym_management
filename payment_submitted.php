
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "member"
) {
    http_response_code(403);
    exit("Access denied.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">
<title>Payment Submitted</title>

<style>
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
}

.card {
    background: #1f2937;
    padding: 35px;
    border-radius: 12px;
    text-align: center;
    max-width: 420px;
    margin: 20px;
}

h1 {
    color: #22c55e;
}

a {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 20px;
    background: #22c55e;
    color: #111827;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="card">
    <h1>Payment Request Submitted!</h1>

    <p>
        Your payment request has been recorded.
    </p>

    <p>
        Payment Status:
        <strong>Pending Verification</strong>
    </p>

    <p>
        Your membership will be activated
        after Staff confirms payment.
    </p>

    <a href="member_dashboard.php">
        Return to Dashboard
    </a>
</div>

</body>
</html>
