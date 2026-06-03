<?php
session_start();
include 'config.php';
include 'log_helper.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (!$id) die("Expense not specified!");

// Get vehicle expense info for logging
$stmt = $conn->prepare("SELECT vehicle_id, service, amount, username FROM vehicle_expense WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();
$vehicle_id = $expense['vehicle_id'] ?? 0;
$expense_info = $expense ? "{$expense['service']} - {$expense['amount']} SAR by {$expense['username']}" : "ID: $id";

$stmt = $conn->prepare("DELETE FROM vehicle_expense WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

logActivity($conn, LOG_DELETE_EXPENSE, "Deleted vehicle expense: $expense_info");

header("Location: vehicle_details.php?id=".$vehicle_id);
exit();
?>
