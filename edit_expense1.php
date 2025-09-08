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
    'accessories_expense' => 'accessories_expense',
    'tv_expense' => 'tv_expense' // Added TV expense
];

$table_key = strtolower($table_param);
if (!array_key_exists($table_key, $table_map)) die("Invalid table.");

$table = $table_map[$table_key];

// Flags for field control
$is_tools = ($table === 'tools_expense');
$is_labour = ($table === 'labour_expense');
$is_tv = ($table === 'tv_expense');

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
    $submitted = isset($_POST['submitted']) ? 1 : 0;

    if (!is_numeric($amount)) die("Invalid amount.");

    if ($is_tv) {
        // TV expense: no submitted update needed if you want
        $update_sql = "UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=? WHERE id=? AND username=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssdii", $date, $division, $company, $location, $store, $description, $amount, $id, $_SESSION['username']);
    } else {
        // Regular expenses
        $disable_fields = $is_tools || ($division === 'Recharge');

        if ($disable_fields) {
            $update_sql = "UPDATE $table SET date=?, description=?, amount=?, submitted=? WHERE id=? AND username=?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sdiii", $date, $description, $amount, $submitted, $id, $_SESSION['username']);
        } else {
            $update_sql = "UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=?, submitted=? WHERE id=? AND username=?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssdiss", $date, $division, $company, $location, $store, $description, $amount, $submitted, $id, $_SESSION['username']);
        }
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

                        <?php if (!$is_tv): ?>
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
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" id="company" name="company" class="form-control" value="<?= htmlspecialchars($expense['company'] ?? ''); ?>" <?= $is_tools ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($expense['location'] ?? ''); ?>" <?= $is_tools ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="mb-3">
                            <label for="store" class="form-label">Store</label>
                            <input type="text" id="store" name="store" class="form-control" value="<?= htmlspecialchars($expense['store'] ?? ''); ?>" <?= $is_tools ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" required><?= htmlspecialchars($expense['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (SAR)</label>
                            <input type="number" id="amount" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($expense['amount']); ?>" required>
                        </div>

                        <?php if (!$is_tv): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="submitted" name="submitted" <?= $expense['submitted'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="submitted">Submitted</label>
                        </div>
                        <?php endif; ?>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
