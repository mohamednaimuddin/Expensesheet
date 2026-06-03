
<?php
// Enable error reporting for debugging (remove on production after fixing)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}
include 'config.php';
include 'log_helper.php';

if (!isset($_GET['username']) || empty($_GET['username'])) {
    die("User not specified!");
}
$username = $_GET['username'];

$success_message = "";

// Handle Add/Edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adv_date'])) {
    $adv_date   = $_POST['adv_date'];
    $adv_amount = $_POST['adv_amount'];
    $cash_bank  = $_POST['cash_bank'];
    $remark     = $_POST['remark'] ?? '';
    $id         = $_POST['id'] ?? '';

    if (empty($adv_date) || !is_numeric($adv_amount) || $adv_amount <= 0 || empty($cash_bank)) {
        die("Invalid advance data submitted.");
    }

    if (!empty($id)) {
        // Update
        $stmt = $conn->prepare("UPDATE adv_amt SET date = ?, adv_amt = ?, cash_bank = ?, remark = ? WHERE id = ?");
        if (!$stmt) {
            die("SQL prepare failed (UPDATE): " . $conn->error);
        }
        $stmt->bind_param("sdssi", $adv_date, $adv_amount, $cash_bank, $remark, $id);
        if (!$stmt->execute()) {
            die("SQL execute failed (UPDATE): " . $stmt->error);
        }
        logActivity($conn, LOG_EDIT_ADVANCE, "Edited advance ID: $id for user: $username, Amount: $adv_amount SAR");
        header("Location: manage_advance.php?username=" . urlencode($username) . "&success=2");
        exit();
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO adv_amt (date, username, adv_amt, cash_bank, remark) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("SQL prepare failed (INSERT): " . $conn->error);
        }
        $stmt->bind_param("ssdss", $adv_date, $username, $adv_amount, $cash_bank, $remark);
        if (!$stmt->execute()) {
            die("SQL execute failed (INSERT): " . $stmt->error);
        }
        logActivity($conn, LOG_ADD_ADVANCE, "Added advance for user: $username, Amount: $adv_amount SAR via $cash_bank");
        header("Location: manage_advance.php?username=" . urlencode($username) . "&success=1");
        exit();
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM adv_amt WHERE id = ?");
    if (!$stmt) {
        die("SQL prepare failed (DELETE): " . $conn->error);
    }
    $stmt->bind_param("i", $delete_id);
    if (!$stmt->execute()) {
        die("SQL execute failed (DELETE): " . $stmt->error);
    }
    logActivity($conn, LOG_DELETE_ADVANCE, "Deleted advance ID: $delete_id for user: $username");
    header("Location: manage_advance.php?username=" . urlencode($username) . "&success=3");
    exit();
}

// Success alert check
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $success_message = "Advance added successfully!";
    elseif ($_GET['success'] == 2) $success_message = "Advance updated successfully!";
    elseif ($_GET['success'] == 3) $success_message = "Advance deleted successfully!";
}

// Fetch filter values
$month_filter = $_GET['month'] ?? date('Y-m'); // Default to current month
$mode         = $_GET['mode'] ?? '';

// Calculate start and end dates from month
$start_date = date('Y-m-01', strtotime($month_filter . '-01'));
$end_date = date('Y-m-t', strtotime($month_filter . '-01'));

// Build SQL query with filters
$sql = "SELECT * FROM adv_amt WHERE username = ?";
$params = [$username];
$types = "s";

// Filter by month
$sql .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
$params[] = $month_filter;
$types .= "s";

if (!empty($mode)) {
    $sql .= " AND cash_bank = ?";
    $params[] = $mode;
    $types .= "s";
}

$sql .= " ORDER BY date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$adv_result = $stmt->get_result();

// Calculate totals
$total_amount = 0.0;
$advances_data = [];

while ($row = $adv_result->fetch_assoc()) {
    $advances_data[] = $row;
    $total_amount += floatval($row['adv_amt']);
}

// Get carry down from carry_down table
$current_month = date("Y-m", strtotime($month_filter . '-01'));
$stmt = $conn->prepare("SELECT amount FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("ss", $username, $current_month);
$stmt->execute();
$carry_result = $stmt->get_result()->fetch_assoc();
$carry_down = floatval($carry_result['amount'] ?? 0);

// Calculate total expenses for the filtered month
$expense_tables = [
    'fuel_expense',
    'food_expense',
    'room_expense',
    'other_expense',
    'tools_expense',
    'labour_expense',
    'accessories_expense',
    'tv_expense',
    'vehicle_expense',
    'taxi_expense'
];

$total_expense = 0.0;
foreach ($expense_tables as $table) {
    $sql = "SELECT SUM(amount) as amt FROM $table WHERE username=? AND submitted=1 AND DATE_FORMAT(date, '%Y-%m') = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $month_filter);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $total_expense += $res ? floatval($res['amt']) : 0.0;
}

$grand_total = $total_amount + $carry_down;
$balance = $grand_total - $total_expense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Advances</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/loader.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">

<style>
    /* Watermark styles */
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        opacity: 0.06;
        z-index: -1;
        pointer-events: none;
    }
    .watermark img {
        width: 500px;
        height: auto;
    }
    
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; padding: 0; }
        .container { max-width: 100% !important; padding-left: 10px !important; padding-right: 10px !important; }
        #print-section { width: 100%; margin: 0 auto; }
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tfoot { display: table-footer-group; page-break-inside: avoid; }
        @page { margin: 10mm; }
        .watermark {
            display: block !important;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.06;
            z-index: -1;
        }
    }
    
    @media screen {
        .watermark { display: none; }
    }
</style>
</head>
<body class="container py-4">

<!-- Watermark for print -->
<div class="watermark">
    <img src="assets/visionlogo.jpg" alt="Watermark">
</div>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-container">
        <div class="loader-spinner"></div>
        <div class="loader-text">Processing...</div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show text-center no-print" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div id="print-section">
    <!--<div class="text-center mb-3">
        <img src="assets/visionlogo.jpg" alt="Company Logo" class="img-fluid mx-auto d-block" style="max-width:300px;">
    </div>-->

    <h2 class="text-center mb-2 fw-bold">Advance Report</h2>

    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4 justify-content-center text-center no-print">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

        <div class="col-md-4">
            <label class="form-label">Select Month</label>
            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month_filter); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Mode</label>
            <select name="mode" class="form-select">
                <option value="">All</option>
                <option value="Cash" <?php if($mode=='Cash') echo 'selected'; ?>>Cash</option>
                <option value="Al Rajhi Bank" <?php if($mode=='Al Rajhi Bank') echo 'selected'; ?>>Al Rajhi Bank</option>
                <option value="SNB Bank" <?php if($mode=='SNB Bank') echo 'selected'; ?>>SNB Bank</option>
            </select>
        </div>

        <div class="col-12 d-flex flex-wrap justify-content-center gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <button type="button" class="btn btn-warning btn-sm" onclick="window.location='manage_advance.php?username=<?php echo urlencode($username); ?>'">Clear Search</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.location.href='user_report.php?username=<?php echo urlencode($username); ?>'">Back</button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#advanceModal">+ Add Advance</button>
        </div>
    </form>

    <div class="mb-3">
        <strong>Month:</strong> <?php echo date('F Y', strtotime($month_filter . '-01')); ?>
        <?php if (!empty($mode)) echo " | <strong>Mode:</strong> " . htmlspecialchars($mode); ?>
    </div>

    <p class="mb-3 fw-bold">Username: <?php echo htmlspecialchars($username); ?></p>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center table-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width: 50px;">SI No</th>
                    <th style="width: 100px;">Date</th>
                    <th style="width: 100px;">Mode</th>
                    <th style="width: 120px;">Advance Amount</th>
                    <th style="width: 200px;">Remark</th>
                    <th class="no-print" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $si = 1;
            if (count($advances_data) > 0) {
                foreach ($advances_data as $row) {
                    echo "<tr>
                            <td>{$si}</td>
                            <td style='white-space: nowrap;'>{$row['date']}</td>
                            <td style='white-space: nowrap;'>{$row['cash_bank']}</td>
                            <td>{$row['adv_amt']}</td>
                            <td>".htmlspecialchars($row['remark'])."</td>
                            <td class='no-print'>
                                <button type='button' class='btn btn-sm btn-primary editBtn'
                                    data-id='{$row['id']}'
                                    data-date='{$row['date']}'
                                    data-amount='{$row['adv_amt']}'
                                    data-mode='{$row['cash_bank']}'
                                    data-remark='".htmlspecialchars($row['remark'], ENT_QUOTES)."'
                                    data-bs-toggle='modal'
                                    data-bs-target='#advanceModal'>Edit</button>
                                <button type='button' class='btn btn-sm btn-danger deleteBtn'
                                    data-id='{$row['id']}'
                                    data-bs-toggle='modal'
                                    data-bs-target='#deleteModal'>Delete</button>
                            </td>
                          </tr>";
                    $si++;
                }
            } else {
                echo "<tr><td colspan='6'>No advances found.</td></tr>";
            }
            ?>
            </tbody>
            <!-- Summary Section - Only at end of page -->
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end table-secondary" style="white-space: nowrap; border-left: none !important;">Total Advance:</th>
                    <td class="text-center fw-bold table-secondary" style="border-right: none !important;"><?php echo number_format($total_amount, 2); ?></td>
                </tr>
                <tr>
                    <th colspan="3" class="text-end table-secondary" style="white-space: nowrap; border-left: none !important;">Brought Down (B/d):</th>
                    <td class="text-center fw-bold table-secondary" style="border-right: none !important;"><?php echo number_format($carry_down, 2); ?></td>
                </tr>
                <tr>
                    <th colspan="3" class="text-end table-danger" style="white-space: nowrap; border-left: none !important;">Total Expense:</th>
                    <td class="text-center fw-bold table-danger" style="border-right: none !important;"><?php echo number_format($total_expense, 2); ?></td>
                </tr>
                <tr>
                    <th colspan="3" class="text-end fw-bold table-success" style="white-space: nowrap; border-left: none !important;">Carry down (C/d):</th>
                    <td class="text-center fw-bold table-success" style="border-right: none !important;"><?php echo number_format($balance, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Add/Edit Advance Modal -->
<div class="modal fade" id="advanceModal" tabindex="-1" aria-labelledby="advanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content shadow-lg rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold" id="advanceModalLabel">Add Advance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4">
        <form method="POST" action="">
          <input type="hidden" name="id" id="advId">
          <div class="mb-3">
            <label for="advDate" class="form-label">Date</label>
            <input type="date" name="adv_date" id="advDate" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="advAmount" class="form-label">Amount (SAR)</label>
            <input type="number" step="0.01" name="adv_amount" id="advAmount" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Mode</label>
            <select name="cash_bank" id="advMode" class="form-select" required>
                <option value="">Select Mode</option>
                <option value="Cash">Cash</option>
                <option value="Al Rajhi Bank">Al Rajhi Bank</option>
                <option value="SNB Bank">SNB Bank</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="advRemark" class="form-label">Remark</label>
            <textarea name="remark" id="advRemark" class="form-control" rows="2"></textarea>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-success btn-lg rounded-3" id="modalSubmitBtn">Add Advance</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this advance?</p>
      </div>
      <div class="modal-footer border-0">
        <form method="POST" action="">
          <input type="hidden" name="delete_id" id="deleteId">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="text-center mt-4 no-print">
    <a href="user_report.php?username=<?php echo urlencode($username); ?>" class="btn btn-outline-dark">Back</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const editButtons = document.querySelectorAll(".editBtn");
    const modalTitle = document.getElementById("advanceModalLabel");
    const submitBtn = document.getElementById("modalSubmitBtn");

    editButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("advId").value = this.dataset.id;
            document.getElementById("advDate").value = this.dataset.date;
            document.getElementById("advAmount").value = this.dataset.amount;
            document.getElementById("advMode").value = this.dataset.mode;
            document.getElementById("advRemark").value = this.dataset.remark || "";
            modalTitle.textContent = "Edit Advance";
            submitBtn.textContent = "Update Advance";
        });
    });

    // Reset modal for Add
    const addBtn = document.querySelector("[data-bs-target='#advanceModal']:not(.editBtn)");
    if (addBtn) {
        addBtn.addEventListener("click", function() {
            document.getElementById("advId").value = "";
            // Set default date to current date
            const today = new Date();
            const todayFormatted = today.getFullYear() + "-" + ("0" + (today.getMonth() + 1)).slice(-2) + "-" + ("0" + today.getDate()).slice(-2);
            document.getElementById("advDate").value = todayFormatted;
            document.getElementById("advAmount").value = "";
            document.getElementById("advMode").value = "";
            document.getElementById("advRemark").value = "";
            modalTitle.textContent = "Add Advance";
            submitBtn.textContent = "Add Advance";
        });
    }

    // Delete buttons
    const deleteButtons = document.querySelectorAll(".deleteBtn");
    deleteButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("deleteId").value = this.dataset.id;
        });
    });
    
    // Hide loader on page load
    document.getElementById('pageLoader').classList.add('hidden');
    
    // Show loader on form submit
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            document.getElementById('pageLoader').classList.remove('hidden');
            document.querySelector('#pageLoader .loader-text').textContent = 'Processing...';
        });
    });
    
    // Show loader on navigation
    document.querySelectorAll('a, button[onclick*="location"]').forEach(function(el) {
        el.addEventListener('click', function() {
            if (!this.closest('.modal')) {
                document.getElementById('pageLoader').classList.remove('hidden');
                document.querySelector('#pageLoader .loader-text').textContent = 'Loading...';
            }
        });
    });
});
</script>
</body>
</html>