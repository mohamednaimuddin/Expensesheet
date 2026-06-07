<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
include 'config.php';
// --- Count total pending bills ---
$tables = ['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense', 'vehicle_expense', 'taxi_expense'];
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

// Fetch company name from companies table
$admin_company_name = '';
if($admin_company_id !== ''){
    $cname_res = $conn->query("SELECT company_name FROM companies WHERE id='" . $conn->real_escape_string($admin_company_id) . "' LIMIT 1");
    if($cname_res && $cname_res->num_rows > 0){
        $admin_company_name = $cname_res->fetch_assoc()['company_name'];
    }
}

// Ensure is_active column exists (auto-migration so disabled users feature works)
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

// Fetch all non-admin ACTIVE users of the same company (disabled users are kept in DB but hidden)
$users_result = $conn->query("SELECT username, full_name, role, company_id 
                             FROM users 
                             WHERE role='user' AND company_id='$admin_company_id' AND is_active=1
                             ORDER BY full_name ASC");

// Helper: get advance, expense, balance for a user for a given month (YYYY-MM)
function get_user_month_summary($conn, $username, $month) {
    // Advance total
    $advance = 0.0;
    $stmt = $conn->prepare("SELECT IFNULL(SUM(adv_amt),0) AS total FROM adv_amt WHERE username=? AND DATE_FORMAT(date,'%Y-%m')=?");
    $stmt->bind_param("ss", $username, $month);
    $stmt->execute();
    $advance = floatval($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Carry down (previous balance brought forward)
    $carry = 0.0;
    $stmt = $conn->prepare("SELECT amount FROM carry_down WHERE username=? AND DATE_FORMAT(created_at,'%Y-%m')=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $username, $month);
    $stmt->execute();
    $cr = $stmt->get_result()->fetch_assoc();
    $carry = floatval($cr['amount'] ?? 0);

    // Expenses across all tables
    $expense_tables = [
        'fuel_expense','food_expense','room_expense','other_expense',
        'tools_expense','labour_expense','accessories_expense','tv_expense',
        'vehicle_expense','taxi_expense'
    ];
    $expense = 0.0;
    foreach ($expense_tables as $table) {
        $stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) AS amt FROM $table WHERE username=? AND submitted=1 AND DATE_FORMAT(date,'%Y-%m')=?");
        $stmt->bind_param("ss", $username, $month);
        $stmt->execute();
        $expense += floatval($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
    }

    $balance = ($advance + $carry) - $expense;
    return [
        'advance' => $advance,
        'carry'   => $carry,
        'expense' => $expense,
        'balance' => $balance
    ];
}

$current_month = date('Y-m');
$current_month_label = date('M Y');
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
<link rel="stylesheet" href="assets/loader.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Animated background blobs -->
<div class="bg-blobs" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
</div>

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

<nav class="navbar navbar-expand-lg glass-nav">
  <div class="container-fluid app-container">
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
        <span class="navbar-text user-pill me-3"><i class="bi bi-person-circle"></i> <?php echo ucfirst($username); ?></span>
        <a href="manage_users.php" class="btn btn-add-user me-2"><i class="bi bi-people-fill"></i> Manage Users</a>
        <a href="logout.php" class="btn btn-logout" onclick="showLogoutPopup(event)"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid app-container my-4">
    <div class="hero glass-card text-center mb-4">
        <div class="hero-inner">
            <span class="hero-badge"><i class="bi bi-shield-lock-fill"></i> Admin Console</span>
            <h1>Welcome back, <?php echo ucfirst($username); ?> 👋</h1>
            <p class="hero-sub">Comprehensive management and oversight for Vision Angles Security.</p>
        </div>
    </div>

    <h2 class="section-title mb-3"><span class="title-bar"></span> Summary</h2>
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="glass-card card-summary h-100 summary-users">
                <div class="card-body">
                    <div class="summary-icon"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text amount-lg"><?php echo $total_users; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="glass-card card-summary h-100 summary-admins">
                <div class="card-body">
                    <div class="summary-icon"><i class="bi bi-shield-fill-check"></i></div>
                    <div>
                        <h5 class="card-title">Total Admins</h5>
                        <p class="card-text amount-lg"><?php echo $total_admins; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="glass-card card-summary h-100 summary-pending"
                 onclick="window.location.href='pending_bills.php'" style="cursor:pointer;">
                <div class="card-body">
                    <div class="summary-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <h5 class="card-title">Pending Bills</h5>
                        <p class="card-text amount-lg"><?= $pending_bill_count ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
            <div class="glass-card card-utility h-100"
                 onclick="window.location.href='tv_report.php'" style="cursor:pointer;">
                <div class="card-body text-center">
                    <i class="bi bi-tv utility-icon"></i>
                    <h5 class="card-title">TV Report</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
            <div class="glass-card card-utility h-100"
                 onclick="window.location.href='vehicle.php'" style="cursor:pointer;">
                <div class="card-body text-center">
                    <i class="bi bi-truck utility-icon"></i>
                    <h5 class="card-title">Vehicle</h5>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
    <div class="glass-card card-utility h-100 position-relative"
         style="pointer-events:none; opacity:0.7;">

        <div class="position-absolute top-50 start-50 translate-middle">
            <span class="badge bg-danger px-3 py-2 fs-6">
                Coming Soon
            </span>
        </div>

        <div class="card-body text-center">
            <i class="bi bi-bar-chart-line utility-icon"></i>
            <h5 class="card-title">Analytics</h5>
            <p class="card-text-sm">Review expense trends and category totals.</p>
        </div>
    </div>
</div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
            <div class="glass-card card-utility h-100 utility-all-expenses"
                 onclick="window.location.href='all_user_expenses.php'"
                 style="cursor:pointer;">
                <div class="card-body text-center">
                    <i class="bi bi-cash-coin utility-icon"></i>
                    <h5 class="card-title">All User Expenses</h5>
                    <p class="card-text-sm">View expenses of all users combined.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="section-title mb-3">
        <span class="title-bar"></span> Users of Company: <?php echo htmlspecialchars($admin_company_name ?: $admin_company_id); ?>
        <span class="month-chip"><i class="bi bi-calendar3"></i> <?php echo $current_month_label; ?></span>
    </h2>
    <div class="row g-3">
        <?php if($users_result && $users_result->num_rows > 0): ?>
            <?php while($user = $users_result->fetch_assoc()): ?>
                <?php
                    $sum = get_user_month_summary($conn, $user['username'], $current_month);
                    // Build initials for avatar
                    $parts = preg_split('/\s+/', trim($user['full_name']));
                    $initials = '';
                    foreach ($parts as $p) {
                        if ($p !== '') $initials .= strtoupper($p[0]);
                        if (strlen($initials) >= 2) break;
                    }
                ?>
                <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                    <div class="glass-card card-user h-100" onclick="window.location.href='user_report.php?username=<?php echo $user['username']; ?>'" style="cursor:pointer;">
                        <div class="card-body">
                            <div class="user-head">
                                <div class="avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                                <div class="user-meta">
                                    <h5 class="user-name"><?php echo ucfirst($user['full_name']); ?></h5>
                                    <p class="user-username">@<?php echo $user['username']; ?></p>
                                </div>
                            </div>
                            <div class="user-stats">
                                <div class="stat-row">
                                    <span class="stat-label"><i class="bi bi-wallet2"></i> Advance</span>
                                    <span class="stat-value advance">SAR <?php echo number_format($sum['advance'], 2); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><i class="bi bi-credit-card-2-front"></i> Spending</span>
                                    <span class="stat-value spending">SAR <?php echo number_format($sum['expense'], 2); ?></span>
                                </div>
                                <div class="stat-row balance-row">
                                    <span class="stat-label"><i class="bi bi-bank"></i> Balance</span>
                                    <span class="stat-value <?php echo $sum['balance'] >= 0 ? 'balance-pos' : 'balance-neg'; ?>">SAR <?php echo number_format($sum['balance'], 2); ?></span>
                                </div>
                            </div>
                            <div class="card-footer-link">View Report <i class="bi bi-arrow-right-short"></i></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center text-muted">No users found for this company.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Logout Confirmation Popup -->
<div id="logoutPopup" class="popup-overlay">
    <div class="popup-box">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>

        <div class="popup-buttons">
            <button class="cancel-btn" onclick="closeLogoutPopup()">Cancel</button>
            <a href="logout.php" class="confirm-btn">Yes, Logout</a>
        </div>
    </div>
</div>



<footer class="text-center py-4 mt-5 glass-footer">
    <p class="mb-0">© 2026 VisionAngles | Admin Dashboard</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function getPageLoader() {
    return document.getElementById('pageLoader');
}

function hidePageLoader() {
    const loader = getPageLoader();
    if (loader) loader.classList.add('hidden');
}

function showPageLoader() {
    const loader = getPageLoader();
    if (loader) loader.classList.remove('hidden');
}

// Keep the admin dashboard as the browser-back landing page.
// This prevents older pages like analytics.php or index.php from appearing
// when Back is pressed repeatedly after returning to the dashboard.
(function keepBackOnDashboard() {
    if (!window.history || !window.history.replaceState || !window.history.pushState) {
        return;
    }

    const dashboardUrl = window.location.href;
    window.history.replaceState({ page: 'dashboard_admin' }, '', dashboardUrl);
    window.history.pushState({ page: 'dashboard_admin' }, '', dashboardUrl);

    window.addEventListener('popstate', function() {
        hidePageLoader();
        window.history.pushState({ page: 'dashboard_admin' }, '', dashboardUrl);
    });
})();

// Hide loader when page is loaded
window.addEventListener('load', function() {
    hidePageLoader();
});

// Hide loader when navigating back via browser history (bfcache restore)
window.addEventListener('pageshow', function(event) {
    hidePageLoader();
});

// Show loader on navigation
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll(
    'a[href]:not([href^="#"]):not([href^="javascript"]):not(.btn-logout), .card-user'
).forEach(function(el) {
        el.addEventListener('click', function(e) {
            // Skip loader for new tab / modifier-key clicks
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
            var target = el.getAttribute('target');
            if (target && target === '_blank') return;
            showPageLoader();
        });
    });
});

function showLogoutPopup(event) {
    event.preventDefault();
    document.getElementById("logoutPopup").style.display = "flex";
}

function closeLogoutPopup() {
    document.getElementById("logoutPopup").style.display = "none";
}
</script>
</body>
</html>
