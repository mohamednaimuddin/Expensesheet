<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand           = $_POST['brand'];
    $model           = $_POST['model'];
    $number_plate    = $_POST['number_plate'];
    $model_year      = $_POST['model_year'];
    $vehicle_owner   = $_POST['vehicle_owner'];
    $date_purchase   = $_POST['date_purchase'];
    $insurance_exp   = $_POST['insurance_exp'];
    $authorised_user = $_POST['authorised_user'];
    $auth_start      = $_POST['auth_start'];
    $auth_expire     = $_POST['auth_expire'];
    $insurance_policy= $_POST['insurance_policy'];
    $estimara_exp    = $_POST['estimara_exp'];
    $fas_exp         = $_POST['fas_exp'];

    $stmt = $conn->prepare("INSERT INTO vehicle 
        (brand, model, number_plate, model_year, vehicle_owner, date_purchase, insurance_exp, authorised_user, auth_start, auth_expire, insurance_policy, estimara_exp, fas_exp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssss", 
        $brand, $model, $number_plate, $model_year, $vehicle_owner, $date_purchase, 
        $insurance_exp, $authorised_user, $auth_start, $auth_expire, $insurance_policy, 
        $estimara_exp, $fas_exp
    );

    if ($stmt->execute()) {
        header("Location: vehicle.php");
        exit();
    } else {
        echo "<div class='alert alert-danger text-center'>Error: " . $conn->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Vehicle | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #4f46e5, #ec4899);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center text-white" href="dashboard_admin.php">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      Vision Angles Security EST.
    </a>
    <div class="d-flex ms-auto align-items-center">
      <span class="text-white me-3">👤 <?php echo ucfirst($username); ?></span>
      <a href="vehicle.php" class="btn btn-secondary me-2">Back</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>
</nav>

<div class="container my-4">
    <h2 class="mb-3">➕ Add Vehicle</h2>
    <form method="POST" class="row g-3">

        <div class="col-md-6">
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Number Plate</label>
            <input type="text" name="number_plate" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Model Year</label>
            <input type="number" name="model_year" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Vehicle Owner</label>
            <input type="text" name="vehicle_owner" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Date of Purchase</label>
            <input type="date" name="date_purchase" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Insurance Expiry</label>
            <input type="date" name="insurance_exp" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Insurance Policy No.</label>
            <input type="text" name="insurance_policy" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Authorised User</label>
            <input type="text" name="authorised_user" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Authorization Start</label>
            <input type="date" name="auth_start" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Authorization Expire</label>
            <input type="date" name="auth_expire" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Estimara Expiry</label>
            <input type="date" name="estimara_exp" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">FAS Expiry (1 yr)</label>
            <input type="date" name="fas_exp" class="form-control">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success">Save Vehicle</button>
            <a href="vehicle.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<footer class="text-center py-3 mt-5">
    <p class="mb-0">© 2025 VisionAngles | Vehicle Management</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
