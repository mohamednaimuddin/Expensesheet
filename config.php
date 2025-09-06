<?php 
$host = "localhost";   // no extra space
$user = "root";        // your DB username
$pass = "";            // your DB password (default is empty for localhost)
$db   = "vas_angles";  // your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
?>
