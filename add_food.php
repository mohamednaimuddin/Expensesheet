<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $date = date('Y-m-d', strtotime($_POST['date']));
    $division = trim($_POST['division']);
    $region = trim($_POST['region']);
    $company = trim($_POST['company']);
    $store = trim($_POST['store']);
    $location = trim($_POST['location']);
    $people = (int)$_POST['people'];
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $bill = trim($_POST['bill']);

    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO food_expense 
        (username, division, date, region, company, store, location, people, description, amount, bill, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters correctly
    $stmt->bind_param(
        "sssssssisss",
        $username,
        $division,
        $date,
        $region,
        $company,
        $store,
        $location,
        $people,
        $description,
        $amount,
        $bill
    );

    // Execute
    if ($stmt->execute()) {
        header("Location: dashboard_user.php?success=food");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
$conn->close();
?>
