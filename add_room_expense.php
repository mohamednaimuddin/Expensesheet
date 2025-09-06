<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }
include 'config.php';

$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = date('Y-m-d', strtotime($_POST['date']));
    $division = $_POST['division'];
    $region = $_POST['region'];
    $company = $_POST['company'];
    $store = $_POST['store'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $bill = $_POST['bill'];

    $stmt = $conn->prepare("INSERT INTO room_expense 
(username,division,date,region,company,store,location,description,amount,bill,created_at) 
VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");

$stmt->bind_param("ssssssssss", 
    $username,$division,$date,$region,$company,$store,$location,$description,$amount,$bill
);

    if($stmt->execute()){
        header("Location: dashboard_user.php?success=room");
    } else {
        echo "Error: ".$stmt->error;
    }
}
