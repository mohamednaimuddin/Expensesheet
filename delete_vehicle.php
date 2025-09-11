<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get Vehicle ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: vehicle.php");
    exit();
}
$id = intval($_GET['id']);

// Delete Vehicle
$stmt = $conn->prepare("DELETE FROM vehicle WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: vehicle.php?msg=deleted");
    exit();
} else {
    echo "<div class='alert alert-danger text-center'>Error deleting: " . $conn->error . "</div>";
}
