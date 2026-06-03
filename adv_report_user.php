<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}
include 'config.php';

$username = $_SESSION['username'];

// ✅ Set default date range (current month)
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date   = $_GET['to_date']   ?? date('Y-m-t');

// ✅ Fetch advance details with date filter
$sql = "SELECT id, date, adv_amt, cash_bank, created_at 
        FROM adv_amt 
        WHERE username=? 
        AND date BETWEEN ? AND ?
        ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Calculate total advance for the selected period
$sql_total = "SELECT SUM(adv_amt) AS total_adv 
              FROM adv_amt 
              WHERE username=? 
              AND date BETWEEN ? AND ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("sss", $username, $from_date, $to_date);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_row = $result_total->fetch_assoc();
$total_adv = $total_row['total_adv'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Advance Report | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link rel="stylesheet" href="assets/loader.css">
<style>
body {
    background: linear-gradient(120deg, #f0f4ff, #f9f9f9);
    min-height: 100vh;
}
.container {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    margin-top: 40px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
h2 {
    color: #4f46e5;
    font-weight: bold;
}
.table th {
    background-color: #4f46e5 !important;
    color: white;
}
.btn-primary {
    background: linear-gradient(90deg, #4f46e5, #ec4899);
    border: none;
}
</style>
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="brand-loader">
        <img src="assets/visionnew.png" alt="VisionAngles" class="loader-logo">
        <div class="dots-loader">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>

<div class="container">
    <h2 class="mb-4">💰 Advance Report</h2>

    <!-- 🔍 Filter Form -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
    </form>

    <!-- Display Total Advance -->
    <div class="mb-3">
        <h5>Total Advance (<?php echo date("F Y", strtotime($from_date)); ?>): 
            <strong>SAR <?php echo number_format($total_adv, 2); ?></strong>
        </h5>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Amount (SAR)</th>
                    <th>Cash/Bank</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo number_format($row['adv_amt'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['cash_bank']); ?></td>
                        </tr>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No records found for this period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard_user.php" class="btn btn-secondary mt-3">⬅ Back to Dashboard</a>
</div>

<script>
// Hide loader when page is fully loaded
window.addEventListener('load', function() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.classList.add('hidden');
        setTimeout(() => { loader.style.display = 'none'; }, 500);
    }
});
</script>
</body>
</html>
