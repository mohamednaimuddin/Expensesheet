<?php
session_start();
if (!isset($_SESSION['username'])) { 
    header("Location: index.php"); 
    exit(); 
}
include 'config.php';

$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = date('Y-m-d', strtotime($_POST['date']));
    $division = trim($_POST['division']); 
    $region = trim($_POST['region']);
    $company = trim($_POST['company']);
    $store = trim($_POST['store']);
    $location = trim($_POST['location']);
    $description = htmlspecialchars(trim($_POST['description']));
    $amount = floatval($_POST['amount']);
    $bill = trim($_POST['bill']); // If uploading, handle with $_FILES
    $status = "Pending"; // default status when added
    $submitted = "Yes";  // mark as submitted when user adds

    $stmt = $conn->prepare("INSERT INTO accessories_expense 
        (username, date, division, region, company, store, location, description, amount, bill, created_at, status, submitted) 
        VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,?)");

    $stmt->bind_param("ssssssssssss", 
        $username, $date, $division, $region, $company, $store, $location, 
        $description, $amount, $bill, $status, $submitted
    );

    if ($stmt->execute()) {
        header("Location: dashboard_user.php?success=accessories");
        exit();
    } else {
        header("Location: dashboard_user.php?error=" . urlencode($stmt->error));
        exit();
    }
}
?>
