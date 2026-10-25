<?php
// logout.php — delete the server-side session so the token stops working.
require_once 'ratelimit.php';
rate_limit_or_exit($conn, 'logout', 30);
require_once 'auth.php';

$stmt = mysqli_prepare($conn, 'DELETE FROM login_sessions WHERE token_hash = ?');
mysqli_stmt_bind_param($stmt, 's', $tokenHash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode(['message' => 'Logged out']);
exit;
