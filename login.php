<?php
// login.php — verify credentials, then issue a random expiring token.
require_once 'ratelimit.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!is_string($email) || !is_string($password)
    || strlen($email) > 190 || strlen($password) > 72
    || strpos($password, "\0") !== false) {
  http_response_code(401);
  echo json_encode(['error' => 'Email or password incorrect']);
  exit;
}
$email = strtolower(trim($email));

// ---- Brute-force protection (slide: "Limit repeated login attempts") ----
// Failed attempts are counted per email (5 / 15 min) and per IP (20 / 15 min).
// The lock applies whether or not the email exists, so it reveals nothing.
$WINDOW = 900;
$emailKey = hash('sha256', 'login_fail_email|' . $email);
$ipKey = hash('sha256', 'login_fail_ip|' . $_SERVER['REMOTE_ADDR']);
[$emailFails, $emailEnd] = rate_peek($conn, $emailKey);
[$ipFails, $ipEnd] = rate_peek($conn, $ipKey);
if ($emailFails >= 5 || $ipFails >= 20) {
  rate_reject(max($emailEnd, $ipEnd));
}

$stmt = mysqli_prepare($conn,
  'SELECT id, name, password_hash, role FROM users WHERE email = ?');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Unknown email: still run password_verify() against a dummy hash so both
// failure paths take about the same time (no account discovery by timing).
$dummyHash = '$2y$10$pUPp1iOSWgQWb0I08jlo5OVc6XDKARWqSpSG129FIikwJSRebqtsO';
$hashToCheck = $user ? $user['password_hash'] : $dummyHash;
$ok = password_verify($password, $hashToCheck);

if (!$user || !$ok) {
  rate_hit($conn, $emailKey, $WINDOW);
  rate_hit($conn, $ipKey, $WINDOW);
  http_response_code(401);
  echo json_encode(['error' => 'Email or password incorrect']);   // same message
  exit;
}

// Success: forget the failed attempts for this email.
rate_clear($conn, $emailKey);

// Random, unguessable token. Flutter gets the token; MySQL stores its hash.
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$userId = (int) $user['id'];
$now = time();
$expires = $now + 3600;                      // one hour

// Tidy up this user's expired sessions.
$stmt = mysqli_prepare($conn,
  'DELETE FROM login_sessions WHERE user_id = ? AND expires_at <= ?');
mysqli_stmt_bind_param($stmt, 'ii', $userId, $now);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn,
  'INSERT INTO login_sessions (token_hash, user_id, expires_at)
   VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sii', $tokenHash, $userId, $expires);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode([
  'token' => $token,
  'name' => $user['name'],
  'role' => $user['role'],            // for the UI only; PHP re-checks it
  'expires_at' => $expires,
]);
exit;
