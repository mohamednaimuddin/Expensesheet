<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get username from session
    $username = $_SESSION['username'];

    // Sanitize and format inputs
    $date = date('Y-m-d', strtotime($_POST['date']));
    $division = $_POST['division'];
    $region = $_POST['region'];
    $company = $_POST['company'];
    $store = $_POST['store'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $bill = $_POST['bill'];

    // Prepare SQL query
    $sql = "INSERT INTO labour_expense 
            (username, date, division, region, company, store, location, description, amount, bill, status, submitted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 0)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "ssssssssis", 
        $username, 
        $date, 
        $division, 
        $region, 
        $company, 
        $store, 
        $location, 
        $description, 
        $amount, 
        $bill
    );

    // Execute query
    if ($stmt->execute()) {
        $_SESSION['success'] = "Labour expense added successfully!";
        header("Location: dashboard_user.php");
        exit();
    } else {
        die("Insert failed: " . $stmt->error);
    }

} else {
    // Redirect if not POST
    header("Location: dashboard_user.php");
    exit();
}
?>
