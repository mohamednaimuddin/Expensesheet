<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id']) || !isset($_GET['table'])) {
    die("Invalid request.");
}

$id = intval($_GET['id']);
$table = strtolower($_GET['table']); // force lowercase
$username = $_SESSION['username'];

// Validate table name to prevent SQL injection
$valid_tables = ['fuel_expense', 'food_expense', 'room_expense', 'other_expense', 'tools_expense', 'labour_expense', 'accessories_expense'];
if (!in_array($table, $valid_tables)) {
    die("Invalid table.");
}

// Check ownership and update submitted column safely
$stmt = $conn->prepare("UPDATE $table SET submitted = 1 WHERE id = ? AND username = ?");
$stmt->bind_param("is", $id, $username);
$stmt->execute();

header("Location: report.php"); // redirect back to user report
exit();
?>
