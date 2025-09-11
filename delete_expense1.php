<?php 
session_start();

// Show errors temporarily for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check user role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// GET parameters
$id = $_GET['id'] ?? '';
$table_param = $_GET['table'] ?? '';
$username = $_SESSION['username'];

if (!is_numeric($id)) die("Invalid ID.");

// Map allowed tables (including Tools and Labour)
$table_map = [
    'food_expense'   => 'food_expense',
    'fuel_expense'   => 'fuel_expense',
    'room_expense'   => 'room_expense',
    'other_expense'  => 'other_expense',
    'tools_expense'  => 'tools_expense',
    'labour_expense' => 'labour_expense',  // corrected key
    'accessories_expense' => 'accessories_expense',
    'tv_expense' => 'tv_expense',
    'vehicle_expense' => 'vehicle_expense'
];

$table_key = strtolower($table_param);
if (!array_key_exists($table_key, $table_map)) die("Invalid table specified.");

$table = $table_map[$table_key];

// Check if expense exists and is not submitted
$stmt = $conn->prepare("SELECT submitted FROM $table WHERE id=? AND username=?");
if (!$stmt) die("Prepare failed: (" . $conn->errno . ") " . $conn->error);

$stmt->bind_param("is", $id, $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) die("Expense not found.");
if ($row['submitted']) die("Cannot delete a submitted expense.");

// Delete expense
$delete_stmt = $conn->prepare("DELETE FROM $table WHERE id=? AND username=?");
if (!$delete_stmt) die("Prepare failed: (" . $conn->errno . ") " . $conn->error);

$delete_stmt->bind_param("is", $id, $username);
$delete_stmt->execute();

header("Location: report.php");
exit();
?>
