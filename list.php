<?php
// list.php — product catalogue for signed-in users. PUBLIC columns only.
require_once 'ratelimit.php';
rate_limit_or_exit($conn, 'list', 30);      // 30 requests / minute / IP
require_once 'auth.php';

// Explicit column list (never SELECT *): supplier_cost stays server-side.
// Fixed row limit: the client cannot ask for more.
$result = mysqli_query($conn,
  'SELECT id, name, price FROM products ORDER BY id LIMIT 40');
$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
  $rows[] = $row;
}
echo json_encode($rows);
exit;
