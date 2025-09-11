<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

include 'config.php';

$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $username;

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

// Function to fetch expenses per category
function get_expenses($conn, $table, $from_date, $to_date, $region_filter, $username) {
    // Handle vehicle_expense separately (uses date instead of date)
    $date_col = ($table === "vehicle_expense") ? "date" : "date";

    $sql = "SELECT *, $date_col AS date, submitted FROM $table WHERE username=?";
    $params = [$username];
    $types = "s";

    if ($from_date && $to_date) {
        $sql .= " AND $date_col BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $from_date;
        $params[] = $to_date;
    }

    if ($region_filter !== 'All') {
        $sql .= " AND region=?";
        $types .= "s";
        $params[] = $region_filter;
    }

    $sql .= " ORDER BY $date_col DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL error ($table): " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch all expense categories including Vehicle
$fuel_expenses   = get_expenses($conn, 'fuel_expense', $from_date, $to_date, $region_filter, $username);
$food_expenses   = get_expenses($conn, 'food_expense', $from_date, $to_date, $region_filter, $username);
$room_expenses   = get_expenses($conn, 'room_expense', $from_date, $to_date, $region_filter, $username);
$other_expenses  = get_expenses($conn, 'other_expense', $from_date, $to_date, $region_filter, $username);
$tools_expenses  = get_expenses($conn, 'tools_expense', $from_date, $to_date, $region_filter, $username);
$labour_expenses = get_expenses($conn, 'labour_expense', $from_date, $to_date, $region_filter, $username);
$accessories_expense = get_expenses($conn, 'accessories_expense', $from_date, $to_date, $region_filter, $username);
$tv_expense      = get_expenses($conn, 'tv_expense', $from_date, $to_date, $region_filter, $username);
$vehicle_expense = get_expenses($conn, 'vehicle_expense', $from_date, $to_date, $region_filter, $username);

// Total spend
$total_amount = 0;
foreach ([$fuel_expenses, $food_expenses, $room_expenses, $other_expenses, $tools_expenses, $labour_expenses, $accessories_expense, $tv_expense, $vehicle_expense] as $expenses) {
    while($row = $expenses->fetch_assoc()) {
        $total_amount += $row['amount'];
    }
    $expenses->data_seek(0);
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
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit();
}

// Fetch total advance
if ($from_date && $to_date) {
    $adv_query = $conn->prepare("SELECT SUM(adv_amt) AS total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
    $adv_query->bind_param("sss", $username, $from_date, $to_date);
} else {
    $adv_query = $conn->prepare("SELECT SUM(adv_amt) AS total_adv FROM adv_amt WHERE username=?");
    $adv_query->bind_param("s", $username);
}
$adv_query->execute();
$adv_result = $adv_query->get_result();
$total_adv = $adv_result->fetch_assoc()['total_adv'] ?? 0;

// Fetch latest carrydown entry for balance calculation (not displayed)
if ($from_date && $to_date) {
    $cd_query = $conn->prepare("SELECT amount FROM carry_down WHERE username=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 1");
    $cd_query->bind_param("sss", $username, $from_date, $to_date);
} else {
    $cd_query = $conn->prepare("SELECT amount FROM carry_down WHERE username=? ORDER BY created_at DESC LIMIT 1");
    $cd_query->bind_param("s", $username);
}
$cd_query->execute();
$cd_result = $cd_query->get_result();
$total_carry = $cd_result->fetch_assoc()['amount'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Report | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<style>
@media print {
    .no-print { display: none !important; }
    body, html { width: 100%; margin:0; padding:0; background: white !important; }
    .container { width: 100% !important; max-width: 100% !important; padding:0 !important; }
    .report-header { display: block !important; text-align: center; margin-bottom: 20px; }
    table { page-break-inside: auto; width: 100% !important; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    .table-responsive { overflow: visible !important; }
    td.action-buttons, th.action-buttons { display: none !important; }
    td.print-only, th.print-only { display: table-cell !important; }
}
</style>
</head>
<body class="bg-light">

<div class="container my-4">

    <!-- Print Header -->
    <div class="report-header mb-4 text-center">
        <img src="assets/visionlogo.jpg" alt="Company Logo" class="img-fluid" style="max-height:80px;">
        <h2 class="mt-2">Expense Report</h2>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-3 mb-4 no-print">
        <div class="col-12 col-md-3">
            <label class="form-label">From</label>
            <input type="date" class="form-control" name="from_date" value="<?php echo $from_date; ?>" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">To</label>
            <input type="date" class="form-control" name="to_date" value="<?php echo $to_date; ?>" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Region</label>
            <select class="form-select" name="region">
                <option value="All" <?php if($region_filter=='All') echo 'selected'; ?>>All</option>
                <option value="Dammam" <?php if($region_filter=='Dammam') echo 'selected'; ?>>Dammam</option>
                <option value="Riyadh" <?php if($region_filter=='Riyadh') echo 'selected'; ?>>Riyadh</option>
                <option value="Jeddah" <?php if($region_filter=='Jeddah') echo 'selected'; ?>>Jeddah</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex flex-wrap align-items-end gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1 flex-md-grow-0">Search</button>
            <button type="button" onclick="window.location.href='report.php'" class="btn btn-outline-danger flex-grow-1 flex-md-grow-0">Clear Search</button>
            <button type="button" onclick="window.print()" class="btn btn-secondary flex-grow-1 flex-md-grow-0">Print</button>
            <button type="button" onclick="window.location.href='dashboard_user.php'" class="btn btn-outline-secondary flex-grow-1 flex-md-grow-0">Back</button>
        </div>
    </form>

    <!-- Info -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <span class="me-3"><strong>Username:</strong> <?php echo ucfirst($username); ?></span>
        <span class="me-3"><strong>Region:</strong> <?php echo $region_filter; ?></span>
        <span class="me-3"><strong>From:</strong> <?php echo $from_date; ?></span>
        <span><strong>To:</strong> <?php echo $to_date; ?></span>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>SI. No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Division</th>
                    <th>Company Name</th>
                    <th>Location</th>
                    <th>Store</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th class="action-buttons">Action</th>
                    <th class="print-only" style="display:none;">Remark</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $si = 1;
                $all_expenses = [];
                foreach ([
                    'Fuel'=>$fuel_expenses,
                    'Food'=>$food_expenses,
                    'Room'=>$room_expenses,
                    'Other'=>$other_expenses,
                    'Tools'=>$tools_expenses,
                    'Labour'=>$labour_expenses,
                    'Accessories'=>$accessories_expense,
                    'TV'=>$tv_expense,
                    'Vehicle'=>$vehicle_expense,
                ] as $type => $expenses) {
                    while($row = $expenses->fetch_assoc()) {
                        $row['type'] = $type;
                        $all_expenses[] = $row;
                    }
                }

                // Sort by date ascending
                usort($all_expenses, function($a, $b) {
                    return strtotime($a['date']) <=> strtotime($b['date']);
                });

                foreach ($all_expenses as $row) {
                    $is_submitted = $row['submitted'] ?? 0;
                    echo "<tr>
                        <td>{$si}</td>
                        <td>{$row['date']}</td>
                        <td>{$row['type']}</td>
                        <td>".($row['division'] ?? '')."</td>
                        <td>".($row['company'] ?? '')."</td>
                        <td>".($row['location'] ?? '')."</td>
                        <td>".($row['store'] ?? '')."</td>
                        <td>".($row['type'] === 'Vehicle' ? $row['service'] : ($row['description'] ?? ''))."</td>

                        <td>{$row['amount']}</td>
                        <td class='action-buttons'>";
                    
                    if (!$is_submitted) {
                        echo "<a href='edit_expense1.php?id={$row['id']}&table=" . strtolower($row['type']) . "_expense' class='btn btn-sm btn-warning mb-1'>Edit</a> ";
                        echo "<a href='submit_expense.php?id={$row['id']}&table=" . strtolower($row['type']) . "_expense' class='btn btn-sm btn-success mb-1'>Submit</a> ";
                        echo "<a href='delete_expense1.php?id={$row['id']}&table=" . strtolower($row['type']) . "_expense' class='btn btn-sm btn-danger mb-1' onclick=\"return confirm('Are you sure you want to delete this expense?');\">Delete</a>";
                    } else {
                        echo "<span class='badge bg-success'>Submitted</span>";
                    }

                    echo "</td>";
                    echo "<td class='print-only'></td>";
                    echo "</tr>";
                    $si++;
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <div class="row mt-4 g-2">
        <div class="col-12 col-md-4">
            <div class="p-2 border bg-white text-center"><strong>Advance:</strong> SAR <?php echo number_format($total_adv,2); ?></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-2 border bg-white text-center"><strong>Spend:</strong> SAR <?php echo number_format($total_amount,2); ?></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-2 border bg-white text-center"><strong>Balance:</strong> SAR <?php echo number_format(($total_adv + $total_carry) - $total_amount,2); ?></div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
