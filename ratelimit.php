<?php
// ratelimit.php — fixed-window counters stored in MySQL (table rate_limits).
// Works on any XAMPP install (no APCu needed). Chapter 2: "10 requests per
// minute ... the eleventh is rejected with HTTP 429".
require_once 'connection.php';

// Count one hit for $key. Starts a new window when the old one has ended.
// Returns [hits in current window, window end timestamp].
function rate_hit(mysqli $conn, string $key, int $window): array {
  $now = time();
  $newEnd = $now + $window;
  $stmt = mysqli_prepare($conn,
    'INSERT INTO rate_limits (rate_key, hits, window_end) VALUES (?, 1, ?)
     ON DUPLICATE KEY UPDATE
       hits = IF(window_end <= ?, 1, hits + 1),
       window_end = IF(window_end <= ?, ?, window_end)');
  mysqli_stmt_bind_param($stmt, 'siiii', $key, $newEnd, $now, $now, $newEnd);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
  $res = rate_peek($conn, $key);
  // Housekeeping: occasionally drop expired counters.
  if (random_int(1, 50) === 1) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM rate_limits WHERE window_end <= ?');
    mysqli_stmt_bind_param($stmt, 'i', $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
  return $res;
}

// Read a counter WITHOUT counting. Returns [hits, window end] (0 if expired).
function rate_peek(mysqli $conn, string $key): array {
  $now = time();
  $stmt = mysqli_prepare($conn,
    'SELECT hits, window_end FROM rate_limits WHERE rate_key = ? AND window_end > ?');
  mysqli_stmt_bind_param($stmt, 'si', $key, $now);
  mysqli_stmt_execute($stmt);
  $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
  mysqli_stmt_close($stmt);
  return $row ? [(int) $row['hits'], (int) $row['window_end']] : [0, 0];
}

function rate_clear(mysqli $conn, string $key): void {
  $stmt = mysqli_prepare($conn, 'DELETE FROM rate_limits WHERE rate_key = ?');
  mysqli_stmt_bind_param($stmt, 's', $key);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
}

// Send HTTP 429 and stop the request.
function rate_reject(int $windowEnd): void {
  http_response_code(429);
  header('Retry-After: ' . max(1, $windowEnd - time()));
  echo json_encode(['error' => 'Too many requests. Please wait and try again.']);
  exit;
}

// General per-IP limit for an endpoint: at most $max requests per $window s.
function rate_limit_or_exit(mysqli $conn, string $name, int $max, int $window = 60): void {
  $key = hash('sha256', $name . '|' . $_SERVER['REMOTE_ADDR']);
  [$hits, $end] = rate_hit($conn, $key, $window);
  if ($hits > $max) {
    rate_reject($end);
  }
}
