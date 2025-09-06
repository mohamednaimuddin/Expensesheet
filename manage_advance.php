<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include 'config.php';

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
        $stmt->bind_param("sdssi", $adv_date, $adv_amount, $cash_bank, $remark, $id);
        $stmt->execute();
        header("Location: manage_advance.php?username=" . urlencode($username) . "&success=2");
        exit();
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO adv_amt (date, username, adv_amt, cash_bank, remark) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $adv_date, $username, $adv_amount, $cash_bank, $remark);
        $stmt->execute();
        header("Location: manage_advance.php?username=" . urlencode($username) . "&success=1");
        exit();
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM adv_amt WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
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
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$mode       = $_GET['mode'] ?? '';

// Build SQL query with filters
$sql = "SELECT * FROM adv_amt WHERE username = ?";
$params = [$username];
$types = "s";

if (!empty($start_date)) {
    $sql .= " AND date >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $sql .= " AND date <= ?";
    $params[] = $end_date;
    $types .= "s";
}
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Advances</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">

<style>
    @media print {
        .no-print { display: none !important; }
        body { margin: 0; padding: 0; }
        #print-section { width: 100%; margin: 0 auto; }
    }
</style>
</head>
<body class="container py-4">

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show text-center no-print" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div id="print-section">
    <div class="text-center mb-3">
        <img src="assets/visionlogo.jpg" alt="Company Logo" class="img-fluid mx-auto d-block" style="max-width:300px;">
    </div>

    <h2 class="text-center mb-2 fw-bold">Advance Report</h2>

    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4 justify-content-center text-center no-print">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Mode</label>
            <select name="mode" class="form-select">
                <option value="">All</option>
                <option value="Cash" <?php if($mode=='Cash') echo 'selected'; ?>>Cash</option>
                <option value="Al Rajhi Bank" <?php if($mode=='Al Rajhi Bank') echo 'selected'; ?>>Al Rajhi Bank</option>
                <option value="SNB Bank" <?php if($mode=='SNB Bank') echo 'selected'; ?>>SNB Bank</option>Balance C/D
                <option value="Balance C/D" <?php if($mode=='Balance C/D') echo 'selected'; ?>>Balance C/D</option>
            </select>
        </div>

        <div class="col-12 d-flex flex-wrap justify-content-center gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <button type="button" class="btn btn-warning btn-sm" onclick="window.location='manage_advance.php?username=<?php echo urlencode($username); ?>'">Clear Filters</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.location.href='dashboard_admin.php'">Back</button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#advanceModal">+ Add Advance</button>
        </div>
    </form>

    <?php if (!empty($start_date) || !empty($end_date) || !empty($mode)): ?>
    <div class="mb-3">
        <?php if (!empty($start_date)) echo "<strong>From:</strong> " . htmlspecialchars($start_date) . " "; ?>
        <?php if (!empty($end_date)) echo "<strong>To:</strong> " . htmlspecialchars($end_date) . " "; ?>
        <?php if (!empty($mode)) echo "<strong>Mode:</strong> " . htmlspecialchars($mode); ?>
    </div>
    <?php endif; ?>

    <p class="mb-3 fw-bold">Username: <?php echo htmlspecialchars($username); ?></p>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>SI No</th>
                    <th>Date</th>
                    <th>Advance Amount</th>
                    <th>Mode</th>
                    <th>Remark</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $si = 1;
            if ($adv_result->num_rows > 0) {
                while ($row = $adv_result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$si}</td>
                            <td>{$row['date']}</td>
                            <td>{$row['adv_amt']}</td>
                            <td>{$row['cash_bank']}</td>
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
                <option value="Balance C/D">Balance C/D</option>
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
            document.getElementById("advDate").value = "";
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
});
</script>
</body>
</html>
