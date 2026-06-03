<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Pagination
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filters
$filter_company = $_GET['company'] ?? 'All';
$filter_user = $_GET['username'] ?? 'All';
$filter_action = $_GET['action'] ?? 'All';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// Build query
$where = "WHERE DATE(created_at) BETWEEN ? AND ?";
$params = [$from_date, $to_date];
$types = "ss";

if ($filter_company !== 'All') {
    $where .= " AND company_id = ?";
    $params[] = $filter_company;
    $types .= "i";
}

if ($filter_user !== 'All') {
    $where .= " AND username = ?";
    $params[] = $filter_user;
    $types .= "s";
}

if ($filter_action !== 'All') {
    $where .= " AND action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_log $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get logs
$sql = "SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get companies for filter
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");

// Get unique usernames for filter
$users = $conn->query("SELECT DISTINCT username FROM activity_log ORDER BY username");

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - VisionAngles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="assets/vision.ico">
    <link rel="stylesheet" href="assets/loader.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }
        .container-fluid { padding: 20px; }
        .page-header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header img {
            height: 50px;
            border-radius: 8px;
        }
        .filters-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filters-card .form-label { color: #aaa; font-size: 12px; margin-bottom: 3px; }
        .filters-card .form-control, .filters-card .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            font-size: 13px;
        }
        .filters-card .form-control:focus, .filters-card .form-select:focus {
            background: rgba(255,255,255,0.15);
            border-color: #00d9ff;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(0,217,255,0.25);
        }
        .filters-card .form-select option { background: #1a1a2e; color: #fff; }
        .btn-filter {
            background: linear-gradient(135deg, #00d9ff, #0066ff);
            border: none;
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
        }
        .btn-filter:hover { background: linear-gradient(135deg, #0066ff, #00d9ff); color: #fff; }
        .btn-clear {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
        }
        .btn-clear:hover { background: rgba(255,255,255,0.2); color: #fff; }
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-back:hover { background: linear-gradient(135deg, #495057, #6c757d); color: #fff; }
        .log-table {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
        }
        .table { color: #fff; margin: 0; }
        .table thead th {
            background: rgba(0,217,255,0.2);
            border: none;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .table tbody td {
            border-color: rgba(255,255,255,0.1);
            padding: 10px 15px;
            font-size: 13px;
            vertical-align: middle;
        }
        .table tbody tr:hover { background: rgba(255,255,255,0.05); }
        .badge-action {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-login { background: #28a745; }
        .badge-logout { background: #6c757d; }
        .badge-add { background: #17a2b8; }
        .badge-edit { background: #ffc107; color: #000; }
        .badge-delete { background: #dc3545; }
        .badge-export { background: #6f42c1; }
        .badge-view { background: #007bff; }
        .badge-default { background: #343a40; }
        .pagination-wrapper {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination { margin: 0; }
        .page-link {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: #fff;
        }
        .page-link:hover { background: rgba(0,217,255,0.3); color: #fff; border-color: #00d9ff; }
        .page-item.active .page-link { background: #00d9ff; border-color: #00d9ff; color: #000; }
        .page-item.disabled .page-link { background: rgba(255,255,255,0.05); color: #666; }
        .stats-badge {
            background: rgba(0,217,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
        }
        @media print {
            body { background: #fff; color: #000; }
            .filters-card, .pagination-wrapper, .btn-back { display: none; }
            .page-header { background: none; }
            .log-table { background: none; }
            .table, .table th, .table td { color: #000; border-color: #ddd; }
        }
    </style>
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="brand-loader">
        <img src="assets/visionlogo.jpg" alt="VisionAngles" class="loader-logo">
        <div class="dots-loader">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="page-header">
        <h2>
            <img src="assets/visionlogo.jpg" alt="Logo">
            <i class="bi bi-journal-text"></i> Activity Log
        </h2>
        <a href="dashboard_superadmin.php" class="btn-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>

    <div class="filters-card">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Company</label>
                <select name="company" class="form-select">
                    <option value="All">All Companies</option>
                    <?php while($c = $companies->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_company == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Username</label>
                <select name="username" class="form-select">
                    <option value="All">All Users</option>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($u['username']) ?>" <?= $filter_user == $u['username'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="All">All Actions</option>
                    <?php while($a = $actions->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filter_action == $a['action'] ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Per Page</label>
                <select name="per_page" class="form-select">
                    <?php foreach([25, 50, 100, 200, 500] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $per_page == $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-filter"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>
        <div class="mt-2">
            <a href="activity_log.php" class="btn btn-clear btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
            <button onclick="window.print()" class="btn btn-clear btn-sm"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <div class="log-table">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date & Time</th>
                    <th>Company</th>
                    <th>Username</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result->num_rows > 0): ?>
                    <?php $sn = $offset + 1; while($row = $result->fetch_assoc()): ?>
                        <?php
                        // Determine badge class based on action
                        $action = $row['action'];
                        $badge_class = 'badge-default';
                        if (strpos($action, 'LOGIN') !== false) $badge_class = 'badge-login';
                        elseif (strpos($action, 'LOGOUT') !== false) $badge_class = 'badge-logout';
                        elseif (strpos($action, 'ADD') !== false) $badge_class = 'badge-add';
                        elseif (strpos($action, 'EDIT') !== false) $badge_class = 'badge-edit';
                        elseif (strpos($action, 'DELETE') !== false) $badge_class = 'badge-delete';
                        elseif (strpos($action, 'EXPORT') !== false) $badge_class = 'badge-export';
                        elseif (strpos($action, 'VIEW') !== false) $badge_class = 'badge-view';
                        ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= date('M d, Y H:i:s', strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                            <td><span class="badge badge-action <?= $badge_class ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><code><?= htmlspecialchars($row['ip_address']) ?></code></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                            <p class="mt-2 mb-0">No activity logs found</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        <div class="stats-badge">
            <i class="bi bi-list-ul"></i> Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_records) ?> of <?= number_format($total_records) ?> records
        </div>
        
        <?php if($total_pages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm">
                <?php 
                $query_params = $_GET;
                unset($query_params['page']);
                $query_string = http_build_query($query_params);
                ?>
                
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=1&<?= $query_string ?>"><i class="bi bi-chevron-double-left"></i></a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $query_string ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= $query_string ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $query_string ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $total_pages ?>&<?= $query_string ?>"><i class="bi bi-chevron-double-right"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.classList.add('hidden');
        setTimeout(() => loader.style.display = 'none', 500);
    }
});
</script>
</body>
</html>
