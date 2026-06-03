<?php 
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'log_helper.php';

// Validate request
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Invalid request! Missing ID or type.");
}

$id   = intval($_GET['id']);
$type = strtolower($_GET['type']); // convert type to lowercase

// Expense type to table map
$tables = [
    'fuel'        => 'fuel_expense',
    'food'        => 'food_expense',
    'room'        => 'room_expense',
    'other'       => 'other_expense',
    'tools'       => 'tools_expense',
    'labour'      => 'labour_expense',
    'accessories' => 'accessories_expense',
    'tv'          => 'tv_expense',
    'vehicle'     => 'vehicle_expense'
];

if (!array_key_exists($type, $tables)) {
    die("Invalid expense type!");
}

$table = $tables[$type];

// Flags
$is_tools   = ($table === 'tools_expense');
$is_labour  = ($table === 'labour_expense');
$is_vehicle = ($table === 'vehicle_expense');

// Fetch expense
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Expense not found!");
}
$expense = $result->fetch_assoc();

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $description = $_POST['description'] ?? '';
    $amount      = $_POST['amount'] ?? '';
    $service     = $_POST['service'] ?? '';
    $division    = $_POST['division'] ?? '';
    $company     = $_POST['company'] ?? '';
    $location    = $_POST['location'] ?? '';
    $store       = $_POST['store'] ?? '';
    $date        = $_POST['date'] ?? '';
    $bill        = $_POST['bill'] ?? '';

    if (empty($bill)) {
        $error = "Please select Bill option.";
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit();
        }
    } else if ($is_vehicle) {
        // Vehicle: only service, amount, and bill editable
        if (empty($service) || !is_numeric($amount)) {
            $error = "Please fill all required fields correctly.";
            if ($is_ajax) {
                echo json_encode(['success' => false, 'error' => $error]);
                exit();
            }
        } else {
            $update = $conn->prepare("UPDATE vehicle_expense SET service=?, amount=?, bill=? WHERE id=?");
            $update->bind_param("sdsi", $service, $amount, $bill, $id);
            if ($update->execute()) {
                logActivity($conn, LOG_EDIT_EXPENSE, "Edited vehicle expense ID: $id, Amount: $amount SAR, Bill: $bill");
                if ($is_ajax) {
                    echo json_encode(['success' => true, 'redirect' => 'user_report.php?username=' . urlencode($expense['username'])]);
                    exit();
                }
                header("Location: user_report.php?username=" . urlencode($expense['username']));
                exit();
            } else {
                $error = "Update failed: " . $conn->error;
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit();
                }
            }
        }
    } else {
        // Other expenses
        $disable_fields = $is_tools || (!$is_labour && in_array($division, ['Recharge', 'Other']));
        if (empty($date) || empty($amount) || !is_numeric($amount)) {
            $error = "Please fill all required fields correctly.";
            if ($is_ajax) {
                echo json_encode(['success' => false, 'error' => $error]);
                exit();
            }
        } elseif (!$disable_fields && (empty($division) || empty($company) || empty($location) || empty($store) || empty($description))) {
            $error = "Please fill all required fields correctly.";
            if ($is_ajax) {
                echo json_encode(['success' => false, 'error' => $error]);
                exit();
            }
        } else {
            if ($disable_fields) {
                $update = $conn->prepare("UPDATE $table SET date=?, description=?, amount=?, bill=? WHERE id=?");
                $update->bind_param("ssdsi", $date, $description, $amount, $bill, $id);
            } else {
                $update = $conn->prepare("UPDATE $table SET date=?, division=?, company=?, location=?, store=?, description=?, amount=?, bill=? WHERE id=?");
                $update->bind_param("ssssssdsi", $date, $division, $company, $location, $store, $description, $amount, $bill, $id);
            }
            if ($update->execute()) {
                logActivity($conn, LOG_EDIT_EXPENSE, "Edited $type expense ID: $id for {$expense['username']}, Amount: $amount SAR, Bill: $bill");
                if ($is_ajax) {
                    echo json_encode(['success' => true, 'redirect' => 'user_report.php?username=' . urlencode($expense['username'])]);
                    exit();
                }
                header("Location: user_report.php?username=" . urlencode($expense['username']));
                exit();
            } else {
                $error = "Update failed: " . $conn->error;
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit();
                }
            }
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
    <h2>Edit <?= htmlspecialchars(ucfirst($type)) ?> Expense</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

        <?php if($is_vehicle): ?>
            <!-- Vehicle: only service, amount, and bill editable -->
            <div class="mb-3">
                <label>Service:</label>
                <select name="service" class="form-select" required>
                    <option value="">-- Select Service --</option>
                    <option <?= $expense['service']=='Engine Oil'?'selected':'' ?>>Engine Oil</option>
                    <option <?= $expense['service']=='Gear Oil'?'selected':'' ?>>Gear Oil</option>
                    <option <?= $expense['service']=='Tyre'?'selected':'' ?>>Tyre</option>
                    <option <?= $expense['service']=='Brake Pad'?'selected':'' ?>>Brake Pad</option>
                    <option <?= $expense['service']=='Brake Oil'?'selected':'' ?>>Brake Oil</option>
                    <option <?= $expense['service']=='Fuel Injection'?'selected':'' ?>>Fuel Injection</option>
                    <option <?= $expense['service']=='Other'?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($expense['amount']) ?>" required>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($expense['date']); ?>" required>
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
                    <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge']))) ? 'disabled' : 'required' ?>>
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
                       <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge']))) ? 'disabled' : 'required' ?>>
            </div>

            <div class="mb-3">
                <label class="form-label">Store</label>
                <input type="text" id="store" name="store" class="form-control"
                       value="<?= htmlspecialchars($expense['store']); ?>"
                       <?= ($is_tools || (!$is_labour && in_array($expense['division'], ['Recharge']))) ? 'disabled' : 'required' ?>>
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
        <?php endif; ?>

        <!-- Bill Field -->
        <div class="mb-3">
            <label class="form-label">Bill</label>
            <select name="bill" class="form-select" required>
                <option value="">-- Select Bill --</option>
                <option value="Yes" <?= isset($expense['bill']) && $expense['bill'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                <option value="No" <?= isset($expense['bill']) && $expense['bill'] === 'No' ? 'selected' : '' ?>>No</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" id="updateBtn">Update Expense</button>
        <a href="user_report.php?username=<?= urlencode($expense['username']); ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
// AJAX form submission to prevent page refresh
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const updateBtn = document.getElementById("updateBtn");
    
    form.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        updateBtn.disabled = true;
        updateBtn.innerHTML = 'Updating...';
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Use replace to navigate without adding to history (prevents back button issues)
                window.location.replace(data.redirect);
            } else {
                alert(data.error || 'An error occurred');
                updateBtn.disabled = false;
                updateBtn.innerHTML = 'Update Expense';
            }
        })
        .catch(error => {
            // If JSON parse fails, it might be a validation error - submit normally
            form.submit();
        });
    });

<?php if(!$is_vehicle): ?>
    const divisionSelect = document.getElementById("division");
    const companyField = document.getElementById("company");
    const storeField = document.getElementById("store");
    const locationField = document.getElementById("location");

    const isTools = <?= $is_tools ? 'true' : 'false' ?>;
    const isLabour = <?= $is_labour ? 'true' : 'false' ?>;

    function toggleFields() {
        if (isTools || (!isLabour && (divisionSelect.value === 'Recharge'))) {
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
<?php endif; ?>
});
</script>
</body>
</html>
