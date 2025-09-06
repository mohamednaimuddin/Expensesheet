<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

// Fetch expenses function (submitted=1)
function get_expenses($conn, $table, $from_date = '', $to_date = '', $region = 'All') {
    $allowed_tables = ['fuel_expense', 'food_expense', 'room_expense', 'other_expense', 'tools_expense', 'labour_expense']; // ✅ Added labour
    if (!in_array($table, $allowed_tables)) die("Invalid table specified");

    $sql = "SELECT * FROM $table WHERE submitted=1";
    $types = "";
    $params = [];

    if ($from_date && $to_date) {
        $sql .= " AND `date` BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $from_date;
        $params[] = $to_date;
    }

    if ($region !== 'All') {
        $sql .= " AND region=?";
        $types .= "s";
        $params[] = $region;
    }

    $sql .= " ORDER BY `date` ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed: " . $conn->error . " | SQL: " . $sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch all expenses for all users
$tables = [
    'fuel_expense'   => 'Fuel',
    'food_expense'   => 'Food',
    'room_expense'   => 'Room',
    'other_expense'  => 'Other',
    'tools_expense'  => 'Tools',
    'labour_expense' => 'Labour' // ✅ Added labour
];
$all_expenses = [];
$total_amount = 0;

foreach ($tables as $table => $type) {
    $res = get_expenses($conn, $table, $from_date, $to_date, $region_filter);
    while ($row = $res->fetch_assoc()) {
        $row['type'] = $type;
        $all_expenses[] = $row;
        $total_amount += $row['amount'];
    }
}

// Sort all expenses by date ascending
usort($all_expenses, function($a,$b){ return strtotime($a['date'])<=>strtotime($b['date']); });

// Fetch total advances
if ($from_date && $to_date) {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from_date, $to_date);
} else {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt");
}
$stmt->execute();
$total_adv = $stmt->get_result()->fetch_assoc()['total_adv'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Users Expense Report | VisionAngles</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="assets/user_report.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.total-spend { background-color:#f2f2f2; padding:10px; border-radius:5px; text-align:center; font-weight:bold; }
.table { border:2px solid black; border-collapse:collapse; }
th, td { border:0.5px solid black; padding:4px 6px; text-align:left; word-wrap:break-word; }
@media print {
    body { -webkit-print-color-adjust:exact; color-adjust:exact; margin:10mm; font-size:12px; background:#fff; }
    body::before {
        content:""; position:fixed; top:0; left:0; width:100%; height:100%;
        background:url('assets/vision1.png') no-repeat center center;
        background-size:50%; opacity:0.05; z-index:9999; pointer-events:none;
        background-color:#aeb6bd;
    }
    table { width:100%; border-collapse:collapse; border:2px solid black; page-break-inside:auto; }
    thead { display:table-header-group; }
    tfoot { display:table-footer-group; }
    tr { page-break-inside:avoid; page-break-after:auto; }
    th { background-color:#f0f0f0 !important; -webkit-print-color-adjust:exact; color:black; }
    th, td { border:0.5px solid black; padding:4px 6px; font-size:11px; text-align:left; word-wrap:break-word; color:black; }
    button, input, select { display:none !important; }
    .total-summary { display:flex; justify-content:flex-end; text-align:right; margin-top:20px; }
}
</style>
</head>
<body>

<div class="report-header">
    <img src="assets/visionlogo.jpg" alt="Company Logo">
    <h2>All Users Expense Report</h2>
</div>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <!-- From Date -->
    <div class="form-group">
        <input type="date" class="form-control form-control-sm" name="from_date" value="<?= htmlspecialchars($from_date) ?>" required>
    </div>
    <!-- To Date -->
    <div class="form-group">
        <input type="date" class="form-control form-control-sm" name="to_date" value="<?= htmlspecialchars($to_date) ?>" required>
    </div>
    <!-- Region -->
    <div class="form-group">
        <select class="form-select form-select-sm" name="region">
            <?php
            $regions = ['All','Dammam','Riyadh','Jeddah','Other'];
            foreach ($regions as $region) {
                $sel = ($region_filter==$region)?'selected':''; 
                echo "<option value=\"$region\" $sel>$region</option>";
            }
            ?>
        </select>
    </div>
    <!-- Buttons -->
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" type="submit">Search</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.location='all_user_expense.php'">Clear</button>
        <button class="btn btn-outline-success btn-sm" type="button" onclick="window.print()">Print</button>
        <button class="btn btn-danger btn-sm" type="button" onclick="window.location='dashboard_admin.php'">Back</button>
    </div>
</form>

<div class="print-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
    <div><strong>Region:</strong> <?= htmlspecialchars($region_filter) ?><br>
        <strong>From:</strong> <?= htmlspecialchars($from_date) ?> 
        <strong>To:</strong> <?= htmlspecialchars($to_date) ?>
    </div>
    <div style="text-align:right;">
        <strong>Advance:</strong> SAR <?= number_format($total_adv, 2) ?>
    </div>
</div>

<div class="table-responsive">
    <table class="table align-middle">
        <thead class="table" style="background-color:grey;">
            <tr>
                <th>SI. No</th>
                <th>Date</th>
                <th>Username</th>
                <th>Type</th>
                <th>Division</th>
                <th>Company</th>
                <th>Location</th>
                <th>Store</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($all_expenses) > 0): $si=1; foreach($all_expenses as $row): ?>
            <tr>
                <td><?= $si ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['division'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['company'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['location'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['store'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>SAR <?= number_format($row['amount'], 2) ?></td>
                <td><?= htmlspecialchars($row['remark'] ?? '') ?></td>
            </tr>
            <?php $si++; endforeach; else: ?>
            <tr><td colspan="11" class="text-center">No expenses found.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="9" class="text-end fw-bold">Total Spend:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="9" class="text-end fw-bold">Balance:</td>
                <td colspan="2">SAR <?= number_format($total_adv - $total_amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="report-footer">
    <div>Prepared By: Admin</div>
    <div>Verified By:</div>
    <div>Approved By:</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
