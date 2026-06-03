<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard_superadmin.php");
    exit();
}

$company_id = intval($_GET['id']);

// Fetch company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    header("Location: dashboard_superadmin.php");
    exit();
}

// Fetch admins for this company
$admins = $conn->query("SELECT * FROM users WHERE company_id = $company_id AND role = 'admin' ORDER BY full_name ASC");

// Fetch users for this company
$users = $conn->query("SELECT * FROM users WHERE company_id = $company_id AND role = 'user' ORDER BY full_name ASC");

// Calculate expenses for this company's users
$current_month = date('Y-m');
$expense_tables = ['fuel_expense', 'food_expense', 'room_expense', 'other_expense', 'tools_expense', 'labour_expense', 'accessories_expense', 'tv_expense', 'vehicle_expense', 'taxi_expense'];

$total_expenses_month = 0;
$total_expenses_all = 0;

// Get all usernames for this company
$company_users = $conn->query("SELECT username FROM users WHERE company_id = $company_id");
$usernames = [];
while ($u = $company_users->fetch_assoc()) {
    $usernames[] = "'" . $conn->real_escape_string($u['username']) . "'";
}

if (count($usernames) > 0) {
    $usernames_str = implode(',', $usernames);
    
    foreach ($expense_tables as $table) {
        // This month
        $res = $conn->query("SELECT SUM(amount) as total FROM $table WHERE username IN ($usernames_str) AND DATE_FORMAT(date, '%Y-%m') = '$current_month'");
        if ($res) {
            $total_expenses_month += floatval($res->fetch_assoc()['total'] ?? 0);
        }
        
        // All time
        $res2 = $conn->query("SELECT SUM(amount) as total FROM $table WHERE username IN ($usernames_str)");
        if ($res2) {
            $total_expenses_all += floatval($res2->fetch_assoc()['total'] ?? 0);
        }
    }
}

// Pending bills count
$pending_count = 0;
$tables = ['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense', 'vehicle_expense', 'taxi_expense'];
if (count($usernames) > 0) {
    foreach($tables as $table) {
        $res = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE username IN ($usernames_str) AND (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0'))");
        if($res) {
            $pending_count += $res->fetch_assoc()['cnt'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($company['company_name']); ?> | Company Details</title>
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
        <a class="navbar-brand d-flex align-items-center" href="dashboard_superadmin.php">
            <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
            <span>Vision Angles Security EST.</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <a href="dashboard_superadmin.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Company Header -->
    <div class="card mb-4 company-header-card">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1">
                        <i class="bi bi-building"></i> 
                        <?php echo htmlspecialchars($company['company_name']); ?>
                    </h2>
                    <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($company['company_code']); ?></span>
                    <p class="text-muted mt-2 mb-0">
                        <?php if (!empty($company['address'])): ?>
                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($company['address']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($company['email'])): ?>
                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($company['email']); ?> &nbsp;
                        <?php endif; ?>
                        <?php if (!empty($company['phone'])): ?>
                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($company['phone']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="manage_companies.php" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Edit Company
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card card-summary summary-admins h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-person-badge"></i></div>
                    <h5 class="card-title">Admins</h5>
                    <p class="card-text amount-lg"><?php echo $admins->num_rows; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-summary summary-users h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-people"></i></div>
                    <h5 class="card-title">Users</h5>
                    <p class="card-text amount-lg"><?php echo $users->num_rows; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-summary summary-expenses h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-cash-stack"></i></div>
                    <h5 class="card-title">This Month</h5>
                    <p class="card-text amount-lg">SAR <?php echo number_format($total_expenses_month, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-summary summary-pending h-100">
                <div class="card-body text-center">
                    <div class="summary-icon"><i class="bi bi-receipt"></i></div>
                    <h5 class="card-title">Pending Bills</h5>
                    <p class="card-text amount-lg"><?php echo $pending_count; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Admins Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-badge"></i> Company Admins</h5>
            <a href="manage_admins.php?company=<?php echo $company_id; ?>" class="btn btn-light btn-sm">
                <i class="bi bi-plus-lg"></i> Add Admin
            </a>
        </div>
        <div class="card-body">
            <?php if ($admins && $admins->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></td>
                                <td>@<?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['number'] ?? '-'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted mb-0">No admins assigned to this company.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users Section -->
    <div class="card">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people"></i> Company Users</h5>
            <a href="manage_all_users.php?company=<?php echo $company_id; ?>" class="btn btn-light btn-sm">
                <i class="bi bi-plus-lg"></i> Add User
            </a>
        </div>
        <div class="card-body">
            <?php if ($users && $users->num_rows > 0): ?>
                <div class="row g-3">
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card card-user h-100" onclick="window.location.href='user_report.php?username=<?php echo urlencode($user['username']); ?>'" style="cursor:pointer;">
                            <div class="card-body text-center">
                                <div class="user-avatar mb-2">
                                    <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title user-name"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                                <p class="card-text user-username text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="card-text"><small><?php echo htmlspecialchars($user['email']); ?></small></p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted mb-0">No users assigned to this company.</p>
            <?php endif; ?>
        </div>
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

document.querySelectorAll('.card-user').forEach(function(el) {
    el.addEventListener('click', function(e) {
        document.getElementById('pageLoader').classList.remove('hidden');
    });
});
</script>
</body>
</html>
