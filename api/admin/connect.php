<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "FAQ";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => "Connection failed: " . $conn->connect_error]));
}
?>
