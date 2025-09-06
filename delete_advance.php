<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id'])) {
    die("Advance ID not specified!");
}

$id = $_GET['id'];

// Delete advance
$stmt = $conn->prepare("DELETE FROM adv_amt WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

// Redirect back to previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
