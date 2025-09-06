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

// Fetch expenses function (only submitted = 1)
function get_expenses($conn, $table, $username, $from_date = '', $to_date = '', $region = 'All') {
    $allowed_tables = ['fuel_expense', 'food_expense', 'room_expense', 'other_expense'];
    if (!in_array($table, $allowed_tables)) die("Invalid table specified");

    $sql = "SELECT * FROM $table WHERE username=? AND submitted=1";
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
];

$total_amount = 0;
$all_expenses = [];
foreach ($expenses_list as $type => $result) {
    while ($row = $result->fetch_assoc()) {
        $row['type'] = $type;
        $all_expenses[] = $row;
        $total_amount += $row['amount'];
    }
}

// Fetch advance
if ($from_date && $to_date) {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?");
    $stmt->bind_param("sss", $username, $from_date, $to_date);
} else {
    $stmt = $conn->prepare("SELECT SUM(adv_amt) as total_adv FROM adv_amt WHERE username=?");
    $stmt->bind_param("s", $username);
}
$stmt->execute();
$total_adv = $stmt->get_result()->fetch_assoc()['total_adv'] ?? 0;

// Sort expenses by date ascending
usort($all_expenses, fn($a,$b) => strtotime($a['date']) <=> strtotime($b['date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Expense Report | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.total-spend { background-color: #f2f2f2; padding: 10px; border-radius: 5px; text-align: center; font-weight: bold; }
@media print { body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('assets/vision1.png') no-repeat center center; background-size: 50%; opacity: 0.1; z-index: 9999; pointer-events: none; background-color:#aeb6bd; } table, th, td { border: 1px solid black; border-collapse: collapse; padding: 6px; text-align: left; color:black; } .total-summary { display: flex; justify-content: flex-end; text-align: right; margin-top: 40px; } }
</style>
</head>
<body class="bg-light">

<div class="container my-4">
    <div class="report-header mb-4 text-center">
        <img src="assets/visionlogo.jpg" alt="Company Logo" style="height:80px;">
        <h2 class="mt-2">Expense Summary Report</h2>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-3 mb-4">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
        <div class="col-md-3"><label>From</label><input type="date" class="form-control" name="from_date" value="<?php echo $from_date; ?>" required></div>
        <div class="col-md-3"><label>To</label><input type="date" class="form-control" name="to_date" value="<?php echo $to_date; ?>" required></div>
        <div class="col-md-3">
            <label>Region</label>
            <select class="form-select" name="region">
                <?php $regions = ['All','Dammam','Riyadh','Jeddah']; foreach ($regions as $region) { $sel = ($region==$region_filter) ? 'selected' : ''; echo "<option value='$region' $sel>$region</option>"; } ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <button type="button" onclick="window.print()" class="btn btn-secondary">Print</button>
            <button type="button" onclick="window.history.back()" class="btn btn-outline-secondary">Back</button>
            <button type="button" onclick="window.location.href='user_report.php'" class="btn btn-outline-danger">Clear Filter</button>
        </div>
    </form>

    <!-- Info -->
    <div class="mb-3">
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
                <th>Company</th>
                <th>Location</th>
                <th>Store</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if(count($all_expenses) > 0):
            $si=1;
            foreach($all_expenses as $row):
        ?>
            <tr>
                <td><?= $si ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= $row['type'] ?></td>
                <td><?= htmlspecialchars($row['division']) ?></td>
                <td><?= htmlspecialchars($row['company']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['store']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>SAR <?= number_format($row['amount'],2) ?></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="window.location='edit_expense1.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>'">Edit</button>
                    <button class="btn btn-success btn-sm" onclick="if(confirm('Submit this expense?')) window.location='submit_expense.php?id=<?= $row['id'] ?>&type=<?= $row['type'] ?>'">Submit</button>
                </td>
            </tr>
        <?php $si++; endforeach;
        else: ?>
            <tr><td colspan="10" class="text-center">No submitted expenses found.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="8" class="text-end fw-bold">Balance:</td>
                <td colspan="2">SAR <?= number_format($total_adv-$total_amount, 2) ?></td>
            </tr>
        </tfoot>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
