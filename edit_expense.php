<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Validate request
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Invalid request! Missing ID or type.");
}

$id   = intval($_GET['id']);
$type = $_GET['type'];

// Expense type to table map
$tables = [
    'Fuel'        => 'fuel_expense',
    'Food'        => 'food_expense',
    'Room'        => 'room_expense',
    'Other'       => 'other_expense',
    'Tools'       => 'tools_expense',
    'Labour'      => 'labour_expense',
    'Accessories' => 'accessories_expense',
    'tv'          => 'tv_expense'
];

if (!array_key_exists($type, $tables)) {
    die("Invalid expense type!");
}
$table = $tables[$type];

// Flags
$is_tools  = ($table === 'tools_expense');
$is_labour = ($table === 'labour_expense');

// Fetch expense
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Expense not found!");
}
$expense = $result->fetch_assoc();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date        = $_POST['date'] ?? '';
    $division    = $_POST['division'] ?? '';
    $company     = $_POST['company'] ?? '';
    $location    = $_POST['location'] ?? '';
    $store       = $_POST['store'] ?? '';
    $description = $_POST['description'] ?? '';
    $amount      = $_POST['amount'] ?? '';

    // Disable condition: Tools OR (not labour and division is Recharge/Other)
    $disable_fields = $is_tools || (!$is_labour && in_array($division, ['Recharge', 'Other']));

    if (empty($date) || empty($amount) || !is_numeric($amount)) {
        $error = "Please fill all required fields correctly.";
    } elseif (!$disable_fields && (empty($division) || empty($company) || empty($location) || empty($store) || empty($description))) {
        $error = "Please fill all required fields correctly.";
    } else {
        if ($disable_fields) {
            $update = $conn->prepare("UPDATE $table 
                SET date=?, description=?, amount=? 
                WHERE id=?");
            $update->bind_param("ssdi", $date, $description, $amount, $id);
        } else {
            $update = $conn->prepare("UPDATE $table 
                SET date=?, division=?, company=?, location=?, store=?, description=?, amount=? 
                WHERE id=?");
            $update->bind_param("ssssssdi", $date, $division, $company, $location, $store, $description, $amount, $id);
        }

        if (empty($error) && $update->execute()) {
            $username = $expense['username'];
            header("Location: user_report.php?username=" . urlencode($username));
            exit();
        } elseif (!empty($error)) {
            // validation error, just show form again
        } else {
            $error = "Update failed: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Expense - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit <?= htmlspecialchars($type) ?> Expense</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" 
                   value="<?= htmlspecialchars($expense['date']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Division</label>
            <select id="division" name="division" class="form-select" <?= $is_tools ? 'disabled' : 'required' ?>>
                <option value="">-- Select Division --</option>
                <option value="Sales" <?= $expense['division']=='Sales'?'selected':'' ?>>Sales</option>
                <option value="Project" <?= $expense['division']=='Project'?'selected':'' ?>>Project</option>
                <option value="Service" <?= $expense['division']=='Service'?'selected':'' ?>>Service</option>
                <option value="Installation" <?= $expense['division']=='Installation'?'selected':'' ?>>Installation</option>
                <option value="Recharge" <?= $expense['division']=='Recharge'?'selected':'' ?>>Recharge</option>
                <option value="Other" <?= $expense['division']=='Other'?'selected':'' ?>>Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Company</label>
            <select id="company" name="company" class="form-select" 
                <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge','Other']))) ? 'disabled' : 'required' ?>>
                <option value="">-- Select Company --</option>
                <option value="Redtag" <?= $expense['company']=='Redtag'?'selected':'' ?>>Redtag</option>
                <option value="Landmark" <?= $expense['company']=='Landmark'?'selected':'' ?>>Landmark</option>
                <option value="Apparel" <?= $expense['company']=='Apparel'?'selected':'' ?>>Apparel</option>
                <option value="Other" <?= $expense['company']=='Other'?'selected':'' ?>>Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" id="location" name="location" class="form-control"
                   value="<?= htmlspecialchars($expense['location']); ?>"
                   <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge','Other']))) ? 'disabled' : 'required' ?>>
        </div>

        <div class="mb-3">
            <label class="form-label">Store</label>
            <input type="text" id="store" name="store" class="form-control"
                   value="<?= htmlspecialchars($expense['store']); ?>"
                   <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge','Other']))) ? 'disabled' : 'required' ?>>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($expense['description']); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control"
                   value="<?= htmlspecialchars($expense['amount']); ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Update Expense</button>
        <a href="user_report.php?username=<?= urlencode($expense['username']); ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const divisionSelect = document.getElementById("division");
    const companyField = document.getElementById("company");
    const storeField = document.getElementById("store");
    const locationField = document.getElementById("location");

    const isTools = <?= $is_tools ? 'true' : 'false' ?>;
    const isLabour = <?= $is_labour ? 'true' : 'false' ?>;

    function toggleFields() {
        if (isTools || (!isLabour && (divisionSelect.value === 'Recharge' || divisionSelect.value === 'Other'))) {
            companyField.disabled = true;
            storeField.disabled = true;
            locationField.disabled = true;
        } else {
            companyField.disabled = false;
            storeField.disabled = false;
            locationField.disabled = false;
        }
    }

    if (divisionSelect) {
        divisionSelect.addEventListener("change", toggleFields);
        toggleFields();
    }
});
</script>
</body>
</html>
