<?php 
$host = "192.96.210.62";
$user = "admin";         // your DB username
$pass = "NVR@sup2030";   // your DB password
$db   = "visionangles";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
