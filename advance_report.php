<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get filter inputs
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$username_filter = isset($_GET['username']) ? $_GET['username'] : 'All';

// Fetch users for dropdown
$users_result = $conn->query("SELECT username FROM users ORDER BY username ASC");

// Build SQL query
$sql = "SELECT * FROM advance_amt WHERE 1=1";
$params = [];
$types = "";

if ($from_date && $to_date) {
    $sql .= " AND date BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if ($username_filter !== 'All') {
    $sql .= " AND username = ?";
    $params[] = $username_filter;
    $types .= "s";
}

$sql .= " ORDER BY date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Calculate total advance amount
$total_adv = 0;
while ($row = $result->fetch_assoc()) {
    $total_adv += $row['adv_amt'];
}
// Rewind result pointer for display
$result->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advance Report | VisionAngles</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="assets/user_report.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.total-spent {
    margin: 15px 0;
    padding: 10px;
    background: #e9f5ff;
    border: 1px solid #28a745;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #28a745;
    text-align: center;
}
.filter_form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
</style>
</head>
<body>

<div class="report-header">
    <img src="assets/visionlogo.jpg" alt="Company Logo">
    <h2>Advance Summary Report</h2>
</div>

<div class="filters-container">
    <form class="filter_form" method="get" action="">
        <label>From: <input type="date" name="from_date" value="<?php echo $from_date; ?>"></label>
        <label>To: <input type="date" name="to_date" value="<?php echo $to_date; ?>"></label>
        <label>Username:
            <select name="username">
                <option value="All" <?php if($username_filter=='All') echo 'selected'; ?>>All</option>
                <?php while($user = $users_result->fetch_assoc()): ?>
                    <option value="<?php echo $user['username']; ?>" <?php if($username_filter==$user['username']) echo 'selected'; ?>>
                        <?php echo $user['username']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label>
        <button type="submit">Filter</button>
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.location='advance_report.php'">Clear Filters</button>
    </form>

    <div class="total-spent">Total Advance: <?php echo number_format($total_adv,2); ?></div>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Username</th>
            <th>Advance Amount</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
        <?php if($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td><?php echo $row['adv_amt']; ?></td>
                    <td><?php echo $row['timestamp']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No advances found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
