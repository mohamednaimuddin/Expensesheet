<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

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
$month_year_filter = $_GET['month_year'] ?? '';
$username_filter = trim($_GET['username'] ?? '');
$region_filter = $_GET['region'] ?? 'All';

// --- Build SQL filter ---
$filter_sql = '';
if ($month_year_filter) {
    $parts = explode('-', $month_year_filter);
    if (count($parts) == 2) {
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $filter_sql .= " AND YEAR(date) = $year AND MONTH(date) = $month";
    }
}

if ($username_filter !== '') {
    $username_safe = $conn->real_escape_string($username_filter);
    $filter_sql .= " AND username='$username_safe'";
}

if ($region_filter !== '' && $region_filter !== 'All') {
    $region_safe = $conn->real_escape_string($region_filter);
    $filter_sql .= " AND region='$region_safe'";
}

// --- Fetch unique usernames ---
$usernames = [];
foreach (['fuel_expense','room_expense','other_expense','tools_expense','labour_expense', 'accessories_expense'] as $table) {
    $res = $conn->query("SELECT DISTINCT username FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $usernames[$row['username']] = $row['username'];
        }
    }
}
ksort($usernames);

// --- Fetch pending bills ---
$query = "
    SELECT 'Fuel' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM fuel_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Room' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM room_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Other' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM other_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Tools' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM tools_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Labour' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM labour_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Accessories' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM accessories_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    ORDER BY date ASC
";

$result = $conn->query($query);
if (!$result) die("Query failed: " . $conn->error);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Bills | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="icon" type="image/png" href="assets/vision.ico">

<style>
body { background: #f8f9fa; }
.pending { background-color: #fff3cd !important; }

@media print {
    body, html { height: 100%; margin: 0; padding: 0; background: #fff; -webkit-print-color-adjust: exact; }
    #filterForm, .action-btn, .print-btn, .back-btn { display: none !important; }
    .print-section { display: block; page-break-inside: avoid; }
    .table-responsive { overflow: visible !important; page-break-inside: avoid; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; page-break-inside: auto; }
    th, td { border: 0.5px solid black; padding: 2px 3px; word-wrap: break-word; white-space: normal; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr { page-break-inside: avoid; }
    .report-footer { display: block; margin-top: 10px; page-break-inside: avoid; page-break-after: auto; width: 100%; }
    @page { size: A4 portrait; margin: 8mm; }
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
});
</script>
</head>
<body>

<div class="container my-4">

    <div class="text-center mb-4 print-header">
        <img src="assets/visionlogo.jpg" alt="Company Logo" style="max-height:80px;">
        <h2>No Bill Payment Slip</h2>
    </div>

    <form method="get" id="filterForm" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label for="month_year" class="form-label">Select Month & Year</label>
            <input type="month" id="month_year" name="month_year" class="form-control"
                   value="<?= htmlspecialchars($month_year_filter ?: date('Y-m')) ?>">
        </div>
        <div class="col-md-2">
            <label for="region" class="form-label">Region</label>
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
            <label class="form-label">Username</label>
            <select class="form-select" name="username">
                <option value="">All Users</option>
                <?php foreach($usernames as $uname): ?>
                    <option value="<?= htmlspecialchars($uname) ?>" <?= ($username_filter==$uname)?'selected':'' ?>>
                        <?= htmlspecialchars($uname) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary">Search</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard_admin.php'">Back</button>
            <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Print</button>
        </div>
    </form>

    <div class="mb-3 d-flex justify-content-between">
        <div><strong>Month:</strong> <?= $month_year_filter ? date('F, Y', strtotime($month_year_filter . '-01')) : 'All Months' ?></div>
        <div><strong>User:</strong> <?= $username_filter ? htmlspecialchars($username_filter) : 'All Users' ?></div>
        <div><strong>Region:</strong> <?= $region_filter ?></div>
    </div>

    <div class="print-section">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" style="width:100%;">
                <thead class="table-dark">
                    <tr>
                        <th>SI No</th>
                        <th>Date</th>
                        <th>Division</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Store</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i=1; $total_amount=0;
                    while($row=$result->fetch_assoc()):
                        $total_amount += (float)$row['amount'];
                        $is_pending = empty($row['bill']) || strtolower($row['bill'])=='no';
                    ?>
                    <tr class="<?= $is_pending ? 'pending' : '' ?>">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['division'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['location'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['store'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['expense_category']) ?></td>
                        <td><?= htmlspecialchars($row['amount']) ?></td>
                        <td>
                            <?php if($is_pending): ?>
                                <button class="btn btn-success btn-sm action-btn"
                                        data-id="<?= $row['id'] ?>"
                                        data-category="<?= $row['expense_category'] ?>"
                                        data-value="yes">Yes</button>
                            <?php else: ?>
                                <?= htmlspecialchars($row['bill']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                        <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="report-footer d-print-block mt-3">
            <div style="display:flex; justify-content:space-between;">
                <div style="width:33%; text-align:left;">Prepared By:</div>
                <div style="width:33%; text-align:center;">Verified By:</div>
                <div style="width:33%; text-align:right;">Approved By:</div>
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
