<?php
// db_connect.php - Database Connection File
$host = "localhost";
$user = "root";
$password = "";
$database = "ticket_reservation_system";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>