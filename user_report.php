<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Validate username
if (!isset($_GET['username']) || empty($_GET['username'])) {
    die("User not specified!");
}
$username = $_GET['username'];
// Initialize carrydown variables
$total_carry = 0.0;
$carrydown_tooltip = "";

// Get user full name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows === 0) die("User not found!");
$full_name = $user_result->fetch_assoc()['full_name'];

// Default to current month if month filter missing
if (empty($_GET['month'])) {
    $selected_month = date("Y-m");
} else {
    $selected_month = $_GET['month'];
}
$from_date = date("Y-m-01", strtotime($selected_month . "-01"));
$to_date   = date("Y-m-t", strtotime($selected_month . "-01"));
$region_filter = isset($_GET['region']) ? $_GET['region'] : 'All';
$types = ['All','Fuel','Food','Room','Other','Tools','Labour','Accessories','TV','Vehicle','Taxi'];
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'All';


// Fetch expenses function
function get_expenses($conn, $table, $username, $from_date = '', $to_date = '', $region = 'All') {
    if ($table === 'vehicle_expense') {
        $sql = "SELECT ve.id, ve.username,
                       CONCAT(IFNULL(v.model,''), ' - ', IFNULL(v.number_plate,''), ' - ', ve.service, IF(ve.description IS NOT NULL, CONCAT(' - ', ve.description), '')) AS description,
                       ve.amount, ve.date, ve.service, ve.bill,
                       '' as division, '' as company, '' as location, '' as store, '' as region
                FROM vehicle_expense ve
                LEFT JOIN vehicle v ON ve.vehicle_id = v.id
                WHERE ve.username=? AND ve.submitted=1";
    } elseif ($table === 'taxi_expense') {
        $sql = "SELECT id, username, CONCAT(from_location, ' → ', to_location) AS description, 
                       amount, date, division, company, '' as location, store, region, bill,
                       from_location, to_location
                FROM $table
                WHERE username=? AND submitted=1";
    } else {
        $sql = "SELECT id, username, description, amount, date, division, company, location, store, region, bill
                FROM $table
                WHERE username=? AND submitted=1";
    }

    $types = "s";
    $params = [$username];

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
    if (!$stmt) die("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

function calculate_carryforward_from_previous_month($conn, $username, $prev_first_day, $prev_last_day, $prev_month) {
    $tables = ['fuel_expense','food_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense','vehicle_expense','taxi_expense'];
    $total_prev_expenses = 0.0;
    foreach ($tables as $table) {
        $sql = "SELECT SUM(amount) as amt FROM $table WHERE username=? AND submitted=1 AND date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $prev_first_day, $prev_last_day);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $total_prev_expenses += $row ? floatval($row['amt']) : 0.0;
    }

    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
    $stmt->bind_param("sss", $username, $prev_first_day, $prev_last_day);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total_prev_adv = $row ? floatval($row['total_adv']) : 0.0;

    $stmt = $conn->prepare("SELECT amount FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $username, $prev_month);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $prev_carry = $row ? floatval($row['amount']) : 0.0;

    return ($total_prev_adv + $prev_carry) - $total_prev_expenses;
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch expenses
$expense_tables = [
    'Fuel' => 'fuel_expense',
    'Food' => 'food_expense',
    'Room' => 'room_expense',
    'Other' => 'other_expense',
    'Tools' => 'tools_expense',
    'Labour' => 'labour_expense',
    'Accessories' => 'accessories_expense',
    'TV' => 'tv_expense',
    'Vehicle' => 'vehicle_expense',
    'Taxi' => 'taxi_expense'
];

$expenses_list = [];
foreach ($expense_tables as $type => $table) {
    $expenses_list[$type] = get_expenses($conn, $table, $username, $from_date, $to_date, $region_filter);
}

// Combine expenses
$total_amount = 0;
$all_expenses = [];
$total_amount = 0;
foreach ($expenses_list as $type => $result) {
    if ($type_filter != 'All' && $type != $type_filter) continue; // Skip types not selected
    while ($row = $result->fetch_assoc()) {
        $row['type'] = $type;
        $all_expenses[] = $row;
        $total_amount += $row['amount'];
    }
}
$type_totals = [];
if ($type_filter == 'All') {
    foreach ($expense_tables as $type => $table) {
        $type_totals[$type] = 0;
    }
    foreach ($all_expenses as $row) {
        $type_totals[$row['type']] += $row['amount'];
    }
}

// Sort and paginate
usort($all_expenses, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

$total_records = count($all_expenses);
$paged_expenses = array_slice($all_expenses, $offset, $limit);

// ----------------------
// Handle Expense Edit Submission (AJAX)
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_expense'])) {
    header('Content-Type: application/json');
    
    $expense_id = intval($_POST['expense_id']);
    $expense_type = strtolower($_POST['expense_type']);
    $date = $_POST['date'] ?? '';
    $division = $_POST['division'] ?? '';
    $company = $_POST['company'] ?? '';
    $location = $_POST['location'] ?? '';
    $store = $_POST['store'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $bill = $_POST['bill'] ?? '';
    $service = $_POST['service'] ?? '';
    
    $tables = [
        'fuel' => 'fuel_expense',
        'food' => 'food_expense',
        'room' => 'room_expense',
        'other' => 'other_expense',
        'tools' => 'tools_expense',
        'labour' => 'labour_expense',
        'accessories' => 'accessories_expense',
        'tv' => 'tv_expense',
        'vehicle' => 'vehicle_expense',
        'taxi' => 'taxi_expense'
    ];
    
    if (!array_key_exists($expense_type, $tables)) {
        echo json_encode(['success' => false, 'error' => 'Invalid expense type']);
        exit();
    }
    
    $table = $tables[$expense_type];
    $is_tools = ($table === 'tools_expense');
    $is_labour = ($table === 'labour_expense');
    $is_vehicle = ($table === 'vehicle_expense');
    $is_taxi = ($table === 'taxi_expense');
    
    if (empty($bill)) {
        echo json_encode(['success' => false, 'error' => 'Please select Bill option']);
        exit();
    }
    
    if ($is_vehicle) {
        if (empty($service) || !is_numeric($amount)) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields correctly']);
            exit();
        }
        $update = $conn->prepare("UPDATE vehicle_expense SET service=?, amount=?, bill=? WHERE id=?");
        $update->bind_param("sdsi", $service, $amount, $bill, $expense_id);
    } elseif ($is_taxi) {
        $from_location = $_POST['from_location'] ?? '';
        $to_location = $_POST['to_location'] ?? '';
        
        if (empty($date) || empty($amount) || !is_numeric($amount) || empty($from_location) || empty($to_location)) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields correctly']);
            exit();
        }
        $update = $conn->prepare("UPDATE $table SET date=?, division=?, company=?, store=?, from_location=?, to_location=?, amount=?, bill=? WHERE id=?");
        $update->bind_param("ssssssdsi", $date, $division, $company, $store, $from_location, $to_location, $amount, $bill, $expense_id);
    } else {
        $disable_fields = $is_tools || (!$is_labour && in_array($division, ['Recharge', 'Other']));
        
        if (empty($date) || empty($amount) || !is_numeric($amount)) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields correctly']);
            exit();
        }
        
        // Always update all fields - the JavaScript already sends the correct values
        $update = $conn->prepare("UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=?, bill=? WHERE id=?");
        $update->bind_param("ssssssdsi", $date, $division, $company, $location, $store, $description, $amount, $bill, $expense_id);
    }
    
    if ($update->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed: ' . $conn->error]);
    }
    exit();
}

// ----------------------
// Handle Advance Submission
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_advance'])) {
    $adv_date = $_POST['adv_date'];
    $adv_amount = $_POST['adv_amount'];
    if (empty($adv_date) || !is_numeric($adv_amount) || $adv_amount <= 0) {
        die("Invalid advance data submitted.");
    }
    $stmt = $conn->prepare("INSERT INTO adv_amt (date, username, adv_amt) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $adv_date, $username, $adv_amount);
    $stmt->execute();
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// ----------------------
// Handle Carrydown Submission
// ----------------------
$current_month = date("Y-m", strtotime($from_date));
$prev_month = date("Y-m", strtotime("$from_date -1 month"));
$first_day_prev = date("Y-m-01", strtotime("$from_date -1 month"));
$last_day_prev  = date("Y-m-t", strtotime("$from_date -1 month"));

// Check if carrydown exists for current month
$stmt = $conn->prepare("SELECT id, amount, description FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? LIMIT 1");
$stmt->bind_param("ss", $username, $current_month);
$stmt->execute();
$current_month_carry = $stmt->get_result()->fetch_assoc();
$carrydown_exists = !empty($current_month_carry);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_carrydown'])) {
    $cd_amount = floatval($_POST['cd_amount']);
    $cd_desc = $_POST['cd_desc'];

    if ($carrydown_exists) {
        // Update existing
        $stmt = $conn->prepare("UPDATE carry_down SET amount=?, description=?, updated_at=NOW() WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $stmt->bind_param("dsss", $cd_amount, $cd_desc, $username, $current_month);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO carry_down (username, amount, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sds", $username, $cd_amount, $cd_desc);
    }
    $stmt->execute();
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// ----------------------
// Handle Recalculate Carrydown (current month and all future months)
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_prev_carry'])) {
    // Delete carrydown for current month and all future months for this user
    $stmt = $conn->prepare("DELETE FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m') >= ?");
    $stmt->bind_param("ss", $username, $current_month);
    $stmt->execute();
    
    // Now recalculate carrydown for current month and all future months that have data
    $recalc_month = $current_month;
    $today_month = date("Y-m");
    
    while ($recalc_month <= $today_month) {
        $recalc_first_day = $recalc_month . "-01";
        $recalc_last_day = date("Y-m-t", strtotime($recalc_first_day));
        $recalc_prev_month = date("Y-m", strtotime("$recalc_first_day -1 month"));
        $recalc_prev_first = date("Y-m-01", strtotime("$recalc_first_day -1 month"));
        $recalc_prev_last = date("Y-m-t", strtotime("$recalc_first_day -1 month"));
        
        // Calculate previous month's expenses
        $expense_tables = ['fuel_expense','food_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense','vehicle_expense','taxi_expense'];
        $total_prev_exp = 0.0;
        foreach ($expense_tables as $table) {
            $sql = "SELECT SUM(amount) as amt FROM $table WHERE username=? AND submitted=1 AND date BETWEEN ? AND ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $username, $recalc_prev_first, $recalc_prev_last);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $total_prev_exp += $res ? floatval($res['amt']) : 0.0;
        }
        
        // Get previous month's advance
        $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
        $stmt->bind_param("sss", $username, $recalc_prev_first, $recalc_prev_last);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $prev_adv = $res ? floatval($res['total_adv']) : 0.0;
        
        // Get previous month's carrydown
        $stmt = $conn->prepare("SELECT amount FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ss", $username, $recalc_prev_month);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $prev_cd = $res ? floatval($res['amount']) : 0.0;
        
        // Calculate new carrydown: (prev_advance + prev_carrydown) - prev_expenses
        $new_carrydown = ($prev_adv + $prev_cd) - $total_prev_exp;
        $cd_desc = "Carryforward from " . date("M Y", strtotime($recalc_prev_first));
        
        // Insert new carrydown for this month
        $stmt = $conn->prepare("INSERT INTO carry_down (username, amount, description, created_at) VALUES (?, ?, ?, ?)");
        $created_at = $recalc_first_day . " 00:00:00";
        $stmt->bind_param("sdss", $username, $new_carrydown, $cd_desc, $created_at);
        $stmt->execute();
        
        // Move to next month
        $recalc_month = date("Y-m", strtotime("$recalc_first_day +1 month"));
    }
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Auto-insert carrydown if missing
if (!$carrydown_exists) {
    $last_month_balance = calculate_carryforward_from_previous_month($conn, $username, $first_day_prev, $last_day_prev, $prev_month);

    $carrydown_desc = "Carryforward from " . date("M Y", strtotime($first_day_prev));
    $stmt = $conn->prepare("INSERT INTO carry_down (username, amount, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sds", $username, $last_month_balance, $carrydown_desc);
    $stmt->execute();

    $carrydown_value = $last_month_balance;
} else {
    $carrydown_value = floatval($current_month_carry['amount']);
    $carrydown_desc  = $current_month_carry['description'];
    $expected_carrydown = calculate_carryforward_from_previous_month($conn, $username, $first_day_prev, $last_day_prev, $prev_month);

    if (strpos($carrydown_desc, 'Carryforward from ') === 0 && abs($carrydown_value - $expected_carrydown) >= 0.005) {
        $stmt = $conn->prepare("UPDATE carry_down SET amount=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("di", $expected_carrydown, $current_month_carry['id']);
        $stmt->execute();
        $carrydown_value = $expected_carrydown;
    }
}

// Assign to total_carry for HTML display
$total_carry = $carrydown_value;


// Fetch advances in selected range
$stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
$stmt->bind_param("sss", $username, $from_date, $to_date);
$stmt->execute();
$total_adv = floatval($stmt->get_result()->fetch_assoc()['total_adv'] ?? 0);

// Invoice handling
$stmt = $conn->prepare("SELECT invoice_no FROM invoices WHERE username=? AND from_date=? AND to_date=? AND region=? ORDER BY CAST(invoice_no AS UNSIGNED) DESC LIMIT 1");
$stmt->bind_param("ssss", $username, $from_date, $to_date, $region_filter);
$stmt->execute();
$invoice_result = $stmt->get_result();
if ($invoice_result->num_rows > 0) {
    $invoice_no = str_pad($invoice_result->fetch_assoc()['invoice_no'], 5, "0", STR_PAD_LEFT);
} else {
    $max_inv = $conn->query("SELECT MAX(CAST(invoice_no AS UNSIGNED)) as max_inv FROM invoices")->fetch_assoc()['max_inv'];
    $next_invoice = $max_inv ? $max_inv + 1 : 1;
    $stmt = $conn->prepare("INSERT INTO invoices (username, from_date, to_date, region, invoice_no) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $from_date, $to_date, $region_filter, $next_invoice);
    $stmt->execute();
    $invoice_no = str_pad($next_invoice, 5, "0", STR_PAD_LEFT);
}
$total_carry = $carrydown_value;

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Expense Report | VisionAngles</title>
<link rel="stylesheet" href="assets/vendor/flatpickr/flatpickr.min.css">
<link href="assets/user_report.css" rel="stylesheet">
<link href="assets/loader.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="assets/vendor/bootstrap-5.0.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
.total-spend { background-color: #f2f2f2; padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; }
/* Table general styling */
.table { border: 2px solid black; border-collapse: collapse; }
th, td { border: 0.5px solid black; padding: 4px 6px; text-align: left; word-wrap: break-word; }
@media print {
    .total-summary { font-size: 11px; margin-top: 5px; text-align: right; }
    body { -webkit-print-color-adjust: exact; color-adjust: exact; margin: 10mm; font-size: 12px; background: #fff; }
    body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('assets/vision1.png') no-repeat center center; background-size: 50%; opacity: 0.05; z-index: 9999; pointer-events: none; background-color: #aeb6bd; }
    table { width: 100%; border-collapse: collapse; border: 2px solid black; page-break-inside: auto; }
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; color: black; }
    th, td { border: 0.5px solid black; padding: 4px 6px; font-size: 11px; text-align: left; word-wrap: break-word; color: black; }
    button, input, select { display: none !important; }
    .total-summary { display: flex; justify-content: flex-end; text-align: right; margin-top: 20px; }
    /* Prevent scrollbars from printing */
    html, body { overflow: visible !important; height: auto !important; }
    .table-responsive { overflow: visible !important; }
    /* Hide any webkit scrollbars in print context */
    .table-responsive::-webkit-scrollbar { display: none !important; }
}
/* Modal styling */
.modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 5px; width: 80%; max-width: 700px; }
.close-btn { float: right; font-size: 24px; cursor: pointer; }
.modal table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.modal table th, .modal table td { border: 0.5px solid #ddd; padding: 8px; text-align: center; }
.modal table th { background-color: #f2f2f2; }
</style>
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-container">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading...</div>
    </div>
</div>

<div class="report-header">
    <h2>Expense Report</h2>
    <div style="text-align:right; font-weight:bold;">EX: <span id="invoice_no"><?php echo $invoice_no; ?></span></div>
</div>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    
    <div class="form-group">
        <label for="month" class="form-label mb-1 small">Select Month</label>
        <input type="month" class="form-control form-control-sm" id="month" name="month" 
               value="<?php echo htmlspecialchars($selected_month); ?>" required>
    </div>
    <div class="form-group">
    <label for="region" class="form-label mb-1 small">Region</label>
    <select class="form-select form-select-sm" id="region" name="region"> <?php $regions = ['All','Dammam','Riyadh','Jeddah','Other']; foreach ($regions as $region) { $selected = ($region_filter == $region) ? 'selected' : ''; echo "<option value=\"$region\" $selected>$region</option>"; } ?></select></div> 
    <div class="btn-group align-self-end">
        <button class="btn btn-outline-primary btn-sm" type="submit">
            Search
        </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location='user_report.php?username=<?php echo urlencode($username); ?>'">
        Clear
    </button>
    <button class="btn btn-outline-success btn-sm" type="button" onclick="confirmInvoicePrint()">Print</button>
    <button type="button" class="btn btn-outline-success btn-sm" onclick="startExport('export_excel.php?username=<?php echo urlencode($username); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&region=<?php echo urlencode($region_filter); ?>&type=<?php echo urlencode($type_filter); ?>')">
        Export
    </button>
    <!-- <button class="btn btn-info btn-sm" <?php echo $carrydown_exists ? 'disabled' : ''; ?> onclick="openCarrydownModal()">
        Add Carrydown
    </button> -->
    <div class="form-group">
        <select class="form-select form-select-sm" id="type" name="type">
            <?php foreach($types as $type): 
                $selected = ($type_filter == $type) ? 'selected' : ''; ?>
                <option value="<?= $type ?>" <?= $selected ?>><?= $type ?></option>
            <?php endforeach; ?>
        </select>
    </div>
 <a href="<?= htmlspecialchars(($_SESSION['role'] === 'superadmin') ? 'dashboard_superadmin.php' : 'dashboard_admin.php') ?>" class="btn btn-danger btn-sm" data-dashboard-back>Back</a> </div> </form>

<div class="print-header d-flex justify-content-between mb-3">
    <div>
        <strong>Username:</strong> <?php echo htmlspecialchars(ucfirst($username)); ?> 
        | <strong>Region:</strong> <?php echo htmlspecialchars($region_filter); ?><br>
        <strong>Month:</strong> <?php echo date("F Y", strtotime($selected_month . "-01")); ?>
    </div>
    <div style="text-align:right;">
        <strong>Brought Down (B/d):</strong> SAR <?php echo number_format($total_carry, 2); ?>
        <button type="button" class="btn btn-warning btn-sm" onclick="openRecalculateModal()">Re-Calculate</button><br>
        <strong>Advance:</strong> SAR <?php echo number_format($total_adv, 2); ?>
    </div>
</div>

<!-- Screen Table (Paginated) -->
<div class="table-responsive d-print-none">
    <table class="table align-middle">
        <thead style="background-color:grey;">
            <tr>
                <th style="width:50px; text-align:center;">SI. No</th>
                <th>Date</th>
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
            <?php if(count($paged_expenses) > 0): $si = $offset + 1; foreach($paged_expenses as $row): ?>
            <tr>
                <td class="text-center"><?= $si ?></td>
                <td><?= date("d", strtotime($row['date'])) . "&nbsp;" . date("M", strtotime($row['date'])) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['division']) ?></td>
                <td><?= htmlspecialchars($row['company']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['store']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>SAR <?= number_format($row['amount'], 2) ?></td>
                <td>
                    <div class="d-flex flex-column flex-md-row gap-1">
                        <button class="btn btn-warning btn-sm editExpenseBtn" 
                            data-id="<?= $row['id'] ?>"
                            data-type="<?= $row['type'] ?>"
                            data-date="<?= $row['date'] ?>"
                            data-division="<?= htmlspecialchars($row['division'] ?? '') ?>"
                            data-company="<?= htmlspecialchars($row['company'] ?? '') ?>"
                            data-location="<?= htmlspecialchars($row['location'] ?? '') ?>"
                            data-store="<?= htmlspecialchars($row['store'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($row['description'] ?? '') ?>"
                            data-amount="<?= $row['amount'] ?>"
                            data-bill="<?= htmlspecialchars($row['bill'] ?? '') ?>"
                            data-service="<?= htmlspecialchars($row['service'] ?? '') ?>"
                            data-from_location="<?= htmlspecialchars($row['from_location'] ?? '') ?>"
                            data-to_location="<?= htmlspecialchars($row['to_location'] ?? '') ?>">Edit</button>
                        <?php if($row['type'] === 'TV'): ?>
    <button class="btn btn-danger btn-sm" onclick="if(confirm('Are you sure?')) window.location='delete_tv.php?id=<?= $row['id'] ?>&username=<?= urlencode($username) ?>'">Delete</button>
<?php else: ?>
    <button class="btn btn-danger btn-sm" onclick="if(confirm('Are you sure?')) window.location='delete_expense.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>'">Delete</button>
<?php endif; ?>
                        <span><?= htmlspecialchars($row['remark'] ?? '') ?></span>
                    </div>
                </td>
            </tr>
            <?php $si++; endforeach; else: ?>
            <tr><td colspan="10" class="text-center">No expenses found for this user.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="8" class="text-end fw-bold">Carry Down (C/d):</td>
                <td colspan="2">SAR <?= number_format(($total_adv + $total_carry) - $total_amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination controls -->
    <?php
    $total_pages = ceil($total_records / $limit);
    if ($total_pages > 1):
    ?>
    <nav>
      <ul class="pagination justify-content-center">
        <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
          <a class="page-link" href="?username=<?= urlencode($username) ?>&month=<?= urlencode($selected_month) ?>&region=<?= urlencode($region_filter) ?>&type=<?= urlencode($type_filter) ?>&page=<?= $page-1 ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <li class="page-item <?= ($i == $page ? 'active' : '') ?>">
            <a class="page-link" href="?username=<?= urlencode($username) ?>&month=<?= urlencode($selected_month) ?>&region=<?= urlencode($region_filter) ?>&type=<?= urlencode($type_filter) ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= ($page >= $total_pages ? 'disabled' : '') ?>">
          <a class="page-link" href="?username=<?= urlencode($username) ?>&month=<?= urlencode($selected_month) ?>&region=<?= urlencode($region_filter) ?>&type=<?= urlencode($type_filter) ?>&page=<?= $page+1 ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Full Table (Print) -->
<div class="table-responsive d-none d-print-block">
    <table class="table align-middle">
        <thead style="background-color:grey;">
            <tr>
                <th style="width:50px; text-align:center;">SI. No</th>
                <th>Date</th>
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
            <?php if(count($all_expenses) > 0): $si = 1; foreach($all_expenses as $row): ?>
            <tr>
                <td class="text-center"><?= $si ?></td>
                <td><?= date("d", strtotime($row['date'])) . "&nbsp;" . date("M", strtotime($row['date'])) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['division']) ?></td>
                <td><?= htmlspecialchars($row['company']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['store']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>SAR <?= number_format($row['amount'], 2) ?></td>
                <td><?= htmlspecialchars($row['remark'] ?? '') ?></td>
            </tr>
            <?php $si++; endforeach; else: ?>
            <tr><td colspan="10" class="text-center">No expenses found for this user.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="8" class="text-end fw-bold">Balance:</td>
                <td colspan="2">SAR <?= number_format(($total_adv + $total_carry) - $total_amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <?php if($type_filter == 'All'): ?>
    <div class="total-summary" style="font-size:12px; margin-top:10px; text-align:right;">
        <strong>Summary by Type:</strong>
        <?php 
            $summary_items = [];
            foreach($type_totals as $type => $amount) {
                $summary_items[] = "$type: SAR " . number_format($amount, 2);
            }
            echo implode(" | ", $summary_items);
        ?>
    </div>
<?php endif; ?>

</div>

<div style="text-align:right; margin-top:10px;">
    <button onclick="window.location='manage_advance.php?username=<?= urlencode($username) ?>&total_expense=<?= urlencode($total_amount) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&region=<?= urlencode($region_filter) ?>'">Manage Advances</button>
</div>

<div class="report-footer">
    <div>Prepared By: <?= htmlspecialchars(ucfirst($full_name)) ?></div>
    <div>Verified By :       </div>
    <div>Approved By:        </div>
</div>

<!-- Carrydown Modal -->
<div id="carrydownModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeCarrydownModal()">&times;</span>
    <h4>Carrydown Entry</h4>
    <form method="post">
        <input type="hidden" name="add_carrydown" value="1">
        <div class="mb-3">
            <label for="cd_amount" class="form-label">Amount (can be + or -)</label>
            <input type="number" step="0.01" name="cd_amount" id="cd_amount" class="form-control" required 
                   value="<?= $current_month_carry['amount'] ?? '' ?>">
        </div>
        <div class="mb-3">
            <label for="cd_desc" class="form-label">Description</label>
            <textarea name="cd_desc" id="cd_desc" class="form-control" rows="3" required><?= $current_month_carry['description'] ?? '' ?></textarea>
        </div>
        <button type="submit" class="btn btn-success"><?= $carrydown_exists ? 'Update' : 'Save' ?></button>
        <button type="button" class="btn btn-secondary" onclick="closeCarrydownModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Expense Modal -->
<div id="editExpenseModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <span class="close-btn" onclick="closeEditExpenseModal()">&times;</span>
    <h4 id="editExpenseTitle">Edit Expense</h4>
    <form id="editExpenseForm">
        <input type="hidden" name="edit_expense" value="1">
        <input type="hidden" name="expense_id" id="edit_expense_id">
        <input type="hidden" name="expense_type" id="edit_expense_type">
        
        <!-- Vehicle fields -->
        <div id="vehicleFields" style="display:none;">
            <div class="mb-3">
                <label class="form-label">Service</label>
                <select name="service" id="edit_service" class="form-control">
                    <option value="">-- Select Service --</option>
                    <option value="Engine Oil">Engine Oil</option>
                    <option value="Gear Oil">Gear Oil</option>
                    <option value="Tyre">Tyre</option>
                    <option value="Brake Pad">Brake Pad</option>
                    <option value="Brake Oil">Brake Oil</option>
                    <option value="Fuel Injection">Fuel Injection</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        
        <!-- Non-vehicle fields -->
        <div id="nonVehicleFields">
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" id="edit_date" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Division</label>
                <select name="division" id="edit_division" class="form-control">
                    <option value="">-- Select Division --</option>
                    <option value="Sales">Sales</option>
                    <option value="Project">Project</option>
                    <option value="Service">Service</option>
                    <option value="Installation">Installation</option>
                    <option value="Recharge">Recharge</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <select name="company" id="edit_company" class="form-control">
                    <option value="">-- Select Company --</option>
                    <option value="Redtag">Redtag</option>
                    <option value="Landmark">Landmark</option>
                    <option value="Apparel">Apparel</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3" id="edit_location_field">
                <label class="form-label">Location</label>
                <input type="text" name="location" id="edit_location" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Store</label>
                <input type="text" name="store" id="edit_store" class="form-control">
            </div>
            <div class="mb-3" id="edit_description_field">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>
        </div>
        
        <!-- Taxi fields -->
        <div id="taxiFields" style="display:none;">
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" id="edit_taxi_date" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Division</label>
                <select name="division" id="edit_taxi_division" class="form-control">
                    <option value="">-- Select Division --</option>
                    <option value="Sales">Sales</option>
                    <option value="Project">Project</option>
                    <option value="Service">Service</option>
                    <option value="Installation">Installation</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <select name="company" id="edit_taxi_company" class="form-control">
                    <option value="">-- Select Company --</option>
                    <option value="Redtag">Redtag</option>
                    <option value="Landmark">Landmark</option>
                    <option value="Apparel">Apparel</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Store</label>
                <input type="text" name="store" id="edit_taxi_store" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">From (Pickup Location)</label>
                <input type="text" name="from_location" id="edit_from_location" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">To (Drop Location)</label>
                <input type="text" name="to_location" id="edit_to_location" class="form-control">
            </div>
        </div>
        
        <!-- Common fields -->
        <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Bill</label>
            <select name="bill" id="edit_bill" class="form-control" required>
                <option value="">-- Select Bill --</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        
        <div id="editExpenseError" class="alert alert-danger" style="display:none;"></div>
        
        <button type="submit" class="btn btn-primary" id="editExpenseSubmitBtn">Update Expense</button>
        <button type="button" class="btn btn-secondary" onclick="closeEditExpenseModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Recalculate Carrydown Modal -->
<div id="recalculateModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeRecalculateModal()">&times;</span>
    <h4>Recalculate Carrydown</h4>
    <p>Do you want to recalculate the carrydown? This will delete the current carrydown and recalculate it based on previous data.</p>
    <form method="post">
        <input type="hidden" name="delete_prev_carry" value="1">
        <button type="submit" class="btn btn-danger">Yes, Recalculate</button>
        <button type="button" class="btn btn-secondary" onclick="closeRecalculateModal()">Cancel</button>
    </form>
  </div>
</div>

<script src="assets/vendor/bootstrap-5.0.2/js/bootstrap.bundle.min.js"></script>
<script>
function confirmInvoicePrint(){
    if(confirm("Do you want a NEW invoice number?")){
        fetch("generate_invoice.php?username=<?= urlencode($username) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&region=<?= urlencode($region_filter) ?>")
        .then(res => {
            if (!res.ok) throw new Error("Unable to generate invoice number");
            return res.text();
        }).then(inv=>{
            inv = inv.trim();
            if (!/^\d+$/.test(inv)) throw new Error(inv || "Invalid invoice number");
            // Update all invoice number references
            document.querySelectorAll('#invoice_no, .invoice_no').forEach(function(el){
                el.innerText = inv;
            });
            // If there are input fields or hidden fields, update their value too
            document.querySelectorAll('input[name="invoice_no"]').forEach(function(el){
                el.value = inv;
            });
            window.print();
        }).catch(error => {
            alert(error.message);
        });
    } else window.print();
}

let exportInProgress = false;

function getPageLoader() {
    return document.getElementById('pageLoader');
}

function hidePageLoader() {
    const loader = getPageLoader();
    if (loader) loader.classList.add('hidden');
}

function showPageLoader(message) {
    const loader = getPageLoader();
    if (!loader) return;
    const loaderText = loader.querySelector('.loader-text');
    if (loaderText && message) loaderText.textContent = message;
    loader.classList.remove('hidden');
}

function goBackToDashboard(url) {
    showPageLoader('Loading...');
    window.location.replace(url);
}

function startExport(url) {
    exportInProgress = true;
    hidePageLoader();
    window.location.href = url;

    setTimeout(function() {
        exportInProgress = false;
        hidePageLoader();
    }, 2000);
}
function openCarrydownModal() { document.getElementById("carrydownModal").style.display = "block"; }
function closeCarrydownModal() { document.getElementById("carrydownModal").style.display = "none"; }

// Recalculate Modal functions
function openRecalculateModal() { document.getElementById("recalculateModal").style.display = "block"; }
function closeRecalculateModal() { document.getElementById("recalculateModal").style.display = "none"; }

// Edit Expense Modal functions
function openEditExpenseModal() { document.getElementById("editExpenseModal").style.display = "block"; }
function closeEditExpenseModal() { 
    document.getElementById("editExpenseModal").style.display = "none"; 
    document.getElementById("editExpenseError").style.display = "none";
}

// Handle Edit buttons
document.addEventListener("DOMContentLoaded", function() {
    // Restore scroll position if saved
    const savedScrollPosition = sessionStorage.getItem('scrollPosition');
    if (savedScrollPosition) {
        window.scrollTo(0, parseInt(savedScrollPosition));
        sessionStorage.removeItem('scrollPosition');
    }
    
    const editButtons = document.querySelectorAll(".editExpenseBtn");
    
    editButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const type = this.dataset.type;
            const date = this.dataset.date;
            const division = this.dataset.division;
            const company = this.dataset.company;
            const location = this.dataset.location;
            const store = this.dataset.store;
            const description = this.dataset.description;
            const amount = this.dataset.amount;
            const bill = this.dataset.bill;
            const service = this.dataset.service;
            const fromLocation = this.dataset.from_location || '';
            const toLocation = this.dataset.to_location || '';
            
            // Set hidden fields
            document.getElementById("edit_expense_id").value = id;
            document.getElementById("edit_expense_type").value = type;
            
            // Set title
            document.getElementById("editExpenseTitle").textContent = "Edit " + type + " Expense";
            
            // Show/hide fields based on type
            const isVehicle = (type.toLowerCase() === 'vehicle');
            const isTools = (type.toLowerCase() === 'tools');
            const isTaxi = (type.toLowerCase() === 'taxi');
            
            document.getElementById("vehicleFields").style.display = isVehicle ? "block" : "none";
            document.getElementById("taxiFields").style.display = isTaxi ? "block" : "none";
            document.getElementById("nonVehicleFields").style.display = (isVehicle || isTaxi) ? "none" : "block";
            
            if (isVehicle) {
                document.getElementById("edit_service").value = service;
            } else if (isTaxi) {
                document.getElementById("edit_taxi_date").value = date;
                document.getElementById("edit_taxi_division").value = division;
                document.getElementById("edit_taxi_company").value = company;
                document.getElementById("edit_taxi_store").value = store;
                document.getElementById("edit_from_location").value = fromLocation;
                document.getElementById("edit_to_location").value = toLocation;
            } else {
                document.getElementById("edit_date").value = date;
                document.getElementById("edit_division").value = division;
                document.getElementById("edit_company").value = company;
                document.getElementById("edit_location").value = location;
                document.getElementById("edit_store").value = store;
                document.getElementById("edit_description").value = description;
                
                // Disable fields for tools type
                const disableFields = isTools || ['Recharge', 'Other'].includes(division);
                document.getElementById("edit_division").disabled = isTools;
                document.getElementById("edit_company").disabled = disableFields;
                document.getElementById("edit_location").disabled = disableFields;
                document.getElementById("edit_store").disabled = disableFields;
            }
            
            document.getElementById("edit_amount").value = amount;
            document.getElementById("edit_bill").value = bill;
            
            openEditExpenseModal();
        });
    });
    
    // Handle division change to toggle fields
    document.getElementById("edit_division").addEventListener("change", function() {
        const disableFields = ['Recharge', 'Other'].includes(this.value);
        document.getElementById("edit_company").disabled = disableFields;
        document.getElementById("edit_location").disabled = disableFields;
        document.getElementById("edit_store").disabled = disableFields;
    });
    
    // Handle form submission via AJAX
    document.getElementById("editExpenseForm").addEventListener("submit", function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = document.getElementById("editExpenseSubmitBtn");
        const errorDiv = document.getElementById("editExpenseError");
        
        submitBtn.disabled = true;
        submitBtn.textContent = "Updating...";
        errorDiv.style.display = "none";
        
        // Show loader
        showPageLoader('Updating expense...');
        
        const formData = new FormData(form);
        
        // Get expense type to determine which fields to include
        const expenseType = document.getElementById("edit_expense_type").value.toLowerCase();
        const isTaxi = (expenseType === 'taxi');
        const isVehicle = (expenseType === 'vehicle');
        
        // Include disabled fields or taxi-specific fields
        if (isTaxi) {
            formData.set('date', document.getElementById("edit_taxi_date").value);
            formData.set('division', document.getElementById("edit_taxi_division").value);
            formData.set('company', document.getElementById("edit_taxi_company").value);
            formData.set('store', document.getElementById("edit_taxi_store").value);
            formData.set('from_location', document.getElementById("edit_from_location").value);
            formData.set('to_location', document.getElementById("edit_to_location").value);
        } else if (!isVehicle) {
            // For non-vehicle, non-taxi expenses - include all fields even if disabled
            formData.set('date', document.getElementById("edit_date").value);
            formData.set('division', document.getElementById("edit_division").value);
            formData.set('company', document.getElementById("edit_company").value);
            formData.set('location', document.getElementById("edit_location").value);
            formData.set('store', document.getElementById("edit_store").value);
            formData.set('description', document.getElementById("edit_description").value);
        }
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEditExpenseModal();
                // Save scroll position and reload
                sessionStorage.setItem('scrollPosition', window.scrollY);
                location.reload();
            } else {
                hidePageLoader();
                errorDiv.textContent = data.error || 'An error occurred';
                errorDiv.style.display = "block";
                submitBtn.disabled = false;
                submitBtn.textContent = "Update Expense";
            }
        })
        .catch(error => {
            hidePageLoader();
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = "block";
            submitBtn.disabled = false;
            submitBtn.textContent = "Update Expense";
        });
    });
    
    // Hide loader when page is loaded
    hidePageLoader();

    const dashboardBack = document.querySelector('[data-dashboard-back]');
    if (dashboardBack) {
        dashboardBack.addEventListener('click', function(e) {
            e.preventDefault();
            goBackToDashboard(this.href);
        });
    }
    
    // Show loader on delete buttons
    document.querySelectorAll('.btn-danger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (this.onclick && this.onclick.toString().includes('confirm')) {
                // Wait for confirmation before showing loader
            }
        });
    });
});

// Enable Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })

// Hide loader when the browser restores this page from history cache.
window.addEventListener('pageshow', function() {
    exportInProgress = false;
    hidePageLoader();
});

// Show loader before page unload (navigation)
window.addEventListener('beforeunload', function() {
    if (exportInProgress) return;
    showPageLoader('Loading...');
});
</script>
</body>
</html>
