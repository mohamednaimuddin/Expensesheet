<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
include 'config.php';

// Fetch summary data
$total_companies = $conn->query("SELECT COUNT(*) as total FROM companies")->fetch_assoc()['total'];
$total_admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='admin'")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='user'")->fetch_assoc()['total'];

// Count total pending bills across all companies
$tables = ['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense', 'vehicle_expense', 'taxi_expense'];
$pending_bill_count = 0;
foreach($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')");
    if($res) {
        $pending_bill_count += $res->fetch_assoc()['cnt'];
    }
}

// Calculate total expenses this month
$current_month = date('Y-m');
$total_expenses_month = 0;
$expense_tables = ['fuel_expense', 'food_expense', 'room_expense', 'other_expense', 'tools_expense', 'labour_expense', 'accessories_expense', 'tv_expense', 'vehicle_expense', 'taxi_expense'];
foreach ($expense_tables as $table) {
    $res = $conn->query("SELECT SUM(amount) as total FROM $table WHERE DATE_FORMAT(date, '%Y-%m') = '$current_month'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_expenses_month += floatval($row['total'] ?? 0);
    }
}

// Fetch all companies with their user counts
$companies_result = $conn->query("
    SELECT c.id, c.company_name, c.company_code, c.created_at,
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND role = 'admin') as admin_count,
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND role = 'user') as user_count
    FROM companies c
    ORDER BY c.company_name ASC
");

// Fetch recent expenses across all companies
$recent_expenses = $conn->query("
    SELECT 'Fuel' as type, username, amount, date, division FROM fuel_expense ORDER BY created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link rel="stylesheet" href="assets/dashboard_superadmin.css">
<link rel="stylesheet" href="assets/loader.css">
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-container">
        <div class="brand-loader">
            <img src="assets/visionnew.png" alt="Loading...">
            <div class="dots-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-superadmin">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
            <span>Vision Angles Security EST.</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <span class="navbar-text text-white me-3">
                    <i class="bi bi-shield-lock-fill"></i> Super Admin: <?php echo ucfirst($username); ?>
                </span>
                <a href="logout.php" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="hero text-center mb-4">
        <h1><i class="bi bi-building-gear"></i> Super Admin Dashboard</h1>
        <p class="text-white-50">Complete control over all companies, admins, and users.</p>
    </div>

    <!-- Summary Cards -->
    <h2 class="section-title mb-3"><i class="bi bi-graph-up"></i> Overview</h2>
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-summary summary-companies h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-building"></i></div>
                    <h5 class="card-title">Total Companies</h5>
                    <p class="card-text amount-lg"><?php echo $total_companies; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-summary summary-admins h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-person-badge"></i></div>
                    <h5 class="card-title">Total Admins</h5>
                    <p class="card-text amount-lg"><?php echo $total_admins; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-summary summary-users h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-people"></i></div>
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text amount-lg"><?php echo $total_users; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-summary summary-expenses h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-cash-stack"></i></div>
                    <h5 class="card-title">This Month</h5>
                    <p class="card-text amount-lg">SAR <?php echo number_format($total_expenses_month, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="section-title mb-3"><i class="bi bi-lightning"></i> Quick Actions</h2>
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-action h-100" onclick="window.location.href='manage_companies.php'">
                <div class="card-body text-center">
                    <i class="bi bi-building-add action-icon"></i>
                    <h5 class="card-title">Manage Companies</h5>
                    <p class="card-text-sm">Add, edit, or delete companies</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-action h-100" onclick="window.location.href='manage_admins.php'">
                <div class="card-body text-center">
                    <i class="bi bi-person-gear action-icon"></i>
                    <h5 class="card-title">Manage Admins</h5>
                    <p class="card-text-sm">Assign admins to companies</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-action h-100" onclick="window.location.href='manage_all_users.php'">
                <div class="card-body text-center">
                    <i class="bi bi-people-fill action-icon"></i>
                    <h5 class="card-title">Manage All Users</h5>
                    <p class="card-text-sm">View and manage all users</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-action h-100" onclick="window.location.href='pending_bills.php'">
                <div class="card-body text-center">
                    <i class="bi bi-receipt action-icon"></i>
                    <h5 class="card-title">Pending Bills</h5>
                    <p class="card-text-sm badge bg-warning"><?php echo $pending_bill_count; ?> pending</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-action h-100" onclick="window.location.href='activity_log.php'">
                <div class="card-body text-center">
                    <i class="bi bi-journal-text action-icon"></i>
                    <h5 class="card-title">Activity Log</h5>
                    <p class="card-text-sm">Track all user activities</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <h2 class="section-title mb-3"><i class="bi bi-file-earmark-bar-graph"></i> Reports & Data</h2>
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-report h-100" onclick="window.location.href='analytics.php'">
                <div class="card-body text-center">
                    <i class="bi bi-bar-chart-line report-icon"></i>
                    <h5 class="card-title">Analytics</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-report h-100" onclick="window.location.href='all_user_expenses.php'">
                <div class="card-body text-center">
                    <i class="bi bi-journal-text report-icon"></i>
                    <h5 class="card-title">All Expenses</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-report h-100" onclick="window.location.href='tv_report.php'">
                <div class="card-body text-center">
                    <i class="bi bi-tv report-icon"></i>
                    <h5 class="card-title">TV Report</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-report h-100" onclick="window.location.href='vehicle.php'">
                <div class="card-body text-center">
                    <i class="bi bi-car-front report-icon"></i>
                    <h5 class="card-title">Vehicle Report</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-report h-100" onclick="window.location.href='advance_report.php'">
                <div class="card-body text-center">
                    <i class="bi bi-cash-coin report-icon"></i>
                    <h5 class="card-title">Advance Report</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Companies List -->
    <h2 class="section-title mb-3"><i class="bi bi-buildings"></i> Companies Overview</h2>
    <div class="row g-3 mb-4">
        <?php if($companies_result && $companies_result->num_rows > 0): ?>
            <?php while($company = $companies_result->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-company h-100" onclick="window.location.href='company_details.php?id=<?php echo $company['id']; ?>'">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-building"></i> 
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </h5>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($company['company_code']); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="company-stats">
                                <div class="stat">
                                    <span class="stat-value"><?php echo $company['admin_count']; ?></span>
                                    <span class="stat-label">Admins</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value"><?php echo $company['user_count']; ?></span>
                                    <span class="stat-label">Users</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>Created: <?php echo date('M d, Y', strtotime($company['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No companies found. 
                    <a href="manage_companies.php" class="alert-link">Add your first company</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="text-center py-4 mt-5">
    <p class="mb-0">© <?php echo date('Y'); ?> VisionAngles | Super Admin Dashboard</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener('load', function() {
    document.getElementById('pageLoader').classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript"]), .card-action, .card-report, .card-company').forEach(function(el) {
        el.addEventListener('click', function(e) {
            document.getElementById('pageLoader').classList.remove('hidden');
        });
    });
});
</script>
</body>
</html>
