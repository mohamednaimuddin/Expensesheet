<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Validate TV expense ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("TV expense ID is required.");
}

$id = intval($_GET['id']);

// Delete TV expense
$stmt = $conn->prepare("DELETE FROM tv_expense WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: tv_report.php?deleted=1");
    exit();
} else {
    die("Failed to delete TV expense: " . $conn->error);
}
?>
