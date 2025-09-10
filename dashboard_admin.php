<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
include 'config.php';
// --- Count total pending bills ---
$tables = ['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense'];
$pending_bill_count = 0;

foreach($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')");
    if($res) {
        $pending_bill_count += $res->fetch_assoc()['cnt'];
    }
}


// Fetch summary data
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='user'")->fetch_assoc()['total'];
$total_admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin'")->fetch_assoc()['total'];
$total_fuel = $conn->query("SELECT COUNT(*) as total FROM fuel_expense")->fetch_assoc()['total'];
$result = $conn->query("
    SELECT SUM(total) as total FROM (
        SELECT COUNT(*) as total FROM fuel_expense WHERE bill='no'
        UNION ALL
        SELECT COUNT(*) as total FROM room_expense WHERE bill='no'
        UNION ALL
        SELECT COUNT(*) as total FROM other_expense WHERE bill='no'
        UNION ALL
        SELECT COUNT(*) as total FROM accessories_expense WHERE bill='no'
    ) as combined
")->fetch_assoc();

$pending_bills = $result['total'];

// Fetch all non-admin users
$users_result = $conn->query("SELECT username, full_name, role FROM users WHERE role='user' ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<style>
body {
    background-color: #f5f6fa;
    font-family: 'Arial', sans-serif;
    position: relative;
    min-height: 100vh;
}

/* Background logo */
body::before {
    content: "";
    position: fixed;
    top: 50%;
    left: 50%;
    width: 500px;   /* adjust size */
    height: 500px;
    background: url('assets/vision1.png') no-repeat center center;
    background-size: contain;
    opacity: 0.3;   /* faint effect */
    transform: translate(-50%, -50%);
    z-index: 0;      /* behind everything */
}

/* Put content above the background */
.container, .navbar, footer {
    position: relative;
    z-index: 1;
}
/* Navbar */
.navbar {
    background: linear-gradient(90deg, #4f46e5, #ec4899); /* Blue to pink gradient */
}
.navbar-brand span {
    color: #fff;
    font-weight: bold;
    font-size: 1.2rem;
}

/* Buttons */
.btn-success {
    background-color: #10b981;
    border-color: #10b981;
}
.btn-success:hover {
    background-color: #059669;
    border-color: #059669;
}
.btn-danger {
    background-color: #ef4444;
    border-color: #ef4444;
}
.btn-danger:hover {
    background-color: #b91c1c;
    border-color: #b91c1c;
}

/* Summary Cards */
.card-summary {
    background: linear-gradient(135deg, #4f46e5, #ec4899);
    color: white;
    transition: transform 0.3s;
}
.card-summary:hover {
    transform: scale(1.05);
}

/* User Cards */
.card-user {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card-user:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* Footer */
footer {
    background-color: #ecf0f1;
    color: #333;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    <div class="d-flex ms-auto align-items-center">
      <span class="text-white me-3">👤 <?php echo ucfirst($username); ?></span>
      <a href="add_user.php" class="btn btn-success me-2">➕ Add User</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>
</nav>

<div class="container my-4">
    <div class="text-center mb-4">
        <h1>Admin</h1>
        <p class="text-muted">Manage users and admins from here.</p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card card-summary text-center shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">👥 Total Users</h5>
                    <p class="card-text"><?php echo $total_users; ?> users registered.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-summary text-center shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">🛡️ Total Admins</h5>
                    <p class="card-text"><?php echo $total_admins; ?> admins registered.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary text-center shadow-sm h-100" 
                 onclick="window.location.href='tv_report.php'" style="cursor:pointer;">
                <div class="card-body">
                    <h5 class="card-title">📺 TV Report</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-summary text-center shadow-sm h-100" onclick="window.location.href='pending_bills.php'" style="cursor:pointer;">
                <div class="card-body">
                    <h5 class="card-title">🕒 Pending Bills</h5>
                    <p class="card-text"><?= $pending_bill_count ?> pending without a bill.
                </p>
            </div>
        </div>
    </div>
</div>

    <!-- Users Cards -->
    <h2 class="mb-3">All Users</h2>
    <div class="row g-3">
        <?php if($users_result && $users_result->num_rows > 0): ?>
            <?php while($user = $users_result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card card-user h-100 shadow-sm" onclick="window.location.href='user_report.php?username=<?php echo $user['username']; ?>'" style="cursor:pointer;">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo ucfirst($user['full_name']); ?></h5>
                            <p class="card-text">Username: <?php echo $user['username']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
        <div class="col-md-4">
    <div class="card card-user h-100 shadow-sm bg-warning text-dark" 
         onclick="window.location.href='all_user_expenses.php'" 
         style="cursor:pointer;">
        <div class="card-body text-center">
            <h5 class="card-title">💰 All User Expenses</h5>
            <p class="card-text">View expenses of all users combined.</p>
        </div>
    </div>
</div>

    </div>
</div>



<footer class="text-center py-3 mt-5">
    <p class="mb-0">© 2025 VisionAngles | Admin Dashboard</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
