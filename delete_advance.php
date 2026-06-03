<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'log_helper.php';

if (!isset($_GET['id'])) {
    die("Advance ID not specified!");
}

$id = $_GET['id'];

// Get advance info before deleting
$stmt = $conn->prepare("SELECT username, adv_amt, date FROM adv_amt WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$advance = $stmt->get_result()->fetch_assoc();
$adv_info = $advance ? "{$advance['adv_amt']} SAR for {$advance['username']} on {$advance['date']}" : "ID: $id";

// Delete advance
$stmt = $conn->prepare("DELETE FROM adv_amt WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

logActivity($conn, LOG_DELETE_ADVANCE, "Deleted advance: $adv_info");

// Redirect back to previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
