<?php
// db.php — shared DB connection
// Adjust host/user/pass to match your MySQL setup.
// Database name is "lab_sec_db".

// PHP 8.1+ defaults mysqli to throwing an exception on SQL errors instead
// of returning false. That crashes the script with an HTML fatal error
// page (the "<br />" you saw), which breaks Flutter's jsonDecode. Turn
// that off so query() just returns false and our own error handling runs.
mysqli_report(MYSQLI_REPORT_OFF);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "lab_sec_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "DB connection failed: " . $conn->connect_error]));
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
