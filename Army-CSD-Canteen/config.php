<?php
$host = "localhost"; // or 127.0.0.1
$username = "root";  // default username for XAMPP/WAMP
$password = "";      // default password (empty for XAMPP)
$database = "csd_canteen";

// Connect to database
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
