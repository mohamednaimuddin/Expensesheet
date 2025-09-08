<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Invalid request!");
}

$id = intval($_GET['id']);
$type = $_GET['type'];

$tables = [
    'Fuel'   => 'fuel_expense',
    'Food'   => 'food_expense',
    'Room'   => 'room_expense',
    'Other'  => 'other_expense',
    'Tools'  => 'tools_expense',
    'Labour' => 'labour_expense', // ✅ Added Labour
    'Accessories' => 'accessories_expense',
    'tv' => 'tv_expense'
];

if (!array_key_exists($type, $tables)) {
    die("Invalid expense type!");
}

$table = $tables[$type];

// Fetch username for redirect
$stmt = $conn->prepare("SELECT username FROM $table WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) die("Expense not found!");
$row = $result->fetch_assoc();
$username = $row['username'];

// Delete the expense
$del = $conn->prepare("DELETE FROM $table WHERE id=?");
$del->bind_param("i", $id);
$del->execute();

header("Location: user_report.php?username=" . urlencode($username));
exit();
?>
