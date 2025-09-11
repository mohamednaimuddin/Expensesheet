<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','user'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect POST data
    $vehicle_id  = $_POST['vehicle_id'] ?? null;
    $date        = $_POST['date'] ?? null;
    $region      = $_POST['region'] ?? '';
    $service     = $_POST['service'] ?? '';
    $km_reading  = $_POST['km_reading'] ?? 0;
    $description = $_POST['description'] ?? '';
    $amount      = $_POST['amount'] ?? 0;
    $bill        = $_POST['bill'] ?? '';

    // Logged-in username
    $username    = $_SESSION['username'];

    // Basic validation
    if (!$vehicle_id || !$date || !$region || !$service || !$description || !$amount) {
        die("All required fields must be filled!");
    }

    if (!is_numeric($km_reading) || !is_numeric($amount)) {
        die("KM reading and Amount must be numeric!");
    }

    // Insert query with username + submitted default 0
    $stmt = $conn->prepare("INSERT INTO vehicle_expense 
        (vehicle_id, username, date, region, service, km_reading, description, amount, bill, submitted) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "issssisss",
        $vehicle_id,
        $username,
        $date,
        $region,
        $service,
        $km_reading,
        $description,
        $amount,
        $bill
    );

    if ($stmt->execute()) {
        // Redirect based on user role
        if ($_SESSION['role'] === 'admin') {
            header("Location: dashboard_admin.php");
        } else {
            header("Location: dashboard_user.php");
        }
        exit();
    } else {
        die("Execute failed: " . $stmt->error);
    }
}
?>
