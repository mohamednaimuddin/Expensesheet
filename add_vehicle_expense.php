<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $date = $_POST['date'];
    $region = $_POST['region'];
    $service = $_POST['service'];
    $km_reading = $_POST['km_reading'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $bill = $_POST['bill'];

    $stmt = $conn->prepare("INSERT INTO vehicle_expense (vehicle_id,date,region,service,km_reading,description,amount,bill) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("isssidsd", $vehicle_id, $date, $region, $service, $km_reading, $description, $amount, $bill);
    $stmt->execute();

    header("Location: vehicle_details.php?id=".$vehicle_id);
    exit();
}
?>
