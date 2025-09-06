<?php 
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}
include 'config.php';

$username = $_SESSION['username'];

// Fetch advance details
$sql = "SELECT id, date, adv_amt, cash_bank, created_at 
        FROM adv_amt 
        WHERE username=? 
        ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total advance amount
$sql_total = "SELECT SUM(adv_amt) AS total_adv FROM adv_amt WHERE username=?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("s", $username);
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
<link rel="icon" type="image/png" href="assets\vision.ico">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-4">💰 Advance Report</h2>

    <!-- Display Total Advance -->
    <div class="mb-3">
        <h5>Total Advance: <strong>SAR <?php echo number_format($total_adv, 2); ?></strong></h5>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Amount (SAR)</th>
                <th>Cash/Bank</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo number_format($row['adv_amt'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['cash_bank']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <a href="dashboard_user.php" class="btn btn-secondary">⬅ Back to Dashboard</a>
</div>

</body>
</html>
