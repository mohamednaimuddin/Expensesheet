<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Validate TV expense ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("TV expense ID is required.");
}

$id = intval($_GET['id']);

// Fetch existing TV expense
$stmt = $conn->prepare("SELECT * FROM tv_expense WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("TV expense not found.");
}
$tv = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $division = $_POST['division'];
    $company = $_POST['company'];
    $location = $_POST['location'];
    $store = $_POST['store'];
    $tv_type = $_POST['tv_type'];
    $description = $_POST['description'];
    $updated_description = $_POST['updated_description'] ?? null;
    $amount = $_POST['amount'];
    $region = $_POST['region'];

    $update = $conn->prepare("UPDATE tv_expense SET date=?, division=?, company=?, location=?, store=?, tv_type=?, description=?, updated_description=?, amount=?, region=? WHERE id=?");
    $update->bind_param("ssssssssdsi", $date, $division, $company, $location, $store, $tv_type, $description, $updated_description, $amount, $region, $id);
    
    if ($update->execute()) {
        header("Location: tv_report.php?success=1");
        exit();
    } else {
        $error = "Failed to update: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit TV Expense | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<script>
function toggleUpdatedDescription() {
    var tvType = document.getElementById('tv_type').value;
    var updatedDescDiv = document.getElementById('updated_description_div');
    if(tvType === 'Repaired') {
        updatedDescDiv.style.display = 'none';
    } else {
        updatedDescDiv.style.display = 'block';
    }
}
</script>
</head>
<body>
<div class="container my-4">
    <h2>Edit TV Expense</h2>
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="row g-3">
            <div class="col-md-3">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($tv['date']) ?>" required>
            </div>
            <div class="col-md-3">
                <label>Division</label>
                <select name="division" class="form-select" required>
                    <?php
                    $divisions = ['Sales', 'Project', 'Service', 'Installation'];
                    foreach ($divisions as $division_option) {
                        $selected = ($tv['division'] == $division_option) ? 'selected' : '';
                        echo "<option value=\"$division_option\" $selected>$division_option</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Company</label>
                <select name="company" class="form-select" required>
                    <?php
                    $companies = ['Redtag', 'Landmark', 'Apparel', 'Other'];
                    foreach ($companies as $company_option) {
                        $selected = ($tv['company'] == $company_option) ? 'selected' : '';
                        echo "<option value=\"$company_option\" $selected>$company_option</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Location</label>
                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($tv['location']) ?>" required>
            </div>
            <div class="col-md-3">
                <label>Store</label>
                <input type="text" name="store" class="form-control" value="<?= htmlspecialchars($tv['store']) ?>" required>
            </div>
            <div class="col-md-3">
                <label>TV Type</label>
                <select name="tv_type" id="tv_type" class="form-select" onchange="toggleUpdatedDescription()" required>
                    <?php
                    $types = ['New','Repaired'];
                    foreach($types as $type){
                        $selected = ($tv['tv_type']==$type) ? 'selected' : '';
                        echo "<option value=\"$type\" $selected>$type</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Description</label>
                <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($tv['description']) ?>">
            </div>
            <div class="col-md-3" id="updated_description_div" style="display: <?= ($tv['tv_type']=='Repaired') ? 'none' : 'block' ?>;">
                <label>Old/Updated Description</label>
                <input type="text" name="updated_description" class="form-control" value="<?= htmlspecialchars($tv['updated_description']) ?>">
            </div>
            <div class="col-md-3">
                <label>Amount (SAR)</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($tv['amount']) ?>" required>
            </div>
            <div class="col-md-3">
                <label>Region</label>
                <select name="region" class="form-select">
                    <?php
                    $regions = ['Dammam','Riyadh','Jeddah','Other'];
                    foreach($regions as $region){
                        $selected = ($tv['region']==$region) ? 'selected' : '';
                        echo "<option value=\"$region\" $selected>$region</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary">Update</button>
            <a href="tv_report.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
window.onload = toggleUpdatedDescription;
</script>
</body>
</html>
