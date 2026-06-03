<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get filters
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

// Pagination settings
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Fetch expenses function (submitted=1)
function get_expenses($conn, $table, $from_date = '', $to_date = '', $region = 'All') {
    $allowed_tables = [
        'fuel_expense', 'food_expense', 'room_expense', 
        'other_expense', 'tools_expense', 'labour_expense', 
        'accessories_expense','tv_expense', 'vehicle_expense', 'taxi_expense'
    ];
    if (!in_array($table, $allowed_tables)) die("Invalid table specified");

    // Column mapping if description is a different field
    $column_map = [
        'vehicle_expense' => 'service',
        'taxi_expense' => "CONCAT(from_location, ' → ', to_location)"
    ];
    $description_col = $column_map[$table] ?? 'description';

    // Extra optional columns (only if table has them)
    $extra_columns = [
        'division', 'company', 'location', 'store', 'region', 'remark'
    ];

    // Build SELECT columns dynamically
    $columns = ["id", "username", "$description_col AS description", "amount", "date"];
    foreach ($extra_columns as $col) {
        // Check if column exists in this table
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($check && $check->num_rows > 0) {
            $columns[] = $col;
        } else {
            // Add a blank field for consistency
            $columns[] = "'' AS $col";
        }
    }

    $columns_sql = implode(", ", $columns);

    $sql = "SELECT $columns_sql FROM $table WHERE submitted=1";

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

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $stmt->get_result();
}


// Fetch all expenses for all users
$tables = [
    'fuel_expense'        => 'Fuel',
    'food_expense'        => 'Food',
    'room_expense'        => 'Room',
    'other_expense'       => 'Other',
    'tools_expense'       => 'Tools',
    'labour_expense'      => 'Labour',
    'accessories_expense' => 'Accessories', // ✅ Added Accessories
    'tv_expense'          => 'tv',
    'vehicle_expense'     => 'vehicle',
    'taxi_expense'        => 'Taxi'
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

// Pagination calculations
$total_records = count($all_expenses);
$total_pages = ceil($total_records / $per_page);
$page = min($page, max(1, $total_pages)); // Ensure page is within valid range
$offset = ($page - 1) * $per_page;

// Slice the array for current page
$paged_expenses = array_slice($all_expenses, $offset, $per_page);

// Build query string for pagination links
function build_query_string($params = []) {
    $current = $_GET;
    unset($current['page']); // Remove page from current params
    $merged = array_merge($current, $params);
    return http_build_query($merged);
}

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
<link href="assets/dashboard_admin.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<link href="assets/all_user_expense.css" rel="stylesheet">
</head>
<body>

<!-- Animated background blobs (matches dashboard) -->
<div class="bg-blobs" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
</div>

<div class="app-container page-wrap">

    <div class="report-header">
        <img src="assets/visionlogo.jpg" alt="Company Logo">
        <h2><i class="fa-solid fa-chart-line me-2" style="color:var(--blue-600);"></i>All Users Expense Report</h2>
    </div>

    <form method="get" class="filter-card no-print">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="form-group">
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?= htmlspecialchars($from_date) ?>" required>
            </div>
            <div class="form-group">
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?= htmlspecialchars($to_date) ?>" required>
            </div>
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
            <div class="form-group">
                <select class="form-select form-select-sm" name="per_page">
                    <?php
                    $per_page_options = [25, 50, 100, 200, 500];
                    foreach ($per_page_options as $opt) {
                        $sel = ($per_page == $opt) ? 'selected' : '';
                        echo "<option value=\"$opt\" $sel>$opt per page</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="d-flex flex-wrap gap-2 ms-auto">
                <button class="btn btn-pill btn-search" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <button class="btn btn-pill btn-clear" type="button" onclick="window.location='all_user_expenses.php'"><i class="fa-solid fa-rotate-left"></i> Clear</button>
                <button class="btn btn-pill btn-print" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                <button class="btn btn-pill btn-back" type="button" onclick="window.location='<?= ($_SESSION['role'] === 'superadmin') ? 'dashboard_superadmin.php' : 'dashboard_admin.php' ?>'"><i class="fa-solid fa-arrow-left"></i> Back</button>
            </div>
        </div>
    </form>

    <div class="info-strip print-header">
        <div class="d-flex flex-wrap gap-2">
            <span class="chip"><i class="fa-solid fa-location-dot"></i> Region: <?= htmlspecialchars($region_filter) ?></span>
            <span class="chip"><i class="fa-solid fa-calendar-day"></i> From: <?= htmlspecialchars($from_date) ?></span>
            <span class="chip"><i class="fa-solid fa-calendar-check"></i> To: <?= htmlspecialchars($to_date) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="chip advance"><i class="fa-solid fa-wallet"></i> Advance: SAR <?= number_format($total_adv, 2) ?></span>
            <span class="chip records"><i class="fa-solid fa-list"></i> Showing <?= count($paged_expenses) ?> of <?= $total_records ?></span>
        </div>
    </div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
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
            <?php if(count($paged_expenses) > 0): $si = $offset + 1; foreach($paged_expenses as $row): ?>
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
        <tfoot>
            <tr>
                <td colspan="9" class="text-end">Page Total:</td>
                <td colspan="2">SAR <?= number_format(array_sum(array_column($paged_expenses, 'amount')), 2) ?></td>
            </tr>
            <tr>
                <td colspan="9" class="text-end">Grand Total:</td>
                <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="9" class="text-end">Balance:</td>
                <td colspan="2">SAR <?= number_format($total_adv - $total_amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-3 no-print">
    <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <!-- First Page -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= build_query_string(['page' => 1]) ?>">First</a>
        </li>
        
        <!-- Previous Page -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= build_query_string(['page' => $page - 1]) ?>">&laquo;</a>
        </li>
        
        <!-- Page Numbers -->
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
            <a class="page-link" href="?<?= build_query_string(['page' => $i]) ?>"><?= $i ?></a>
        </li>
        <?php endfor;
        
        if ($end_page < $total_pages) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        ?>
        
        <!-- Next Page -->
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= build_query_string(['page' => $page + 1]) ?>">&raquo;</a>
        </li>
        
        <!-- Last Page -->
        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= build_query_string(['page' => $total_pages]) ?>">Last</a>
        </li>
    </ul>
    <p class="text-center text-muted small">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> total records)</p>
</nav>
<?php endif; ?>

<div class="report-footer">
    <div>Prepared By: Admin</div>
    <div>Verified By:</div>
    <div>Approved By:</div>
</div>

</div><!-- /.app-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
