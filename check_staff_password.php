
<?php
// Run only from the local command line.
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Access denied.");
}

require_once __DIR__ . '/config/db.php';

echo "Enter Staff email: ";
$email = trim(fgets(STDIN));

echo "Enter Staff password: ";
$password = rtrim(fgets(STDIN), "\r\n");

$stmt = $conn->prepare(
    "SELECT password_hash, role
     FROM users
     WHERE email = ?
     LIMIT 1"
);

$stmt->bind_param("s", $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "Account not found." . PHP_EOL;
} elseif (password_verify(
    $password,
    $user["password_hash"]
)) {
    echo "Password is CORRECT." . PHP_EOL;
    echo "Role: " . $user["role"] . PHP_EOL;
} else {
    echo "Password does NOT match." . PHP_EOL;
}
?>
