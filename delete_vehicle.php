<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'log_helper.php';

// Get Vehicle ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: vehicle.php");
    exit();
}
$id = intval($_GET['id']);

// Get vehicle info before deleting
$stmt = $conn->prepare("SELECT brand, model, number_plate FROM vehicle WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$vehicle_info = $vehicle ? "{$vehicle['brand']} {$vehicle['model']} ({$vehicle['number_plate']})" : "ID: $id";

// Delete Vehicle
$stmt = $conn->prepare("DELETE FROM vehicle WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logActivity($conn, LOG_DELETE_VEHICLE, "Deleted vehicle: $vehicle_info");
    header("Location: vehicle.php?msg=deleted");
    exit();
} else {
    echo "<div class='alert alert-danger text-center'>Error deleting: " . $conn->error . "</div>";
}
