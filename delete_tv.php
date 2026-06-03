<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'log_helper.php';

// Validate TV expense ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("TV expense ID is required.");
}

$id = intval($_GET['id']);

// Get TV expense info before deleting
$stmt = $conn->prepare("SELECT tv_type, location, amount, username FROM tv_expense WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$tv = $stmt->get_result()->fetch_assoc();
$tv_info = $tv ? "{$tv['tv_type']} at {$tv['location']} ({$tv['amount']} SAR) by {$tv['username']}" : "ID: $id";

// Delete TV expense
$stmt = $conn->prepare("DELETE FROM tv_expense WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logActivity($conn, 'DELETE_TV', "Deleted TV expense: $tv_info");
    header("Location: tv_report.php?deleted=1");
    exit();
} else {
    die("Failed to delete TV expense: " . $conn->error);
}
?>
