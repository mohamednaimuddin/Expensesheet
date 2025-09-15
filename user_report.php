<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Validate username
if (!isset($_GET['username']) || empty($_GET['username'])) {
    die("User not specified!");
}
$username = $_GET['username'];

// Get user full name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows === 0) die("User not found!");
$full_name = $user_result->fetch_assoc()['full_name'];

// Get filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

// Fetch expenses function (only submitted=1)
function get_expenses($conn, $table, $username, $from_date = '', $to_date = '', $region = 'All') {
    ['fuel_expense', 'food_expense', 'room_expense', 'other_expense', 'tools_expense','labour_expense', 'accessories_expense','tv_expense','vehicle_expense'];
    if ($table === 'vehicle_expense') {
        $sql = "SELECT id, username, service AS description, amount, date, '' as division, '' as company, '' as location, '' as store, '' as region
                FROM vehicle_expense 
                WHERE username=? AND submitted=1";
    } else {
        $sql = "SELECT id, username, description, amount, date, division, company, location, store, region
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
    if (!$stmt) {
        die("Prepare failed: " . $conn->error . " | SQL: " . $sql);
    }
    $stmt->bind_param($types, ...$params);

    $stmt->execute();
    return $stmt->get_result();
}

// Fetch all expenses
$expenses_list = [
    'Fuel' => get_expenses($conn, 'fuel_expense', $username, $from_date, $to_date, $region_filter),
    'Food' => get_expenses($conn, 'food_expense', $username, $from_date, $to_date, $region_filter),
    'Room' => get_expenses($conn, 'room_expense', $username, $from_date, $to_date, $region_filter),
    'Other' => get_expenses($conn, 'other_expense', $username, $from_date, $to_date, $region_filter),
    'Tools' => get_expenses($conn, 'tools_expense', $username, $from_date, $to_date, $region_filter),
    'Labour' => get_expenses($conn, 'labour_expense', $username, $from_date, $to_date, $region_filter),
    'Accessories' =>get_expenses($conn, 'accessories_expense', $username, $from_date, $to_date, $region_filter),
    'tv' => get_expenses($conn, 'tv_expense', $username, $from_date, $to_date, $region_filter),
    'Vehicle' => get_expenses($conn, 'vehicle_expense', $username, $from_date, $to_date, $region_filter),
];

// Calculate total spend
$total_amount = 0;
$all_expenses = [];
foreach ($expenses_list as $type => $result) {
    while ($row = $result->fetch_assoc()) {
        $row['type'] = $type;
        $all_expenses[] = $row;
        $total_amount += $row['amount'];
    }
}

// Handle advance submission
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

// Check for existing carrydown this month
$current_month = date("Y-m");
$stmt = $conn->prepare("SELECT id, amount, description 
                        FROM carry_down 
                        WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? LIMIT 1");
$stmt->bind_param("ss", $username, $current_month);
$stmt->execute();
$current_month_carry = $stmt->get_result()->fetch_assoc();
$carrydown_exists = !empty($current_month_carry);

// Handle carrydown submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_carrydown'])) {
    $cd_amount = $_POST['cd_amount'];
    $cd_desc   = $_POST['cd_desc'];

    if (!is_numeric($cd_amount)) {
        die("Invalid carrydown amount.");
    }

    if ($carrydown_exists) {
        // Update existing
        $stmt = $conn->prepare("UPDATE carry_down SET amount=?, description=? WHERE id=?");
        $stmt->bind_param("dsi", $cd_amount, $cd_desc, $current_month_carry['id']);
        $stmt->execute();
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO carry_down (username, amount, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sds", $username, $cd_amount, $cd_desc);
        $stmt->execute();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Fetch total advances
if ($from_date && $to_date) {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
    $stmt->bind_param("sss", $username, $from_date, $to_date);
} else {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=?");
    $stmt->bind_param("s", $username);
}
$stmt->execute();
$total_adv = $stmt->get_result()->fetch_assoc()['total_adv'] ?? 0;

// Fetch latest carrydown entry
if ($from_date && $to_date) {
    $stmt = $conn->prepare("SELECT amount, description 
                            FROM carry_down 
                            WHERE username=? 
                              AND created_at BETWEEN ? AND ?
                            ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("sss", $username, $from_date, $to_date);
} else {
    $stmt = $conn->prepare("SELECT amount, description 
                            FROM carry_down 
                            WHERE username=? 
                            ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $username);
}
$stmt->execute();
$latest_carrydown = $stmt->get_result()->fetch_assoc();

$total_carry = $latest_carrydown['amount'] ?? 0;
$carrydown_tooltip = "";
if ($latest_carrydown) {
    $sign = ($latest_carrydown['amount'] >= 0 ? '+' : '-');
    $carrydown_tooltip = $sign . number_format($latest_carrydown['amount'], 2) . " : " . $latest_carrydown['description'];
}

// Invoice handling
$stmt = $conn->prepare("SELECT invoice_no FROM invoices WHERE username=? AND from_date=? AND to_date=? AND region=?");
$stmt->bind_param("ssss", $username, $from_date, $to_date, $region_filter);
$stmt->execute();
$invoice_result = $stmt->get_result();

if ($invoice_result->num_rows > 0) {
    $invoice_no = str_pad($invoice_result->fetch_assoc()['invoice_no'], 5, "0", STR_PAD_LEFT);
} else {
    $max_inv = $conn->query("SELECT MAX(invoice_no) as max_inv FROM invoices")->fetch_assoc()['max_inv'];
    $next_invoice = $max_inv ? $max_inv + 1 : 1;
    $stmt = $conn->prepare("INSERT INTO invoices (username, from_date, to_date, region, invoice_no) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $from_date, $to_date, $region_filter, $next_invoice);
    $stmt->execute();
    $invoice_no = str_pad($next_invoice, 5, "0", STR_PAD_LEFT);
}

// Sort expenses by date ascending
usort($all_expenses, function($a, $b) { return strtotime($a['date']) <=> strtotime($b['date']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Expense Report | VisionAngles</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="assets/user_report.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style> .total-spend { background-color: #f2f2f2; padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; } /* Table general styling */ .table { border: 2px solid black; /* outer border */ border-collapse: collapse; } th, td { border: 0.5px solid black; /* inner borders */ padding: 4px 6px; text-align: left; word-wrap: break-word; } @media print { body { -webkit-print-color-adjust: exact; color-adjust: exact; margin: 10mm; font-size: 12px; background: #fff; } /* Watermark */ body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('assets/vision1.png') no-repeat center center; background-size: 50%; opacity: 0.05; z-index: 9999; pointer-events: none; background-color: #aeb6bd; } /* Table print styling */ table { width: 100%; border-collapse: collapse; border: 2px solid black; /* outer border */ page-break-inside: auto; } thead { display: table-header-group; } tfoot { display: table-footer-group; } tr { page-break-inside: avoid; page-break-after: auto; } th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; color: black; } th, td { border: 0.5px solid black; /* inner borders */ padding: 4px 6px; font-size: 11px; text-align: left; word-wrap: break-word; color: black; } /* Hide interactive elements */ button, input, select { display: none !important; } .total-summary { display: flex; justify-content: flex-end; text-align: right; margin-top: 20px; } } /* Modal styling (not affected by print) */ .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); } .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 5px; width: 80%; max-width: 700px; } .close-btn { float: right; font-size: 24px; cursor: pointer; } .modal table { width: 100%; border-collapse: collapse; margin-top: 10px; } .modal table th, .modal table td { border: 0.5px solid #ddd; padding: 8px; text-align: center; } .modal table th { background-color: #f2f2f2; } </style>
</head>
<body>

<div class="report-header">
    <img src="assets/visionlogo.jpg" alt="Company Logo">
    <h2>Expense Report</h2>
    <div style="text-align:right; font-weight:bold;">EX: <span id="invoice_no"><?php echo $invoice_no; ?></span></div>
</div>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <div class="form-group"><input type="date" class="form-control form-control-sm" id="from_date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required></div>
    <div class="form-group"><input type="date" class="form-control form-control-sm" id="to_date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required></div>
    <div class="form-group">
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
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" type="submit">Search</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location='user_report.php?username=<?php echo urlencode($username); ?>'">Clear</button>
        <button class="btn btn-outline-success btn-sm" type="button" onclick="confirmInvoicePrint()">Print</button>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="window.location='export_excel.php?username=<?php echo urlencode($username); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&region=<?php echo urlencode($region_filter); ?>'">Export</button>
        <button class="btn btn-info btn-sm" <?= $carrydown_exists ? 'disabled' : '' ?> onclick="openCarrydownModal()">Add Carrydown</button>
        
        <button type="button" class="btn btn-danger btn-sm" onclick="window.location.href='dashboard_admin.php'">Back</button>
    </div>
</form>

<div class="print-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
    <div>
        <strong>Username:</strong> <?php echo htmlspecialchars(ucfirst($username)); ?> 
        | <strong>Region:</strong> <?php echo htmlspecialchars($region_filter); ?><br>
        <strong>From:</strong> <?php echo htmlspecialchars($from_date); ?> 
        <strong>To:</strong> <?php echo htmlspecialchars($to_date); ?>
    </div>
    <div style="text-align:right;">
        <strong>Carrydown:</strong> 
        SAR <?php echo number_format($total_carry, 2); ?>
        <?php if ($carrydown_tooltip): ?>
            <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="left" title="<?php echo htmlspecialchars($carrydown_tooltip); ?>"></i>
        <?php endif; ?><?php if($carrydown_exists): ?>
            <i class="bi bi-pencil-square text-success ms-1" style="cursor:pointer;" onclick="openCarrydownModal()"></i>
        <?php endif; ?><br>
        <strong>Advance:</strong> SAR <?php echo number_format($total_adv, 2); ?>
    </div>
</div>

<!-- expenses table remains same -->
<div class="table-responsive">
    <table class="table align-middle">
        <thead class="table" style="background-color:grey;">
            <tr>
                <th>SI. No</th>
                <th>Date</th>
                <th>Type</th> <!-- Added Type column -->
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
                <td><?= date("d", strtotime($row['date'])) . "&nbsp;" . date("M", strtotime($row['date'])) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td> <!-- Display Type -->
                <td><?= htmlspecialchars($row['division']) ?></td>
                <td><?= htmlspecialchars($row['company']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['store']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>SAR <?= number_format($row['amount'], 2) ?></td>
                <td>
                    <div class="d-flex flex-column flex-md-row gap-1">
                        <button class="btn btn-warning btn-sm" onclick="window.location='edit_expense.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>'">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="if(confirm('Are you sure?')) window.location='delete_expense.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>'">Delete</button>
                        <span><?= htmlspecialchars($row['remark'] ?? '') ?></span>
                    </div>
                </td>
            </tr>
            <?php $si++; endforeach; else: ?>
            <tr>
                <td colspan="10" class="text-center">No expenses found for this user.</td>
            </tr>
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
</div>

<div style="text-align:right; margin-top:10px;">
    <button onclick="window.location='manage_advance.php?username=<?= urlencode($username) ?>'">Manage Advances</button>
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

<script>
function confirmInvoicePrint(){
    if(confirm("Do you want a NEW invoice number?")){
        fetch("generate_invoice.php?username=<?= urlencode($username) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&region=<?= urlencode($region_filter) ?>")
        .then(res=>res.text()).then(inv=>{
            document.getElementById("invoice_no").innerText=inv;
            window.print();
        });
    } else window.print();
}

function openCarrydownModal() {
    document.getElementById("carrydownModal").style.display = "block";
}
function closeCarrydownModal() {
    document.getElementById("carrydownModal").style.display = "none";
}

// Enable Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
