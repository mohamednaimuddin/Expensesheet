<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
$username = $_SESSION['username'];

// Validate vehicle ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Vehicle not specified!");
}
$vehicle_id = intval($_GET['id']);

// Fetch vehicle details
$stmt = $conn->prepare("SELECT * FROM vehicle WHERE id=?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle_result = $stmt->get_result();
if ($vehicle_result->num_rows === 0) die("Vehicle not found!");
$vehicle = $vehicle_result->fetch_assoc();

// Handle Add Vehicle Expense POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle_expense'])) {
    $date        = $_POST['date'];
    $region      = $_POST['region'];
    $service     = $_POST['service'];
    $km_reading  = $_POST['km_reading'];
    $description = $_POST['description'];
    $amount      = $_POST['amount'];
    $bill        = $_POST['bill'];
    $submitted   = 1; // Admin submits directly

    $stmt = $conn->prepare("INSERT INTO vehicle_expense 
        (vehicle_id, username, date, region, service, km_reading, description, amount, bill, submitted, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssisdsi", 
        $vehicle_id, $username, $date, $region, $service, $km_reading, $description, $amount, $bill, $submitted);

    if ($stmt->execute()) {
        // Stay on the same vehicle details page
        header("Location: vehicle_details.php?id=" . $vehicle_id);
        exit();
    } else {
        $error = "Failed to add expense: " . $conn->error;
    }
}

// Fetch vehicle expenses (submitted only)
$stmt = $conn->prepare("SELECT id, username, date, region, service, km_reading, description, amount, bill 
                        FROM vehicle_expense 
                        WHERE vehicle_id=? AND submitted=1 
                        ORDER BY date ASC");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$expense_result = $stmt->get_result();

$all_expenses = [];
$total_expense = 0;
while($row = $expense_result->fetch_assoc()){
    $all_expenses[] = $row;
    $total_expense += $row['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Expense | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<style>
.table { border:2px solid black; border-collapse:collapse; }
th, td { border:0.5px solid black; padding:4px 6px; text-align:left; }
@media print {
    body { -webkit-print-color-adjust: exact; margin:10mm; font-size:12px; background:#fff; }
    body::before { content:""; position:fixed; top:0; left:0; width:100%; height:100%; background:url('assets/vision1.png') no-repeat center center; background-size:50%; opacity:0.05; z-index:9999; pointer-events:none; }
    table { width:100%; border-collapse:collapse; border:2px solid black; }
    th, td { border:0.5px solid black; font-size:11px; padding:4px 6px; }
    button, input, select, nav, .modal, .actions-col { display:none !important; }
    .report-footer { display:flex; justify-content:space-between; margin-top:20px; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>

<div class="container mt-3" id="printSection">

  <div class="text-center mb-3">
      <img src="assets/visionlogo.jpg" alt="Company Logo" style="height:80px;">
      <h2 class="mt-2">Vehicle Expense</h2>
  </div>

  <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-end mb-3 gap-2 no-print">
    <button class="btn btn-success" onclick="printReport()">🖨️ Print</button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">➕ Add Vehicle Expense</button>
    <a href="vehicle.php" class="btn btn-danger">Back</a>
  </div>

  <div class="mb-3">
      <strong>Brand:</strong> <?= htmlspecialchars($vehicle['brand']) ?> |
      <strong>Model:</strong> <?= htmlspecialchars($vehicle['model']) ?> |
      <strong>Number Plate:</strong> <?= htmlspecialchars($vehicle['number_plate']) ?> |
      <strong>Model Year:</strong> <?= htmlspecialchars($vehicle['model_year']) ?> |
      <strong>Owner:</strong> <?= htmlspecialchars($vehicle['vehicle_owner']) ?> |
      <strong>Date of Purchase:</strong> <?= htmlspecialchars($vehicle['date_purchase']) ?> |
      <strong>Insurance Exp:</strong> <?= htmlspecialchars($vehicle['insurance_exp']) ?>
  </div>

  <div class="table-responsive">
  <table class="table table-bordered">
      <thead style="background-color:#f0f0f0;">
          <tr>
              <th>SI</th>
              <th>Date</th>
              <th>Region</th>
              <th>Service</th>
              <th>KM Reading</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Bill</th>
              <th class="actions-col">Actions</th>
          </tr>
      </thead>
      <tbody>
          <?php if(count($all_expenses) > 0): $i=1; foreach($all_expenses as $row): ?>
          <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['date']) ?></td>
              <td><?= htmlspecialchars($row['region']) ?></td>
              <td><?= htmlspecialchars($row['service']) ?></td>
              <td><?= htmlspecialchars($row['km_reading']) ?></td>
              <td><?= htmlspecialchars($row['description']) ?></td>
              <td>SAR <?= number_format($row['amount'],2) ?></td>
              <td><?= htmlspecialchars($row['bill']) ?></td>
              <td class="actions-col no-print">
                  <a href="edit_vehicle_expense.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                  <a href="delete_vehicle_expense.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this expense?')">Delete</a>
              </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="9" class="text-center">No expenses found.</td></tr>
          <?php endif; ?>
      </tbody>
      <tfoot>
          <tr>
              <td colspan="6" class="text-end fw-bold">Total Expense:</td>
              <td colspan="3">SAR <?= number_format($total_expense,2) ?></td>
          </tr>
      </tfoot>
  </table>
  </div>

  <div class="report-footer mt-3">
      <div>Prepared By: <?= ucfirst($username) ?></div>
      <div>Verified By: </div>
      <div>Approved By: </div>
  </div>

</div>

<!-- Add Vehicle Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="add_vehicle_expense" value="1">
        <div class="modal-header">
          <h5 class="modal-title">Add Vehicle Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
            <div class="mb-2"><label>Date</label><input type="date" name="date" class="form-control" required></div>
            <div class="mb-2"><label>Region</label>
                <select name="region" class="form-select" required>
                    <option>Dammam</option>
                    <option>Riyadh</option>
                    <option>Jeddah</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="mb-2"><label>Service</label>
                <select name="service" class="form-select" required>
                    <option>Engine Oil</option>
                    <option>Gear Oil</option>
                    <option>Tyre</option>
                    <option>Brake Pad</option>
                    <option>Brake Oil</option>
                    <option>Fuel Injection</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="mb-2"><label>KM Reading</label><input type="number" name="km_reading" class="form-control" required></div>
            <div class="mb-2"><label>Description</label><textarea name="description" class="form-control" required></textarea></div>
            <div class="mb-2"><label>Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="mb-2"><label>Bill</label>
                <select name="bill" class="form-select" required>
                    <option>Yes</option>
                    <option>No</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-success">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function printReport(){
    var printContents = document.getElementById('printSection').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>
</body>
</html>
