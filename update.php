<?php
require_once "db.php";

// ---- Part 2: intentionally vulnerable price update (NO sanitization) ----
// Accepts POST form fields: id, price — both concatenated directly into SQL.

$id = isset($_POST['id']) ? $_POST['id'] : '';
$price = isset($_POST['price']) ? $_POST['price'] : '';

$sql = "UPDATE products SET price = $price WHERE id = $id";

if ($conn->query($sql) === true) {
    echo json_encode([
        "success" => true,
        "affected_rows" => $conn->affected_rows,
        "sql" => $sql
    ]);
} else {
    http_response_code(400);
    echo json_encode(["error" => $conn->error, "sql" => $sql]);
}

/*
=====================================================================
PAYLOAD TO USE FROM THE FLUTTER UPDATE FORM
=====================================================================

To set every product's price to 1, put this in the "id" field and
"1" in the "price" field:

    id field:    0 OR 1=1
    price field: 1

-> resulting query:
   UPDATE products SET price = 1 WHERE id = 0 OR 1=1

Since "0 OR 1=1" is always true, every row matches WHERE 0 OR 1=1,
so every product's price is set to 1.
=====================================================================
*/
