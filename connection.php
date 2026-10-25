<?php
// connection.php — included by every endpoint (via require_once).
// JSON headers, POST-only policy, safe error handling, restricted DB account.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Slide "Database errors can disclose internal information":
// never show raw errors to the client; log them on the server instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Any exception that an endpoint does not handle itself ends up here:
// the client gets a generic message, the details go to the server log.
set_exception_handler(function (Throwable $e) {
  error_log('[products_api] ' . get_class($e) . ': ' . $e->getMessage());
  if (!headers_sent()) {
    http_response_code(500);
  }
  echo json_encode(['error' => 'Service temporarily unavailable']);
  exit;
});

// Tokens and passwords travel in the POST body, never in a URL (URLs end
// up in logs and browser history). Anything else is refused.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

// Dedicated account with minimal privileges (see setup.sql).
$conn = mysqli_connect(
  '127.0.0.1', 'products_lab_user',
  'ProductsLab2026!', 'products_lab', 3306
);
mysqli_set_charset($conn, 'utf8mb4');
