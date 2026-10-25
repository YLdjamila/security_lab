<?php
// register.php — create a CUSTOMER account. Stores a password hash only.
require_once 'ratelimit.php';
rate_limit_or_exit($conn, 'register', 10, 3600);   // 10 sign-ups / hour / IP

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Server-side validation (the Flutter checks are only for convenience).
if (!is_string($name) || !is_string($email) || !is_string($password)
    || !preg_match('/^.{1,100}$/su', trim($name))      // 1-100 valid UTF-8 chars
    || trim($email) === '' || strlen($email) > 190
    || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
    || strlen($password) < 15 || strlen($password) > 72   // bcrypt reads 72 bytes
    || strpos($password, "\0") !== false) {
  http_response_code(400);
  echo json_encode(['error' => 'Check account details and password length (15-72)']);
  exit;
}

$name = trim($name);
$email = strtolower(trim($email));
$hash = password_hash($password, PASSWORD_DEFAULT);   // random salt included

// The role is fixed in the SQL: a "role" field in the request is ignored.
$stmt = mysqli_prepare($conn,
  "INSERT INTO users (name, email, password_hash, role)
   VALUES (?, ?, ?, 'customer')");
mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $hash);

try {
  mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
  if ((int) $e->getCode() === 1062) {               // duplicate email
    http_response_code(409);
    echo json_encode(['error' => 'Unable to create this account']);
    exit;
  }
  throw $e;                                          // generic 500 + log
}

http_response_code(201);
echo json_encode(['message' => 'Account created. You can now sign in.']);
exit;
