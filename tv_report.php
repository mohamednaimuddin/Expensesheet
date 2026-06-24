<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get filters
$month_filter = $_GET['month'] ?? date('Y-m'); // Default to current month
$region_filter = $_GET['region'] ?? 'All';

function get_tv_expenses($conn, $month = '', $region = 'All') {
    $sql = "SELECT * FROM tv_expense WHERE submitted=1";
    $types = "";
    $params = [];

    if ($month) {
        // Extract year and month for filtering
        $sql .= " AND DATE_FORMAT(`date`, '%Y-%m') = ?";
        $types .= "s";
        $params[] = $month;
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
$tv_expenses_result = get_tv_expenses($conn, $month_filter, $region_filter);

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
<link rel="stylesheet" href="assets/vendor/flatpickr/flatpickr.min.css">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="assets/vendor/bootstrap-5.0.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/user_report.css" rel="stylesheet">
<style>
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
    body::before { display:none !important; }
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
        font-size: 20px !important;
        text-align: center !important;
        width: 100% !important;
    }
    .print-header {
        font-size: 11px !important;
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
        word-break: normal !important;
    }
    .table-card thead th:nth-child(1),
    .table-card tbody td:nth-child(1) { width: 5% !important; }
    .table-card thead th:nth-child(2),
    .table-card tbody td:nth-child(2) { width: 7% !important; }
    .table-card thead th:nth-child(3),
    .table-card tbody td:nth-child(3) { width: 8% !important; }
    .table-card thead th:nth-child(4),
    .table-card tbody td:nth-child(4) { width: 10% !important; }
    .table-card thead th:nth-child(5),
    .table-card tbody td:nth-child(5) { width: 10% !important; }
    .table-card thead th:nth-child(6),
    .table-card tbody td:nth-child(6) { width: 9% !important; }
    .table-card thead th:nth-child(7),
    .table-card tbody td:nth-child(7) { width: 10% !important; }
    .table-card thead th:nth-child(8),
    .table-card tbody td:nth-child(8) { width: 13% !important; }
    .table-card thead th:nth-child(9),
    .table-card tbody td:nth-child(9) { width: 13% !important; }
    .table-card thead th:nth-child(10),
    .table-card tbody td:nth-child(10) { width: 8% !important; }
    .table-card thead th:nth-child(11),
    .table-card tbody td:nth-child(11) { width: 7% !important; }
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
            <h2><i class="bi bi-tv me-2"></i>TV Expense Report</h2>
        </div>
        <div class="report-header-meta">Total Spend: SAR <?= number_format($total_amount, 2) ?></div>
    </div>

<form method="get" class="report-toolbar report-toolbar-actions-left mb-3">
    <div class="toolbar-filter-group">
        <div class="toolbar-field">
            <label for="month" class="form-label mb-1 small">Month</label>
            <input type="month" class="form-control form-control-sm" id="month" name="month" value="<?= htmlspecialchars($month_filter) ?>">
        </div>
        <div class="toolbar-field">
            <label for="region" class="form-label mb-1 small">Region</label>
            <select class="form-select form-select-sm" id="region" name="region">
                <?php
                $regions = ['All','Dammam','Riyadh','Jeddah','Other'];
                foreach ($regions as $region) {
                    $selected = ($region_filter == $region) ? 'selected' : '';
                    echo "<option value=\"$region\" $selected>$region</option>";
                }
                ?>
            </select>
        </div>
        <div class="toolbar-search-actions">
            <button class="btn-glass btn-glass-primary" type="submit">
                <i class="bi bi-search"></i>
                Search
            </button>
            <button type="button" class="btn-glass btn-glass-secondary" onclick="window.location='tv_report.php'">
                <i class="bi bi-x-circle"></i>
                Clear
            </button>
        </div>
    </div>

    <div class="toolbar-divider d-none d-md-block"></div>

    <div class="toolbar-actions">
        <button type="button" class="btn-glass btn-glass-danger" onclick="window.location.href='<?= ($_SESSION['role'] === 'superadmin') ? 'dashboard_superadmin.php' : 'dashboard_admin.php' ?>'">
            <i class="bi bi-house"></i>
            Home
        </button>
        <button class="btn-glass btn-glass-success" type="button" onclick="window.print()">
            <i class="bi bi-printer"></i>
            Print
        </button>
    </div>
</form>

<div class="print-header mb-3">
    <div>
        <strong>Month:</strong> <?= htmlspecialchars($month_filter) ?><br>
        <strong>Region:</strong> <?= htmlspecialchars($region_filter) ?>
    </div>
    <div>
        <strong>Total Records:</strong> <?= count($all_tv_expenses) ?><br>
    </div>
</div>

<div class="table-card">
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
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
</div>

<div class="report-footer">
    <div>Prepared By: Admin</div>
    <div>Verified By:</div>
    <div>Approved By:</div>
</div>

</div>

</body>
</html>
