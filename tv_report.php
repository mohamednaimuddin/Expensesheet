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

// Fetch all TV expenses function
function get_tv_expenses($conn, $from_date = '', $to_date = '', $region = 'All') {
    $sql = "SELECT * FROM tv_expense WHERE submitted=1";
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
    if (!$stmt) die("Prepare failed: ".$conn->error);
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch TV expenses
$tv_expenses_result = get_tv_expenses($conn, $from_date, $to_date, $region_filter);

// Calculate total amount
$total_amount = 0;
$all_tv_expenses = [];
while ($row = $tv_expenses_result->fetch_assoc()) {
    $all_tv_expenses[] = $row;
    $total_amount += $row['amount'];
}

// Sort by date ascending
usort($all_tv_expenses, function($a,$b){ return strtotime($a['date']) <=> strtotime($b['date']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TV Expense Report | VisionAngles</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="assets/user_report.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
.table { border: 2px solid black; border-collapse: collapse; }
th, td { border: 0.5px solid black; padding: 4px 6px; word-wrap: break-word; font-size: 11px; text-align:left; }
.total-spend { background-color: #f2f2f2; padding: 10px; border-radius:5px; text-align:center; font-weight:bold; }
@media print {
    body { 
        -webkit-print-color-adjust: exact; 
        color-adjust: exact; 
        margin:10mm; 
        font-size:12px; 
        background:#fff; 
    }
    body::before { 
        content:""; 
        position:fixed; 
        top:0; 
        left:0; 
        width:100%; 
        height:100%; 
        background:url('assets/vision1.png') no-repeat center center; 
        background-size:50%; 
        opacity:0.05; 
        z-index:9999; 
        pointer-events:none; 
        background-color:#aeb6bd; 
    }
    table { width:100%; border-collapse:collapse; border:2px solid black; page-break-inside:auto; }
    thead { display:table-header-group; } 
    tfoot { display:table-footer-group; }
    tr { page-break-inside:avoid; page-break-after:auto; }
    th { background-color:#f0f0f0 !important; color:black; }
    button, input, select, .btn { display:none !important; } /* hide buttons while printing */
}

</style>
</head>
<body>

<div class="report-header">
    <img src="assets/visionlogo.jpg" alt="Company Logo">
    <h2>TV Expense Report</h2>
</div>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="form-group"><input type="date" class="form-control form-control-sm" name="from_date" value="<?= htmlspecialchars($from_date) ?>"></div>
    <div class="form-group"><input type="date" class="form-control form-control-sm" name="to_date" value="<?= htmlspecialchars($to_date) ?>"></div>
    <div class="form-group">
        <select class="form-select form-select-sm" name="region">
            <?php 
            $regions = ['All','Dammam','Riyadh','Jeddah','Other'];
            foreach ($regions as $region) {
                $selected = ($region_filter == $region) ? 'selected' : '';
                echo "<option value=\"$region\" $selected>$region</option>";
            }
            ?>
        </select>
    </div>
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" type="submit">Search</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location='tv_report.php'">Clear</button>
        <button class="btn btn-outline-success btn-sm" type="button" onclick="window.print()">Print</button>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.location.href='dashboard_admin.php'">Back</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table align-middle">
        <thead class="table" style="background-color:grey;">
            <tr>
                <th>SI. No</th>
                <th>Date</th>
                <th>Division</th>
                <th>Company</th>
                <th>Location</th>
                <th>Store</th>
                <th>New/ Repaired</th>
                <th>Description</th>
                <th>Old TV Description</th>
                <th>Amount</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
    <?php if(count($all_tv_expenses) > 0): $si=1; foreach($all_tv_expenses as $row): ?>
    <tr>
        <td><?= $si ?></td>
        <td><?= htmlspecialchars($row['date']) ?></td>
        <td><?= htmlspecialchars($row['division']) ?></td>
        <td><?= htmlspecialchars($row['company']) ?></td>
        <td><?= htmlspecialchars($row['location']) ?></td>
        <td><?= htmlspecialchars($row['store']) ?></td>
        <td><?= htmlspecialchars($row['tv_type']) ?></td>
        <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['updated_description'] ?? '') ?></td>
        <td>SAR <?= number_format($row['amount'], 2) ?></td>
        <td>
            <?= htmlspecialchars($row['remark'] ?? '') ?>
            <div class="mt-1">
                <a href="edit_tv.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="delete_tv.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
            </div>
        </td>
    </tr>
    <?php $si++; endforeach; else: ?>
    <tr><td colspan="11" class="text-center">No TV expenses found.</td></tr>
    <?php endif; ?>
</tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="9" class="text-end fw-bold">Total Spend:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

</body>
</html>
