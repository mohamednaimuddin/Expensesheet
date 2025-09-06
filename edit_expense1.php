<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// GET parameters
$id = $_GET['id'] ?? '';
$table_param = $_GET['table'] ?? '';

if (!is_numeric($id)) die("Invalid ID.");

// Map allowed table parameters
$table_map = [
    'food_expense' => 'food_expense',
    'fuel_expense' => 'fuel_expense',
    'other_expense' => 'other_expense',
    'room_expense' => 'room_expense',
    'tools_expense' => 'tools_expense',
    'labour_expense' => 'labour_expense',
    'accessories_expense' => 'accessories_expense'
];

$table_key = strtolower($table_param);
if (!array_key_exists($table_key, $table_map)) die("Invalid table.");

$table = $table_map[$table_key];

// Flags for field control
$is_tools = ($table === 'tools_expense');
$is_labour = ($table === 'labour_expense');

// Fetch expense
$sql = "SELECT * FROM $table WHERE id=? AND username=?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
$stmt->bind_param("is", $id, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
if (!$expense) die("Expense not found.");

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $division = $_POST['division'] ?? '';
    $company = $_POST['company'] ?? '';
    $location = $_POST['location'] ?? '';
    $store = $_POST['store'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? 0;

    if (!is_numeric($amount)) die("Invalid amount.");

    // Determine disabled fields (Tools or Recharge only)
    $disable_fields = $is_tools || ($division === 'Recharge');

    if ($disable_fields) {
        $update_sql = "UPDATE $table SET date=?, description=?, amount=? WHERE id=? AND username=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssdsi", $date, $description, $amount, $id, $_SESSION['username']);
    } else {
        $update_sql = "UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=? WHERE id=? AND username=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssdis", $date, $division, $company, $location, $store, $description, $amount, $id, $_SESSION['username']);
    }

    if ($update_stmt->execute()) {
        header("Location: report.php");
        exit();
    } else {
        die("Update failed: " . $update_stmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Expense | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9 col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">Edit Expense</h3>
                    <form method="post">
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($expense['date']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="division" class="form-label">Division</label>
                            <select id="division" name="division" class="form-select" <?= $is_tools ? 'disabled' : 'required' ?>>
                                <option value="">-- Select Division --</option>
                                <option value="Sales" <?= $expense['division']=='Sales'?'selected':'' ?>>Sales</option>
                                <option value="Project" <?= $expense['division']=='Project'?'selected':'' ?>>Project</option>
                                <option value="Service" <?= $expense['division']=='Service'?'selected':'' ?>>Service</option>
                                <option value="Installation" <?= $expense['division']=='Installation'?'selected':'' ?>>Installation</option>
                                <option value="Recharge" <?= $expense['division']=='Recharge'?'selected':'' ?>>Recharge</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="company" class="form-label">Company</label>
                            <select id="company" name="company" class="form-select" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                                <option value="">-- Select Company --</option>
                                <option value="Redtag" <?= $expense['company']=='Redtag'?'selected':'' ?>>Redtag</option>
                                <option value="Landmark" <?= $expense['company']=='Landmark'?'selected':'' ?>>Landmark</option>
                                <option value="Apparel" <?= $expense['company']=='Apparel'?'selected':'' ?>>Apparel</option>
                                <option value="Other" <?= $expense['company']=='Other'?'selected':'' ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($expense['location']); ?>" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="mb-3">
                            <label for="store" class="form-label">Store</label>
                            <input type="text" id="store" name="store" class="form-control" value="<?= htmlspecialchars($expense['store']); ?>" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" required><?= htmlspecialchars($expense['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (SAR)</label>
                            <input type="number" id="amount" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($expense['amount']); ?>" required>
                        </div>

                        <div class="d-flex justify-content-start gap-2">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">Back</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const divisionSelect = document.getElementById('division');
const companyField = document.getElementById('company');
const storeField = document.getElementById('store');
const locationField = document.getElementById('location');

function toggleFields() {
    // Tools or Recharge disables some fields, except Labour
    if (divisionSelect.value === 'Recharge' || <?= $is_tools ? 'true' : 'false' ?>) {
        if (!<?= $is_labour ? 'true' : 'false' ?>) {
            companyField.disabled = true;
            storeField.disabled = true;
            locationField.disabled = true;
        }
    } else {
        companyField.disabled = false;
        storeField.disabled = false;
        locationField.disabled = false;
    }
}

toggleFields();
divisionSelect.addEventListener('change', toggleFields);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
