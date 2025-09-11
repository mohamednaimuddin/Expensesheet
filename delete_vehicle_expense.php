<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (!$id) die("Expense not specified!");

// Get vehicle_id for redirect
$stmt = $conn->prepare("SELECT vehicle_id FROM vehicle_expense WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$vehicle_id = $stmt->get_result()->fetch_assoc()['vehicle_id'] ?? 0;

$stmt = $conn->prepare("DELETE FROM vehicle_expense WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: vehicle_details.php?id=".$vehicle_id);
exit();
?>
