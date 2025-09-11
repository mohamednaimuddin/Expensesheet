<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
$username = $_SESSION['username'];

// Search
$search = $_GET['search'] ?? '';

// Fetch vehicles
$sql = "SELECT * FROM vehicle WHERE 1=1";
$params = [];
$types  = "";

if ($search) {
    $sql .= " AND (brand LIKE ? OR model LIKE ? OR number_plate LIKE ? OR vehicle_owner LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types  = "ssss";
}

$sql .= " ORDER BY date_purchase DESC";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vehicles = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Report | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<style>
.table { border: 2px solid black; border-collapse: collapse; }
th, td { border: 0.5px solid black; padding: 4px 6px; text-align: left; }
@media print {
  body { -webkit-print-color-adjust: exact; margin: 10mm; font-size: 12px; }
  table { width:100%; border:2px solid black; }
  th,td { border:0.5px solid black; font-size:11px; }
  button, input, select, .btn { display:none !important; }
}
</style>
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
      <span class="text-white me-3">👤 <?= ucfirst($username) ?></span>
      <a href="add_vehicle.php" class="btn btn-success me-2">➕ Add Vehicle</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>
</nav>

<div class="container mt-3">
  <div class="report-header d-flex justify-content-between align-items-center mb-3">
    <h2>🚗 Vehicle Report</h2>
    <div style="font-weight:bold;">Total Vehicles: <?= $vehicles->num_rows ?></div>
  </div>

  <!-- Search Bar -->
  <form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <input type="text" class="form-control form-control-sm" name="search" placeholder="Search brand, model, plate, owner..."
           value="<?= htmlspecialchars($search) ?>">
    <div class="btn-group">
      <button class="btn btn-outline-primary btn-sm" type="submit">Search</button>
      <a href="vehicle.php" class="btn btn-outline-secondary btn-sm">Clear</a>
      <button class="btn btn-outline-success btn-sm" type="button" onclick="window.print()">Print</button>
      <button class="btn btn-outline-success btn-sm" type="button"
        onclick="window.location='export_vehicle_excel.php?search=<?= urlencode($search) ?>'">
        Export
      </button>
      <a href="dashboard_admin.php" class="btn btn-danger btn-sm">Back</a>
    </div>
  </form>

  <!-- Vehicle Table -->
  <div class="table-responsive">
    <table class="table align-middle" id="vehicleTable">
      <thead style="background-color:grey; color:white;">
        <tr>
          <th>SI</th>
          <th>Brand</th>
          <th>Model</th>
          <th>Number Plate</th>
          <th>Model Year</th>
          <th>Owner</th>
          <th>Date of Purchase</th>
          <th>Insurance Exp</th>
          <th>Authorised User</th>
          <th>Auth Start</th>
          <th>Auth Expire</th>
          <th>Insurance Policy No</th>
          <th>Estimara Exp</th>
          <th>FAS Exp</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($vehicles->num_rows > 0): $i=1; while($row = $vehicles->fetch_assoc()): ?>
        <tr class="vehicle-row" data-id="<?= $row['id'] ?>" style="cursor:pointer;">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['brand']) ?></td>
          <td><?= htmlspecialchars($row['model']) ?></td>
          <td><?= htmlspecialchars($row['number_plate']) ?></td>
          <td><?= htmlspecialchars($row['model_year']) ?></td>
          <td><?= htmlspecialchars($row['vehicle_owner']) ?></td>
          <td><?= htmlspecialchars($row['date_purchase']) ?></td>
          <td><?= htmlspecialchars($row['insurance_exp']) ?></td>
          <td><?= htmlspecialchars($row['authorised_user']) ?></td>
          <td><?= htmlspecialchars($row['auth_start']) ?></td>
          <td><?= htmlspecialchars($row['auth_expire']) ?></td>
          <td><?= htmlspecialchars($row['insurance_policy']) ?></td>
          <td><?= htmlspecialchars($row['estimara_exp']) ?></td>
          <td><?= htmlspecialchars($row['fas_exp']) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <a href="edit_vehicle.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
              <a href="delete_vehicle.php?id=<?= $row['id'] ?>" 
                 onclick="return confirm('Delete this vehicle?')" class="btn btn-danger btn-sm">Delete</a>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="15" class="text-center">No vehicles found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.vehicle-row');
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Prevent navigation if user clicks on Edit/Delete buttons
            if(e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && !e.target.closest('a') && !e.target.closest('button')) {
                const vehicleId = this.dataset.id;
                window.location.href = 'vehicle_details.php?id=' + vehicleId;
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
