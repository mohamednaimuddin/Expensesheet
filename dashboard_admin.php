<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
include 'config.php';
// --- Count total pending bills ---
$tables = ['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense', 'vehicle_expense'];
$pending_bill_count = 0;

foreach($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')");
    if($res) {
        $pending_bill_count += $res->fetch_assoc()['cnt'];
    }
}
// Get logged-in admin's company
$admin_company_result = $conn->query("SELECT company_id FROM users WHERE username='$username' LIMIT 1");
$admin_company_id = '';
if($admin_company_result && $admin_company_result->num_rows > 0){
    $admin_company_id = $admin_company_result->fetch_assoc()['company_id'];
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
// Get logged-in admin's company_id
$admin_company_result = $conn->query("SELECT company_id FROM users WHERE username='$username' LIMIT 1");
$admin_company_id = '';
if($admin_company_result && $admin_company_result->num_rows > 0){
    $admin_company_id = $admin_company_result->fetch_assoc()['company_id'];
}

// Fetch all non-admin users of the same company
$users_result = $conn->query("SELECT username, full_name, role, company_id 
                             FROM users 
                             WHERE role='user' AND company_id='$admin_company_id' 
                             ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<link rel="stylesheet" href="assets/dashboard_admin.css">
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    
    <!-- Mobile hamburger button -->
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
    </button>
    
    <!-- Collapsible navigation content -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto d-flex align-items-center">
        <span class="navbar-text text-white me-3">👤 <?php echo ucfirst($username); ?></span>
        <a href="add_user.php" class="btn btn-add-user me-2">➕ Add User</a>
        <a href="logout.php" class="btn btn-logout">Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="container my-3">
    <div class="hero text-center mb-4">
        <h1>Admin Dashboard</h1>
        <p class="text-white-50">Comprehensive management and oversight for Vision Angles Security.</p>
    </div>

    <h2 class="section-title mb-3">Summary</h2>
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="card card-summary shadow-lg h-100 summary-users">
                <div class="card-body text-center">
                    <h5 class="card-title">👥 Total Users</h5>
                    <p class="card-text amount-lg"><?php echo $total_users; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="card card-summary shadow-lg h-100 summary-admins">
                <div class="card-body text-center">
                    <h5 class="card-title">🛡️ Total Admins</h5>
                    <p class="card-text amount-lg"><?php echo $total_admins; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="card card-summary shadow-lg h-100 summary-pending" 
                 onclick="window.location.href='pending_bills.php'" style="cursor:pointer;">
                <div class="card-body text-center">
                    <h5 class="card-title">🕒 Pending Bills</h5>
                    <p class="card-text amount-lg"><?= $pending_bill_count ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
            <div class="card card-utility h-100 shadow-sm" 
                 onclick="window.location.href='tv_report.php'" style="cursor:pointer;">
                <div class="card-body text-center">
                    <h5 class="card-title">📺 TV Report</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
            <div class="card card-utility h-100 shadow-sm" 
                 onclick="window.location.href='vehicle.php'" style="cursor:pointer;">
                <div class="card-body text-center">
                    <h5 class="card-title">🚗 Vehicle</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12 col-sm-12 col-12 mb-3">
            <div class="card card-utility h-100 shadow-sm utility-all-expenses" 
                 onclick="window.location.href='all_user_expenses.php'" 
                 style="cursor:pointer;">
                <div class="card-body text-center">
                    <h5 class="card-title">💰 All User Expenses</h5>
                    <p class="card-text-sm">View expenses of all users combined.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="section-title mb-3">Users of Company ID: <?php echo $admin_company_id; ?></h2>
    <div class="row g-3">
        <?php if($users_result && $users_result->num_rows > 0): ?>
            <?php while($user = $users_result->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                    <div class="card card-user h-100 shadow-sm" onclick="window.location.href='user_report.php?username=<?php echo $user['username']; ?>'" style="cursor:pointer;">
                        <div class="card-body text-center">
                            <h5 class="card-title user-name"><?php echo ucfirst($user['full_name']); ?></h5>
                            <p class="card-text user-username">@<?php echo $user['username']; ?></p>
                            <p class="card-text user-company">Company ID: <?php echo $user['company_id']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center">No users found for this company.</p>
            </div>
        <?php endif; ?>
    </div>
</div>



<footer class="text-center py-4 mt-5">
    <p class="mb-0">© 2025 VisionAngles | Admin Dashboard</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>