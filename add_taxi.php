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
    $from_location = trim($_POST['from_location']);
    $to_location = trim($_POST['to_location']);
    $amount = (float)$_POST['amount'];
    $bill = trim($_POST['bill']);

    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO taxi_expense 
        (username, division, date, region, company, store, from_location, to_location, amount, bill, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "ssssssssds",
        $username,
        $division,
        $date,
        $region,
        $company,
        $store,
        $from_location,
        $to_location,
        $amount,
        $bill
    );

    // Execute
    if ($stmt->execute()) {
        header("Location: dashboard_user.php?success=taxi");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
$conn->close();
?>
