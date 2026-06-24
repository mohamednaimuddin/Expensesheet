<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
$username = $_SESSION['username'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Vehicle not specified!");
}
$vehicle_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM vehicle WHERE id=?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle_result = $stmt->get_result();
if ($vehicle_result->num_rows === 0) die("Vehicle not found!");
$vehicle = $vehicle_result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle_expense'])) {
    $date        = $_POST['date'];
    $region      = $_POST['region'];
    $service     = $_POST['service'];
    $km_reading  = $_POST['km_reading'];
    $description = $_POST['description'];
    $amount      = $_POST['amount'];
    $bill        = $_POST['bill'];
    $submitted   = 1;

    $stmt = $conn->prepare("INSERT INTO vehicle_expense
        (vehicle_id, username, date, region, service, km_reading, description, amount, bill, submitted, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssisdsi",
        $vehicle_id, $username, $date, $region, $service, $km_reading, $description, $amount, $bill, $submitted);

    if ($stmt->execute()) {
        header("Location: vehicle_details.php?id=" . $vehicle_id);
        exit();
    } else {
        $error = "Failed to add expense: " . $conn->error;
    }
}

$stmt = $conn->prepare("SELECT id, username, date, region, service, km_reading, description, amount, bill
                        FROM vehicle_expense
                        WHERE vehicle_id=? AND submitted=1
                        ORDER BY date ASC");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$expense_result = $stmt->get_result();

$all_expenses = [];
$total_expense = 0;
while ($row = $expense_result->fetch_assoc()) {
    $all_expenses[] = $row;
    $total_expense += $row['amount'];
}

$vehicle_title = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
$vehicle_subtitle = trim(($vehicle['number_plate'] ?? '') . (($vehicle['model_year'] ?? '') !== '' ? ' | ' . $vehicle['model_year'] : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Expense | VisionAngles</title>
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="assets/vendor/bootstrap-5.0.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/user_report.css" rel="stylesheet">
<style>
.vehicle-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.vehicle-detail-card {
    background: rgba(255,255,255,0.74);
    border: 1px solid rgba(255,255,255,0.9);
    border-radius: 16px;
    padding: 13px 15px;
    box-shadow: 0 10px 26px rgba(15,23,42,0.08);
}
.vehicle-detail-card span {
    display: block;
    color: var(--ink-500);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .45px;
    margin-bottom: 4px;
}
.vehicle-detail-card strong {
    color: var(--ink-900);
    font-size: 0.96rem;
}
.vehicle-meta-line {
    color: var(--ink-700);
    font-size: 0.9rem;
    font-weight: 700;
    margin-top: 5px;
}
.total-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 14px;
    border-radius: 999px;
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.26);
    color: #047857;
    font-weight: 800;
    white-space: nowrap;
}
.modal-content {
    border: 1px solid var(--glass-brd);
    border-radius: 18px;
    box-shadow: 0 24px 64px rgba(15,23,42,0.18);
}
.modal {
    z-index: 1060;
}
.modal-backdrop {
    z-index: 1050;
}
.modal-header,
.modal-footer {
    border-color: rgba(15,23,42,0.08);
}
.modal-title {
    color: var(--ink-900);
    font-weight: 800;
}
.vehicle-expense-modal .modal-dialog {
    width: min(560px, calc(100% - 24px));
    margin: 12px auto;
}
.vehicle-expense-modal .modal-content {
    max-height: calc(100vh - 24px);
    overflow: hidden;
}
.vehicle-expense-modal .modal-header {
    align-items: center;
    gap: 12px;
    padding: 18px 20px;
}
.vehicle-expense-modal .modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.2;
}
.vehicle-expense-modal .btn-close {
    width: 1em !important;
    height: 1em !important;
    min-height: 0 !important;
    padding: .75rem !important;
    margin: -.5rem -.5rem -.5rem auto !important;
    flex: 0 0 auto !important;
    background-color: transparent !important;
    border: 0 !important;
    border-radius: .375rem !important;
    box-shadow: none !important;
}
.vehicle-expense-modal .modal-body {
    max-height: calc(100vh - 190px);
    overflow-y: auto;
    padding: 18px 20px;
}
.vehicle-expense-modal .modal-body label {
    display: block;
    margin-bottom: 5px;
    color: var(--ink-700);
    font-size: .86rem;
    font-weight: 600;
}
.vehicle-expense-modal .modal-body .form-control,
.vehicle-expense-modal .modal-body .form-select {
    min-height: 36px;
    border-radius: 8px;
}
.vehicle-expense-modal .modal-body textarea.form-control {
    min-height: 92px;
}
.vehicle-expense-modal .modal-footer {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    padding: 16px 20px;
}
.vehicle-expense-modal .modal-footer .btn-glass {
    width: auto !important;
    flex: 1 1 0;
}
@media (max-width: 420px) {
    .vehicle-expense-modal .modal-dialog {
        width: calc(100% - 16px);
        margin: 8px auto;
    }
    .vehicle-expense-modal .modal-header,
    .vehicle-expense-modal .modal-body,
    .vehicle-expense-modal .modal-footer {
        padding-left: 14px;
        padding-right: 14px;
    }
    .vehicle-expense-modal .modal-title {
        font-size: 1.05rem;
    }
    .vehicle-expense-modal .modal-footer {
        gap: 8px;
    }
    .vehicle-expense-modal .modal-footer .btn-glass {
        padding-left: 10px;
        padding-right: 10px;
    }
}
@page {
    size: A4 landscape;
    margin: 10mm 12mm;
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
    .report-page-shell,
    .report-header,
    .print-header,
    .table-card,
    .report-footer,
    .vehicle-summary {
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
    .report-header-meta,
    .total-chip {
        display: none !important;
    }
    .report-glass-page .report-header h2 {
        width: 100% !important;
        text-align: center !important;
        font-size: 20px !important;
    }
    .vehicle-meta-line {
        text-align: center !important;
        font-size: 10px !important;
    }
    .vehicle-summary {
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 6px !important;
        margin-bottom: 8px !important;
    }
    .vehicle-detail-card {
        border: 1px solid #cbd5e1 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 5px 6px !important;
        background: #fff !important;
    }
    .vehicle-detail-card span {
        font-size: 7px !important;
        margin-bottom: 2px !important;
    }
    .vehicle-detail-card strong {
        font-size: 8.5px !important;
    }
    .print-header {
        font-size: 10px !important;
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
    .table-card tbody td,
    .table-card tfoot td {
        padding: 4px 4px !important;
        font-size: 8.5px !important;
        line-height: 1.2 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }
    .table-card thead th:nth-child(9),
    .table-card tbody td:nth-child(9) {
        display: none !important;
    }
    .table-card thead th:nth-child(1),
    .table-card tbody td:nth-child(1) { width: 5% !important; }
    .table-card thead th:nth-child(2),
    .table-card tbody td:nth-child(2) { width: 10% !important; }
    .table-card thead th:nth-child(3),
    .table-card tbody td:nth-child(3) { width: 10% !important; }
    .table-card thead th:nth-child(4),
    .table-card tbody td:nth-child(4) { width: 13% !important; }
    .table-card thead th:nth-child(5),
    .table-card tbody td:nth-child(5) { width: 11% !important; }
    .table-card thead th:nth-child(6),
    .table-card tbody td:nth-child(6) { width: 26% !important; }
    .table-card thead th:nth-child(7),
    .table-card tbody td:nth-child(7) { width: 13% !important; }
    .table-card thead th:nth-child(8),
    .table-card tbody td:nth-child(8) { width: 12% !important; }
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
      <div>
        <h2><i class="bi bi-truck me-2"></i>Vehicle Expense</h2>
        <div class="vehicle-meta-line"><?= htmlspecialchars($vehicle_title ?: 'Vehicle') ?><?= $vehicle_subtitle ? ' | ' . htmlspecialchars($vehicle_subtitle) : '' ?></div>
      </div>
    </div>
    <div class="report-header-meta">
      <span class="total-chip"><i class="bi bi-cash-stack"></i> SAR <?= number_format($total_expense, 2) ?></span>
    </div>
  </div>

  <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="report-toolbar report-toolbar-actions-left mb-3">
    <div class="toolbar-actions">
      <a href="vehicle.php" class="btn-glass btn-glass-danger"><i class="bi bi-arrow-left"></i> Back</a>
      <button class="btn-glass btn-glass-success" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <button class="btn-glass btn-glass-primary" type="button" data-bs-toggle="modal" data-bs-target="#addExpenseModal"><i class="bi bi-plus-lg"></i> Add Expense</button>
    </div>
    <div class="toolbar-divider d-none d-md-block"></div>
    <div class="toolbar-filter-group justify-content-end">
      <div class="toolbar-field">
        <label class="form-label">Total Expense</label>
        <div class="form-control form-control-sm fw-bold">SAR <?= number_format($total_expense, 2) ?></div>
      </div>
      <div class="toolbar-field">
        <label class="form-label">Records</label>
        <div class="form-control form-control-sm fw-bold"><?= count($all_expenses) ?></div>
      </div>
    </div>
  </div>

  <div class="vehicle-summary">
    <div class="vehicle-detail-card"><span>Owner</span><strong><?= htmlspecialchars($vehicle['vehicle_owner']) ?></strong></div>
    <div class="vehicle-detail-card"><span>Date of Purchase</span><strong><?= htmlspecialchars($vehicle['date_purchase']) ?></strong></div>
    <div class="vehicle-detail-card"><span>Insurance Exp</span><strong><?= htmlspecialchars($vehicle['insurance_exp']) ?></strong></div>
    <div class="vehicle-detail-card"><span>Total Expense</span><strong>SAR <?= number_format($total_expense, 2) ?></strong></div>
  </div>

  <div class="print-header mb-3">
    <div>
      <strong>Prepared By:</strong> <?= htmlspecialchars(ucfirst($username)) ?><br>
      <strong>Vehicle:</strong> <?= htmlspecialchars($vehicle_title ?: '-') ?>
    </div>
    <div>
      <strong>Number Plate:</strong> <?= htmlspecialchars($vehicle['number_plate']) ?><br>
      <strong>Total Records:</strong> <?= count($all_expenses) ?>
    </div>
  </div>

  <div class="table-card">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
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
          <?php if (count($all_expenses) > 0): $i = 1; foreach ($all_expenses as $row): ?>
          <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['date']) ?></td>
              <td><?= htmlspecialchars($row['region']) ?></td>
              <td><?= htmlspecialchars($row['service']) ?></td>
              <td><?= htmlspecialchars($row['km_reading']) ?></td>
              <td><?= htmlspecialchars($row['description']) ?></td>
              <td>SAR <?= number_format($row['amount'], 2) ?></td>
              <td><?= htmlspecialchars($row['bill']) ?></td>
              <td class="actions-col">
                  <div class="d-flex flex-wrap gap-1">
                    <a href="edit_vehicle_expense.php?id=<?= $row['id'] ?>" class="btn-glass btn-glass-secondary" style="padding:3px 10px; min-height:28px; font-size:0.75rem; color:#d97706; border-color:#fcd34d; background:rgba(254,243,199,0.8);"><i class="bi bi-pencil-square"></i> Edit</a>
                    <a href="delete_vehicle_expense.php?id=<?= $row['id'] ?>" class="btn-glass btn-glass-danger" style="padding:3px 10px; min-height:28px; font-size:0.75rem;" onclick="return confirm('Delete this expense?')"><i class="bi bi-trash3-fill"></i> Delete</a>
                  </div>
              </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="9" class="text-center">No expenses found.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
              <td colspan="6" class="text-end fw-bold">Total Expense:</td>
              <td colspan="3">SAR <?= number_format($total_expense, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="report-footer">
      <div>Prepared By: <?= htmlspecialchars(ucfirst($username)) ?></div>
      <div>Verified By:</div>
      <div>Approved By:</div>
  </div>
</div>

<div class="modal fade vehicle-expense-modal" id="addExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="add_vehicle_expense" value="1">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Vehicle Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
            <div class="mb-2"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Region</label>
                <select name="region" class="form-select" required>
                    <option>Dammam</option>
                    <option>Riyadh</option>
                    <option>Jeddah</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="mb-2"><label class="form-label">Service</label>
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
            <div class="mb-2"><label class="form-label">KM Reading</label><input type="number" name="km_reading" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
            <div class="mb-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Bill</label>
                <select name="bill" class="form-select" required>
                    <option>Yes</option>
                    <option>No</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn-glass btn-glass-success"><i class="bi bi-check-circle"></i> Save</button>
            <button type="button" class="btn-glass btn-glass-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap-5.0.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
