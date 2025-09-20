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

// Allowed table mapping
$table_map = [
    'food_expense'        => 'food_expense',
    'fuel_expense'        => 'fuel_expense',
    'other_expense'       => 'other_expense',
    'room_expense'        => 'room_expense',
    'tools_expense'       => 'tools_expense',
    'labour_expense'      => 'labour_expense',
    'accessories_expense' => 'accessories_expense',
    'tv_expense'          => 'tv_expense',
    'vehicle_expense'     => 'vehicle_expense'
];

$table_key = strtolower($table_param);
if (!array_key_exists($table_key, $table_map)) die("Invalid table.");

$table = $table_map[$table_key];

// Flags
$is_tools   = ($table === 'tools_expense');
$is_labour  = ($table === 'labour_expense');
$is_tv      = ($table === 'tv_expense');
$is_vehicle = ($table === 'vehicle_expense');

// Fetch expense
$sql = "SELECT * FROM $table WHERE id=? AND username=?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("is", $id, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
if (!$expense) die("Expense not found.");

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_vehicle) {
        // Vehicle expense: only update service + amount
        $service = $_POST['service'] ?? '';
        $amount  = $_POST['amount'] ?? 0;

        if (!is_numeric($amount)) die("Invalid amount.");

        $update_sql = "UPDATE $table SET service=?, amount=? WHERE id=? AND username=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sdis", $service, $amount, $id, $_SESSION['username']);
    } else {
        // Other expenses
        $date        = $_POST['date'] ?? '';
        $division    = $_POST['division'] ?? '';
        $company     = $_POST['company'] ?? '';
        $location    = $_POST['location'] ?? '';
        $store       = $_POST['store'] ?? '';
        $description = $_POST['description'] ?? '';
        $amount      = $_POST['amount'] ?? 0;

        if (!is_numeric($amount)) die("Invalid amount.");

        $update_sql = "UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=? WHERE id=? AND username=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            "ssssssdsi",
            $date, $division, $company, $location, $store, $description, $amount, $id, $_SESSION['username']
        );
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
                        <?php if ($is_vehicle): ?>
                            <div class="mb-3">
                                <label class="form-label">Service</label>
                                <select name="service" class="form-select" required>
                                    <?php foreach(['Engine Oil','Gear Oil','Tyre','Brake Pad','Brake Oil','Fuel Injection','Other'] as $srv): ?>
                                        <option value="<?= $srv ?>" <?= $expense['service']==$srv?'selected':'' ?>><?= $srv ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount (SAR)</label>
                                <input type="number" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($expense['amount']); ?>" required>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($expense['date']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Division</label>
                                <select name="division" id="division" class="form-select" <?= $is_tools ? 'disabled' : 'required' ?>>
                                    <option value="">-- Select Division --</option>
                                    <?php foreach(['Sales','Project','Service','Installation','Recharge','Other'] as $div): ?>
                                        <option value="<?= $div ?>" <?= $expense['division']==$div?'selected':'' ?>><?= $div ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <select name="company" id="company" class="form-select" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                                    <option value="">-- Select Company --</option>
                                    <?php foreach(['Redtag','Landmark','Apparel','Other'] as $comp): ?>
                                        <option value="<?= $comp ?>" <?= $expense['company']==$comp?'selected':'' ?>><?= $comp ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" id="location" class="form-control" value="<?= htmlspecialchars($expense['location']); ?>" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Store</label>
                                <input type="text" name="store" id="store" class="form-control" value="<?= htmlspecialchars($expense['store']); ?>" <?= ($is_tools || $expense['division']=='Recharge') && !$is_labour ? 'disabled' : 'required' ?>>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($expense['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount (SAR)</label>
                                <input type="number" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($expense['amount']); ?>" required>
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
