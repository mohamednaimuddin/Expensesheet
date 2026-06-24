<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
$username = $_SESSION['username'];

$search = $_GET['search'] ?? '';

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
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="assets/vendor/bootstrap-5.0.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/user_report.css" rel="stylesheet">
<style>
@page {
    size: A4 landscape;
    margin: 10mm 10mm;
}
@media print {
    html, body {
        width: 100%;
        overflow: visible !important;
    }
    body {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 10px !important;
        background: #fff !important;
    }
    body::before { display: none !important; }
    .print-label { display: inline !important; }
    .screen-label { display: none !important; }
    .report-page-shell,
    .report-header,
    .print-header,
    .table-card,
    .report-footer {
        width: 100% !important;
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .report-header {
        margin-bottom: 10px !important;
        padding-bottom: 8px !important;
    }
    .report-title-wrap {
        justify-content: center !important;
        width: 100% !important;
    }
    .report-header-meta {
        display: none !important;
    }
    .report-glass-page .report-header h2 {
        width: 100% !important;
        text-align: center !important;
        font-size: 20px !important;
    }
    .print-header {
        font-size: 10.5px !important;
        margin-bottom: 8px !important;
    }
    .table-card,
    .table-responsive {
        overflow: visible !important;
    }
    .table-card table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }
    .table-card thead th,
    .table-card tbody td {
        padding: 3px 2.5px !important;
        font-size: 7.1px !important;
        line-height: 1.18 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }
    .table-card thead th:nth-child(15),
    .table-card tbody td:nth-child(15) {
        display: none !important;
    }
    .table-card thead th:nth-child(1),
    .table-card tbody td:nth-child(1) { width: 4% !important; }
    .table-card thead th:nth-child(2),
    .table-card tbody td:nth-child(2) { width: 6.5% !important; }
    .table-card thead th:nth-child(3),
    .table-card tbody td:nth-child(3) { width: 6.5% !important; }
    .table-card thead th:nth-child(4),
    .table-card tbody td:nth-child(4) { width: 9% !important; }
    .table-card thead th:nth-child(5),
    .table-card tbody td:nth-child(5) { width: 5.5% !important; }
    .table-card thead th:nth-child(6),
    .table-card tbody td:nth-child(6) { width: 6.5% !important; }
    .table-card thead th:nth-child(7),
    .table-card tbody td:nth-child(7) { width: 8% !important; }
    .table-card thead th:nth-child(8),
    .table-card tbody td:nth-child(8) { width: 7.8% !important; }
    .table-card thead th:nth-child(9),
    .table-card tbody td:nth-child(9) { width: 8.2% !important; }
    .table-card thead th:nth-child(10),
    .table-card tbody td:nth-child(10) { width: 7% !important; }
    .table-card thead th:nth-child(11),
    .table-card tbody td:nth-child(11) { width: 7% !important; }
    .table-card thead th:nth-child(12),
    .table-card tbody td:nth-child(12) { width: 8.8% !important; }
    .table-card thead th:nth-child(13),
    .table-card tbody td:nth-child(13) { width: 7.2% !important; }
    .table-card thead th:nth-child(14),
    .table-card tbody td:nth-child(14) { width: 7% !important; }
    .table-card thead th:nth-child(8),
    .table-card thead th:nth-child(10),
    .table-card thead th:nth-child(11),
    .table-card thead th:nth-child(13),
    .table-card thead th:nth-child(14) {
        white-space: nowrap !important;
        overflow-wrap: normal !important;
    }
    .report-footer {
        margin-top: 22px !important;
        font-size: 10px !important;
    }
}
</style>
</head>
<body class="report-glass-page">

<div class="report-page-shell">
  <div class="report-header">
    <div class="report-title-wrap">
      <img src="assets/visionlogo.jpg" alt="Company Logo">
      <h2><i class="bi bi-truck me-2"></i>Vehicle Report</h2>
    </div>
    <div class="report-header-meta">Total Vehicles: <?= $vehicles->num_rows ?></div>
  </div>

  <form method="get" class="report-toolbar report-toolbar-actions-left mb-3">
    <div class="toolbar-filter-group">
      <div class="toolbar-field" style="min-width:min(420px, 100%);">
        <label class="form-label" for="vehicleSearch">Search</label>
        <input type="text" class="form-control form-control-sm" id="vehicleSearch" name="search" placeholder="Search brand, model, plate, owner..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="toolbar-search-actions">
        <button class="btn-glass btn-glass-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        <a href="vehicle.php" class="btn-glass btn-glass-secondary"><i class="bi bi-x-circle"></i> Clear</a>
      </div>
    </div>
    <div class="toolbar-divider d-none d-md-block"></div>
    <div class="toolbar-actions">
      <a href="<?= ($_SESSION['role'] === 'superadmin') ? 'dashboard_superadmin.php' : 'dashboard_admin.php' ?>" class="btn-glass btn-glass-danger"><i class="bi bi-house"></i> Home</a>
      <button class="btn-glass btn-glass-success" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <a href="add_vehicle.php" class="btn-glass btn-glass-primary"><i class="bi bi-plus-lg"></i> Add Vehicle</a>
    </div>
  </form>

  <div class="print-header mb-3">
    <div>
      <strong>Search:</strong> <?= htmlspecialchars($search ?: 'All Vehicles') ?><br>
      <strong>Prepared By:</strong> <?= htmlspecialchars(ucfirst($username)) ?>
    </div>
    <div>
      <strong>Total Vehicles:</strong> <?= $vehicles->num_rows ?>
    </div>
  </div>

  <div class="table-card">
    <div class="table-responsive">
      <table class="table align-middle" id="vehicleTable">
        <thead>
          <tr>
            <th>SI</th>
            <th>Brand</th>
            <th>Model</th>
            <th><span class="screen-label">Number Plate</span><span class="print-label" style="display:none;">Plate No</span></th>
            <th><span class="screen-label">Model Year</span><span class="print-label" style="display:none;">Year</span></th>
            <th>Owner</th>
            <th><span class="screen-label">Date of Purchase</span><span class="print-label" style="display:none;">Purchase</span></th>
            <th>Insurance Exp</th>
            <th><span class="screen-label">Authorised User</span><span class="print-label" style="display:none;">Auth User</span></th>
            <th>Auth Start</th>
            <th>Auth Expire</th>
            <th><span class="screen-label">Insurance Policy No</span><span class="print-label" style="display:none;">Policy No</span></th>
            <th><span class="screen-label">Estimara Exp</span><span class="print-label" style="display:none;">Estimara</span></th>
            <th><span class="screen-label">FAS Exp</span><span class="print-label" style="display:none;">FAS</span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($vehicles->num_rows > 0): $i = 1; while ($row = $vehicles->fetch_assoc()): ?>
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
                <a href="edit_vehicle.php?id=<?= $row['id'] ?>" class="btn-glass btn-glass-secondary" style="padding:3px 10px; min-height:28px; font-size:0.75rem; color:#d97706; border-color:#fcd34d; background:rgba(254,243,199,0.8);"><i class="bi bi-pencil-square"></i> Edit</a>
                <a href="delete_vehicle.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this vehicle?')" class="btn-glass btn-glass-danger" style="padding:3px 10px; min-height:28px; font-size:0.75rem;"><i class="bi bi-trash3-fill"></i> Delete</a>
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

  <div class="report-footer">
    <div>Prepared By: <?= htmlspecialchars(ucfirst($username)) ?></div>
    <div>Verified By:</div>
    <div>Approved By:</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.vehicle-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && !e.target.closest('a') && !e.target.closest('button')) {
                window.location.href = 'vehicle_details.php?id=' + this.dataset.id;
            }
        });
    });
});
</script>

<script src="assets/vendor/bootstrap-5.0.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
