<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get expense ID
$expense_id = $_GET['id'] ?? '';
if (!$expense_id) die("Expense not specified!");

// Fetch the expense
$stmt = $conn->prepare("SELECT * FROM vehicle_expense WHERE id=?");
$stmt->bind_param("i", $expense_id);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();
if (!$expense) die("Expense not found!");

// Fetch vehicle for redirect after update
$vehicle_id = $expense['vehicle_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $region = $_POST['region'];
    $service = $_POST['service'];
    $km_reading = $_POST['km_reading'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $bill = $_POST['bill'];

    $stmt = $conn->prepare("UPDATE vehicle_expense SET date=?, region=?, service=?, km_reading=?, description=?, amount=?, bill=? WHERE id=?");
    $stmt->bind_param("sssisdsi", $date, $region, $service, $km_reading, $description, $amount, $bill, $expense_id);
    $stmt->execute();

    header("Location: vehicle_details.php?id=$vehicle_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Vehicle Expense | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
</head>
<body>

<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #4f46e5, #ec4899);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center text-white" href="dashboard_admin.php">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      Vision Angles Security EST.
    </a>
    <a href="vehicle_details.php?id=<?= $vehicle_id ?>" class="btn btn-danger ms-auto">Back</a>
  </div>
</nav>

<div class="container mt-3">
  <h2>Edit Vehicle Expense</h2>
  <div class="card">
    <div class="card-body">
      <form method="post">
        <div class="mb-2">
          <label>Date</label>
          <input type="date" name="date" class="form-control" required value="<?= htmlspecialchars($expense['date']) ?>">
        </div>
        <div class="mb-2">
          <label>Region</label>
          <select name="region" class="form-select" required>
            <?php
            $regions = ['Dammam', 'Jeddah', 'Riyadh', 'Other'];
            foreach ($regions as $r) {
                $sel = $expense['region'] === $r ? 'selected' : '';
                echo "<option value='$r' $sel>$r</option>";
            }
            ?>
          </select>
        </div>
        <div class="mb-2">
          <label>Service</label>
          <select name="service" class="form-select" required>
            <?php
            $services = ['Engine Oil', 'Gear Oil', 'Tyre', 'Brake Pad', 'Brake Oil', 'Other'];
            foreach ($services as $s) {
                $sel = $expense['service'] === $s ? 'selected' : '';
                echo "<option value='$s' $sel>$s</option>";
            }
            ?>
          </select>
        </div>
        <div class="mb-2">
          <label>KM Reading</label>
          <input type="number" name="km_reading" class="form-control" required value="<?= htmlspecialchars($expense['km_reading']) ?>">
        </div>
        <div class="mb-2">
          <label>Description</label>
          <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($expense['description']) ?></textarea>
        </div>
        <div class="mb-2">
          <label>Amount (SAR)</label>
          <input type="number" step="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars($expense['amount']) ?>">
        </div>
        <div class="mb-2">
          <label>Bill</label>
          <select name="bill" class="form-select" required>
            <option value="Yes" <?= $expense['bill'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
            <option value="No" <?= $expense['bill'] === 'No' ? 'selected' : '' ?>>No</option>
          </select>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-success">Update Expense</button>
          <a href="vehicle_details.php?id=<?= $vehicle_id ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
