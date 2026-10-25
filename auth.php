<?php
// auth.php — include in every protected endpoint.
// Verifies the token sent in $_POST['token'] and creates $user
// (id, name, email, role) from the database. Invalid token => 401 + exit.
require_once 'connection.php';

$token = $_POST['token'] ?? '';
if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
  http_response_code(401);
  echo json_encode(['error' => 'Please sign in again']);
  exit;
}

$tokenHash = hash('sha256', $token);
$now = time();
$stmt = mysqli_prepare($conn,
  'SELECT u.id, u.name, u.email, u.role
   FROM login_sessions s JOIN users u ON u.id = s.user_id
   WHERE s.token_hash = ? AND s.expires_at > ?');
mysqli_stmt_bind_param($stmt, 'si', $tokenHash, $now);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
  http_response_code(401);
  echo json_encode(['error' => 'Please sign in again']);
  exit;
}
// The role always comes from MySQL, never from the request.
