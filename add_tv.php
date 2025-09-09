<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect POST data
    $date = date('Y-m-d', strtotime($_POST['date']));
    $division = $_POST['division'];
    $region = $_POST['region'];
    $company = $_POST['company'];
    $store = $_POST['store'];
    $location = $_POST['location'];
    $tv_type = $_POST['tv_type'];
    $description = $_POST['description'] ?? '';
    $updated_description = '';
if (isset($_POST['updated_description']) && !empty($_POST['updated_description'])) {
    $updated_description = $_POST['updated_description'];
}
    $amount = $_POST['amount'];
    $bill = $_POST['bill'] ?? 'No';

    // Prepare and execute insertion
    $stmt = $conn->prepare("INSERT INTO tv_expense (username, date, division, region, company, store, location, tv_type, description, updated_description, amount, bill) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssssssss",
        $username,
        $date,
        $division,
        $region,
        $company,
        $store,
        $location,
        $tv_type,
        $description,
        $updated_description,
        $amount,
        $bill
    );

    if ($stmt->execute()) {
        echo "<script>alert('TV Expense added successfully'); window.location.href='dashboard_user.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    die("Invalid request method.");
}
?>
