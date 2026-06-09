<?php 
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

function bind_and_execute($stmt, string $types = '', array $params = []) {
    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function table_has_column($conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

// --- Handle AJAX update for bills ---
if (!empty($_POST['update_bill']) && isset($_POST['id'], $_POST['category'])) {
    $id = (int)$_POST['id'];
    $category = $_POST['category'];
    $bill_value = $_POST['update_bill'] === 'yes' ? 'Yes' : 'No';

    $table_map = [
    'Fuel'        => 'fuel_expense',
    'Room'        => 'room_expense',
    'Other'       => 'other_expense',
    'Tools'       => 'tools_expense',
    'Labour'      => 'labour_expense',
    'Accessories' => 'accessories_expense',
    'TV'          => 'tv_expense',
    'Tv'          => 'tv_expense',
    'Vehicle'     => 'vehicle_expense',
    'Taxi'        => 'taxi_expense'
    ];


    if (isset($table_map[$category])) {
        $stmt = $conn->prepare("UPDATE {$table_map[$category]} SET bill=? WHERE id=?");
        $stmt->bind_param("si", $bill_value, $id);
        $stmt->execute();
        echo 'success';
        exit;
    }
}

// --- Initialize filters ---
$month_year_filter = $_GET['month_year'] ?? date('Y-m');
$username_filter = trim($_GET['username'] ?? '');
$region_filter = $_GET['region'] ?? 'All';

// --- Prepare filters ---
$role = $_SESSION['role'];
$session_username = $_SESSION['username'] ?? '';
$back_url = ($role === 'superadmin') ? 'dashboard_superadmin.php' : 'dashboard_admin.php';

$selected_month = null;
$month_start = null;
$month_end = null;
if (preg_match('/^\d{4}-\d{2}$/', $month_year_filter)) {
    $selected_month = $month_year_filter;
    $month_start = $selected_month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
}

$admin_company_id = null;
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT company_id FROM users WHERE username=? LIMIT 1");
    $result_company = bind_and_execute($stmt, "s", [$session_username]);
    $admin_company_id = $result_company->fetch_assoc()['company_id'] ?? null;
}

// --- Fetch users for dropdown, matching dashboard scope ---
$usernames = [];
$has_active = table_has_column($conn, 'users', 'is_active');
$active_sql = $has_active ? " AND is_active=1" : "";
if ($role === 'admin' && $admin_company_id !== null) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE role='user' AND company_id=? $active_sql ORDER BY username ASC");
    $user_res = bind_and_execute($stmt, "s", [$admin_company_id]);
} else {
    $stmt = $conn->prepare("SELECT username FROM users WHERE role='user' $active_sql ORDER BY username ASC");
    $user_res = bind_and_execute($stmt);
}
while ($row = $user_res->fetch_assoc()) {
    if (!empty($row['username'])) {
        $usernames[$row['username']] = $row['username'];
    }
}

function pending_where_for(string $alias, bool $has_region, ?string $month_start, ?string $month_end, string $username_filter, string $region_filter, string $role, ?string $admin_company_id, string &$types, array &$params): string {
    $where = ["($alias.bill IS NULL OR TRIM($alias.bill)='' OR LOWER($alias.bill) IN ('no','pending','n','0'))"];

    if ($month_start && $month_end) {
        $where[] = "$alias.date BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $month_start;
        $params[] = $month_end;
    }

    if ($username_filter !== '') {
        $where[] = "$alias.username = ?";
        $types .= "s";
        $params[] = $username_filter;
    }

    if ($region_filter !== '' && $region_filter !== 'All') {
        if (!$has_region) {
            $where[] = "1=0";
        } else {
            $where[] = "$alias.region = ?";
            $types .= "s";
            $params[] = $region_filter;
        }
    }

    if ($role === 'admin' && $admin_company_id !== null) {
        $where[] = "u.company_id = ?";
        $types .= "s";
        $params[] = $admin_company_id;
    }

    return implode(" AND ", $where);
}

function user_join_for(string $alias, string $role): string {
    return $role === 'admin'
        ? " INNER JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = $alias.username COLLATE utf8mb4_unicode_ci"
        : "";
}

// --- Fetch pending bills ---
$types = "";
$params = [];
$selects = [];
$regular_tables = [
    'Fuel'        => 'fuel_expense',
    'Room'        => 'room_expense',
    'Other'       => 'other_expense',
    'Tools'       => 'tools_expense',
    'Labour'      => 'labour_expense',
    'Accessories' => 'accessories_expense',
    'TV'          => 'tv_expense',
];

foreach ($regular_tables as $label => $table) {
    $has_region = table_has_column($conn, $table, 'region');
    $join = user_join_for('e', $role);
    $where = pending_where_for('e', $has_region, $month_start, $month_end, $username_filter, $region_filter, $role, $admin_company_id, $types, $params);
    $selects[] = "
            SELECT '$label' AS expense_category, e.id, e.date,
                   e.division COLLATE utf8mb4_unicode_ci AS division,
                   e.company COLLATE utf8mb4_unicode_ci AS company,
                   e.store COLLATE utf8mb4_unicode_ci AS store,
                   e.location COLLATE utf8mb4_unicode_ci AS location,
                   e.description COLLATE utf8mb4_unicode_ci AS description,
                   e.amount, e.bill COLLATE utf8mb4_unicode_ci AS bill
            FROM `$table` e
            $join
            WHERE $where
        ";
}

$join = user_join_for('ve', $role);
$where = pending_where_for('ve', false, $month_start, $month_end, $username_filter, $region_filter, $role, $admin_company_id, $types, $params);
$selects[] = "
        SELECT 'Vehicle' AS expense_category, ve.id, ve.date,
               '' COLLATE utf8mb4_unicode_ci AS division,
               '' COLLATE utf8mb4_unicode_ci AS company,
               '' COLLATE utf8mb4_unicode_ci AS store,
               '' COLLATE utf8mb4_unicode_ci AS location,
               CONCAT(IFNULL(v.model,''), ' - ', IFNULL(v.number_plate,''), ' - ', ve.service, IF(ve.description IS NOT NULL AND ve.description <> '', CONCAT(' - ', ve.description), '')) COLLATE utf8mb4_unicode_ci AS description,
               ve.amount, ve.bill COLLATE utf8mb4_unicode_ci AS bill
        FROM vehicle_expense ve
        LEFT JOIN vehicle v ON ve.vehicle_id = v.id
        $join
        WHERE $where
    ";

$has_taxi_region = table_has_column($conn, 'taxi_expense', 'region');
$join = user_join_for('te', $role);
$where = pending_where_for('te', $has_taxi_region, $month_start, $month_end, $username_filter, $region_filter, $role, $admin_company_id, $types, $params);
$selects[] = "
        SELECT 'Taxi' AS expense_category, te.id, te.date,
               te.division COLLATE utf8mb4_unicode_ci AS division,
               te.company COLLATE utf8mb4_unicode_ci AS company,
               te.store COLLATE utf8mb4_unicode_ci AS store,
               '' COLLATE utf8mb4_unicode_ci AS location,
               CONCAT(te.from_location, ' -> ', te.to_location) COLLATE utf8mb4_unicode_ci AS description,
               te.amount, te.bill COLLATE utf8mb4_unicode_ci AS bill
        FROM taxi_expense te
        $join
        WHERE $where
    ";

$query = implode("\nUNION ALL\n", $selects) . "\nORDER BY date ASC";
$stmt = $conn->prepare($query);
if (!$stmt) die("Query failed: " . $conn->error);
$result = bind_and_execute($stmt, $types, $params);

$rows = [];
$total_amount = 0.0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total_amount += (float)$row['amount'];
}
$pending_count = count($rows);
$selected_month_label = $selected_month ? date('F, Y', strtotime($selected_month . '-01')) : 'All Months';


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Bills | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/dashboard_admin.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="icon" type="image/png" href="assets/vision.ico">

<style>
.pending-page .glass-card > * { position: relative; z-index: 1; }
.pending-hero {
    padding: clamp(20px, 3vw, 34px);
}
.pending-hero-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    align-items: center;
}
.pending-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: var(--blue-600);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-size: .78rem;
}
.pending-hero h1 {
    font-family: 'Poppins', 'Inter', sans-serif;
    font-weight: 800;
    margin: 0;
    font-size: clamp(1.6rem, 3vw, 2.5rem);
}
.pending-hero p {
    color: var(--ink-500);
    margin: 8px 0 0;
}
.pending-total {
    min-width: 190px;
    border-radius: 18px;
    padding: 16px 18px;
    background: rgba(37, 99, 235, .09);
    border: 1px solid rgba(37, 99, 235, .14);
    text-align: right;
}
.pending-total span {
    display: block;
    color: var(--ink-500);
    font-weight: 700;
    font-size: .78rem;
    text-transform: uppercase;
}
.pending-total strong {
    display: block;
    color: var(--ink-900);
    font-size: 1.7rem;
    line-height: 1.1;
}
.pending-filter {
    padding: 18px;
}
.pending-filter label {
    color: var(--ink-700);
    font-weight: 800;
    font-size: .82rem;
    margin-bottom: 8px;
}
.pending-filter .form-control,
.pending-filter .form-select {
    border: 1px solid rgba(15, 23, 42, .12);
    border-radius: 14px;
    min-height: 46px;
    color: var(--ink-900);
    font-weight: 600;
    background-color: rgba(255, 255, 255, .82);
}
.pending-filter .form-control:focus,
.pending-filter .form-select:focus {
    border-color: var(--blue-500);
    box-shadow: 0 0 0 .2rem rgba(59, 130, 246, .15);
}
.btn-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 999px;
    min-height: 44px;
    padding: 9px 18px;
    font-weight: 800;
}
.pending-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 16px 0 0;
}
.pending-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 8px 12px;
    background: rgba(255,255,255,.72);
    border: 1px solid rgba(15,23,42,.08);
    color: var(--ink-700);
    font-weight: 700;
    font-size: .9rem;
}
.pending-table-card {
    padding: 0;
    overflow: hidden;
}
.pending-table-card .table-responsive {
    border-radius: 22px;
}
.pending-table {
    margin: 0;
    color: var(--ink-900);
}
.pending-table thead th {
    background: rgba(15, 23, 42, .92);
    color: #fff;
    border: none;
    padding: 14px 12px;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.pending-table tbody td {
    background: rgba(255,255,255,.74);
    border-color: rgba(15,23,42,.08);
    padding: 13px 12px;
    vertical-align: middle;
}
.pending-table tbody tr.pending td {
    background: rgba(255, 251, 235, .9);
}
.category-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 6px 10px;
    background: rgba(37, 99, 235, .1);
    color: var(--blue-600);
    font-weight: 800;
    font-size: .84rem;
}
.amount-cell {
    font-weight: 900;
    white-space: nowrap;
}
.empty-state {
    padding: 40px 18px;
    text-align: center;
    color: var(--ink-500);
    font-weight: 700;
}
.report-footer { display: none; }
@media (max-width: 768px) {
    .pending-hero-grid { grid-template-columns: 1fr; }
    .pending-total { text-align: left; min-width: 0; }
    .pending-filter .btn-pill { width: 100%; }
}

@media print {
    /* Reset and optimize page layout */
    * { box-sizing: border-box; }
    body, html { 
        height: auto !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        background: #fff !important; 
        -webkit-print-color-adjust: exact; 
        font-size: 11px !important;
        line-height: 1.2 !important;
    }
    
    /* Hide non-essential elements */
    .no-print, #filterForm, .action-btn, .print-btn, .back-btn, .btn, button, .glass-nav, .bg-blobs { display: none !important; }
    .container, .container-fluid, .app-container { margin: 0 !important; padding: 5mm !important; max-width: 100% !important; }
    .my-4, .mb-4, .mb-3, .mb-2 { margin: 0 !important; }
    
    /* Optimize header */
    .print-header { display: block !important; margin-bottom: 8px !important; page-break-inside: avoid; text-align: center; }
    .print-header img { max-height: 50px !important; }
    .print-header h2 { margin: 5px 0 !important; font-size: 18px !important; font-weight: bold; }
    
    /* Optimize info section */
    .d-flex.justify-content-between { 
        margin-bottom: 8px !important; 
        font-size: 10px !important;
        page-break-inside: avoid;
        border-bottom: 1px solid #ccc;
        padding-bottom: 5px;
    }
    
    /* Optimize table layout */
    .print-section { 
        display: block !important; 
        page-break-inside: auto; 
        margin: 0 !important; 
        padding: 0 !important;
    }
    .table-responsive { 
        overflow: visible !important; 
        page-break-inside: auto; 
        margin: 0 !important;
    }
    
    /* Table styling - A4 optimized */
    table { 
        width: 100% !important; 
        border-collapse: collapse !important; 
        table-layout: fixed !important; 
        font-size: 9px !important; 
        page-break-inside: auto !important;
        margin: 0 !important;
    }
    
    th, td { 
        border: 0.5px solid #000 !important; 
        padding: 3px 4px !important; 
        word-wrap: break-word !important; 
        white-space: normal !important;
        vertical-align: top !important;
        overflow: hidden !important;
    }
    
    /* Table header */
    thead { display: table-header-group !important; }
    thead th { 
        background-color: #f0f0f0 !important; 
        font-weight: bold !important;
        font-size: 9px !important;
        text-align: center !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact !important;
    }
    
    /* Column specific widths for A4 */
    th:nth-child(1), td:nth-child(1) { width: 6% !important; } /* SI No */
    th:nth-child(2), td:nth-child(2) { width: 9% !important; } /* Date */
    th:nth-child(3), td:nth-child(3) { width: 8% !important; } /* Type */
    th:nth-child(4), td:nth-child(4) { width: 11% !important; } /* Division */
    th:nth-child(5), td:nth-child(5) { width: 13% !important; } /* Company */
    th:nth-child(6), td:nth-child(6) { width: 11% !important; } /* Location */
    th:nth-child(7), td:nth-child(7) { width: 10% !important; } /* Store */
    th:nth-child(8), td:nth-child(8) { width: 22% !important; } /* Description */
    th:nth-child(9), td:nth-child(9) { width: 8% !important; } /* Amount */
    th:nth-child(10), td:nth-child(10) { width: 6% !important; } /* Remark */
    
    /* Table footer */
    tfoot { display: table-footer-group !important; }
    tfoot td { 
        font-weight: bold !important; 
        background-color: #f8f8f8 !important;
        font-size: 10px !important;
    }
    
    /* Row optimization */
    tr { page-break-inside: avoid !important; }
    
    /* Footer optimization */
    .report-footer { 
        display: block !important; 
        margin-top: 15px !important; 
        page-break-inside: avoid !important; 
        font-size: 10px !important;
        width: 100% !important;
        border-top: 1px solid #ccc;
        padding-top: 8px;
    }
    
    /* Page settings for A4 */
    @page { 
        size: A4 portrait !important; 
        margin: 10mm 8mm !important; 
    }
    
    /* Remove any extra spacing */
    .pending { background-color: #fff3cd !important; }
    
    /* Ensure no horizontal overflow */
    .table-responsive { max-width: 100% !important; }
}
</style>

<script>
$(document).ready(function(){
    let pendingUpdate = null;

    $('.action-btn').click(function(){
        const btn = $(this);
        const id = btn.data('id');
        const category = btn.data('category');
        const update_bill = btn.data('value');

        if(update_bill === 'yes'){
            pendingUpdate = {btn, id, category, update_bill};
            $('#confirmModal').modal('show');
        } else {
            updateBill(btn, id, category, update_bill);
        }
    });

    $('#confirmYesBtn').click(function(){
        if(pendingUpdate){
            updateBill(pendingUpdate.btn, pendingUpdate.id, pendingUpdate.category, pendingUpdate.update_bill);
            $('#confirmModal').modal('hide');
            pendingUpdate = null;
        }
    });

    function updateBill(btn, id, category, update_bill){
        $.post('', {id, category, update_bill}, function(response){
            if(response === 'success'){
                btn.closest('td').text(update_bill === 'yes' ? 'Yes' : 'No');
            } else {
                alert('Update failed');
            }
        });
    }

    // Print optimization for A4
    window.addEventListener('beforeprint', function() {
        // Ensure proper sizing for A4
        document.body.style.fontSize = '11px';
        document.body.style.lineHeight = '1.2';
        
        // Adjust table if needed
        const table = document.querySelector('table');
        if (table) {
            table.style.fontSize = '9px';
        }
    });
    
    window.addEventListener('afterprint', function() {
        // Reset styles after printing
        document.body.style.fontSize = '';
        document.body.style.lineHeight = '';
        
        const table = document.querySelector('table');
        if (table) {
            table.style.fontSize = '';
        }
    });
});
</script>
</head>
<body>

<div class="bg-blobs no-print" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
</div>

<nav class="navbar navbar-expand-lg glass-nav no-print">
  <div class="container-fluid app-container">
    <a class="navbar-brand d-flex align-items-center" href="<?= htmlspecialchars($back_url) ?>">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    <div class="navbar-nav ms-auto d-flex align-items-center flex-row gap-2">
        <span class="navbar-text user-pill"><i class="bi bi-person-circle"></i> <?= htmlspecialchars(ucfirst($session_username)) ?></span>
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-add-user"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>
</nav>

<div class="container-fluid app-container my-4 pending-page">

    <div class="text-center mb-2 print-header d-none">
        <img src="assets/visionlogo.jpg" alt="Company Logo" style="max-height:80px;">
        <h2 class="mb-1">No Bill Payment Slip</h2>
    </div>

    <section class="glass-card pending-hero mb-4 no-print">
        <div class="pending-hero-grid">
            <div>
                <span class="pending-eyebrow"><i class="bi bi-receipt-cutoff"></i> Pending Bills</span>
                <h1>No Bill Payment Slip</h1>
                <p>Review all records where bill status is empty, no, pending, N, or 0.</p>
            </div>
            <div class="pending-total">
                <span>Pending Records</span>
                <strong><?= number_format($pending_count) ?></strong>
                <small>SAR <?= number_format($total_amount, 2) ?></small>
            </div>
        </div>
    </section>

    <form method="get" id="filterForm" class="glass-card pending-filter row g-3 mb-3 align-items-end no-print">
        <div class="col-md-3">
            <label for="month_year" class="form-label"><i class="bi bi-calendar3"></i> Month</label>
            <input type="month" id="month_year" name="month_year" class="form-control"
                   value="<?= htmlspecialchars($month_year_filter ?: date('Y-m')) ?>">
        </div>
        <div class="col-md-2">
            <label for="region" class="form-label"><i class="bi bi-geo-alt"></i> Region</label>
            <select class="form-select" name="region" id="region">
                <?php
                $regions = ['All','Dammam','Riyadh','Jeddah','Other'];
                foreach ($regions as $region) {
                    $selected = ($region_filter == $region) ? 'selected' : '';
                    echo "<option value=\"$region\" $selected>$region</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><i class="bi bi-person"></i> Username</label>
            <select class="form-select" name="username">
                <option value="">All Users</option>
                <?php foreach($usernames as $uname): ?>
                    <option value="<?= htmlspecialchars($uname) ?>" <?= ($username_filter==$uname)?'selected':'' ?>>
                        <?= htmlspecialchars($uname) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary btn-pill"><i class="bi bi-funnel"></i> Apply</button>
            <a class="btn btn-light btn-pill" href="pending_bills.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            <button type="button" class="btn btn-success btn-pill" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="col-12">
            <div class="pending-chip-row">
                <span class="pending-chip"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($selected_month_label) ?></span>
                <span class="pending-chip"><i class="bi bi-person-badge"></i> <?= $username_filter ? htmlspecialchars($username_filter) : 'All Users' ?></span>
                <span class="pending-chip"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($region_filter ?: 'All') ?></span>
            </div>
        </div>
    </form>

    <div class="mb-2 d-flex justify-content-between print-summary">
        <div><strong>Month:</strong> <?= htmlspecialchars($selected_month_label) ?></div>
        <div><strong>User:</strong> <?= $username_filter ? htmlspecialchars($username_filter) : 'All Users' ?></div>
        <div><strong>Region:</strong> <?= htmlspecialchars($region_filter ?: 'All') ?></div>
    </div>

    <div class="print-section">
        <div class="glass-card pending-table-card table-responsive">
            <table class="table table-bordered table-hover align-middle pending-table" style="width:100%; margin-bottom: 8px;">
                <thead>
                    <tr>
                        <th style="width:6%;">SI No</th>
                        <th style="width:9%;">Date</th>
                        <th style="width:8%;">Type</th>
                        <th style="width:11%;">Division</th>
                        <th style="width:13%;">Company</th>
                        <th style="width:11%;">Location</th>
                        <th style="width:10%;">Store</th>
                        <th style="width:22%;">Description</th>
                        <th style="width:8%;">Amount</th>
                        <th style="width:6%;">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i=1;
                    foreach($rows as $row):
                        $is_pending = empty($row['bill']) || strtolower($row['bill'])=='no';
                    ?>
                    <tr class="<?= $is_pending ? 'pending' : '' ?>">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($row['date']))) ?></td>
                        <td><span class="category-pill"><?= htmlspecialchars($row['expense_category']) ?></span></td>
                        <td><?= htmlspecialchars($row['division'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['location'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['store'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td class="amount-cell">SAR <?= number_format((float)$row['amount'], 2) ?></td>
                        <td>
                            <?php if($is_pending): ?>
                                <button class="btn btn-success btn-sm btn-pill action-btn"
                                        data-id="<?= $row['id'] ?>"
                                        data-category="<?= $row['expense_category'] ?>"
                                        data-value="yes">Yes</button>
                            <?php else: ?>
                                <?= htmlspecialchars($row['bill']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="10" class="empty-state">No pending bills found for this filter.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                        <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="report-footer d-print-block mt-2">
            <div style="display:flex; justify-content:space-between; font-size: 10px;">
                <div style="width:33%; text-align:left;">Prepared By: _______________</div>
                <div style="width:33%; text-align:center;">Verified By: _______________</div>
                <div style="width:33%; text-align:right;">Approved By: _______________</div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">Confirm Update</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to mark this bill as <strong>Yes</strong>?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmYesBtn">Yes, Update</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
