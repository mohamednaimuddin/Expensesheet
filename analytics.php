<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['user', 'admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$today = date('Y-m-d');
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? $today;
$region_filter = $_GET['region'] ?? 'All';
$selected_company = isset($_GET['company_id']) ? trim($_GET['company_id']) : 'All';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) $to_date = $today;
if ($from_date > $to_date) {
    [$from_date, $to_date] = [$to_date, $from_date];
}

$expense_tables = [
    'fuel_expense' => ['label' => 'Fuel', 'icon' => 'bi-fuel-pump', 'color' => '#2563eb'],
    'food_expense' => ['label' => 'Food', 'icon' => 'bi-egg-fried', 'color' => '#16a34a'],
    'room_expense' => ['label' => 'Room', 'icon' => 'bi-house-door', 'color' => '#9333ea'],
    'other_expense' => ['label' => 'Other', 'icon' => 'bi-grid', 'color' => '#64748b'],
    'tools_expense' => ['label' => 'Tools', 'icon' => 'bi-tools', 'color' => '#ea580c'],
    'labour_expense' => ['label' => 'Labour', 'icon' => 'bi-person-workspace', 'color' => '#dc2626'],
    'accessories_expense' => ['label' => 'Accessories', 'icon' => 'bi-bag', 'color' => '#0891b2'],
    'tv_expense' => ['label' => 'TV', 'icon' => 'bi-tv', 'color' => '#7c3aed'],
    'vehicle_expense' => ['label' => 'Vehicle', 'icon' => 'bi-truck', 'color' => '#0f766e'],
    'taxi_expense' => ['label' => 'Taxi', 'icon' => 'bi-taxi-front', 'color' => '#ca8a04']
];

$column_cache = [];
function table_has_column($conn, $table, $column) {
    global $column_cache;
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $column_cache)) {
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safe_column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `$safe_table` LIKE '$safe_column'");
        $column_cache[$key] = $res && $res->num_rows > 0;
    }
    return $column_cache[$key];
}

function bind_and_execute($stmt, $types, $params) {
    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    return $stmt->get_result();
}

$admin_company_id = null;
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT company_id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin_company_id = $stmt->get_result()->fetch_assoc()['company_id'] ?? '';
}

$companies = [];
if ($role === 'superadmin') {
    $company_res = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    if ($company_res) {
        while ($company = $company_res->fetch_assoc()) {
            $companies[] = $company;
        }
    }
}

function build_scope_sql($conn, $table, $role, $username, $admin_company_id, $selected_company, $region_filter) {
    $join = '';
    $where = ["e.submitted=1", "e.date BETWEEN ? AND ?"];
    $types = "ss";
    $params = [$GLOBALS['from_date'], $GLOBALS['to_date']];

    if ($role === 'user') {
        $where[] = "e.username=?";
        $types .= "s";
        $params[] = $username;
    } elseif ($role === 'admin') {
        $join = " INNER JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = e.username COLLATE utf8mb4_unicode_ci";
        $where[] = "u.company_id=?";
        $types .= "s";
        $params[] = $admin_company_id;
    } elseif ($selected_company !== 'All' && $selected_company !== '') {
        $join = " INNER JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = e.username COLLATE utf8mb4_unicode_ci";
        $where[] = "u.company_id=?";
        $types .= "s";
        $params[] = $selected_company;
    }

    if ($region_filter !== 'All' && table_has_column($conn, $table, 'region')) {
        $where[] = "e.region=?";
        $types .= "s";
        $params[] = $region_filter;
    }

    return [$join, implode(" AND ", $where), $types, $params];
}

$category_totals = [];
$category_counts = [];
$pending_bills = 0;
$total_expense = 0.0;
$total_records = 0;
$top_users = [];
$recent_expenses = [];
$month_totals = [];

$period = new DatePeriod(
    new DateTime(date('Y-m-01', strtotime('-5 months', strtotime($to_date)))),
    new DateInterval('P1M'),
    (new DateTime(date('Y-m-01', strtotime($to_date))))->modify('+1 month')
);
foreach ($period as $month) {
    $month_totals[$month->format('Y-m')] = 0.0;
}

foreach ($expense_tables as $table => $meta) {
    [$join, $where, $types, $params] = build_scope_sql($conn, $table, $role, $username, $admin_company_id, $selected_company, $region_filter);

    $stmt = $conn->prepare("SELECT IFNULL(SUM(e.amount),0) AS total, COUNT(*) AS records FROM `$table` e $join WHERE $where");
    $res = bind_and_execute($stmt, $types, $params);
    $row = $res->fetch_assoc();
    $category_totals[$table] = floatval($row['total'] ?? 0);
    $category_counts[$table] = intval($row['records'] ?? 0);
    $total_expense += $category_totals[$table];
    $total_records += $category_counts[$table];

    if (table_has_column($conn, $table, 'bill')) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS pending FROM `$table` e $join WHERE $where AND (e.bill IS NULL OR TRIM(e.bill)='' OR LOWER(e.bill) IN ('no','pending','n','0'))");
        $res = bind_and_execute($stmt, $types, $params);
        $pending_bills += intval($res->fetch_assoc()['pending'] ?? 0);
    }

    $stmt = $conn->prepare("SELECT DATE_FORMAT(e.date, '%Y-%m') AS month_key, IFNULL(SUM(e.amount),0) AS total FROM `$table` e $join WHERE $where GROUP BY month_key");
    $res = bind_and_execute($stmt, $types, $params);
    while ($row = $res->fetch_assoc()) {
        if (isset($month_totals[$row['month_key']])) {
            $month_totals[$row['month_key']] += floatval($row['total']);
        }
    }

    if ($role !== 'user') {
        $stmt = $conn->prepare("SELECT e.username, IFNULL(SUM(e.amount),0) AS total FROM `$table` e $join WHERE $where GROUP BY e.username");
        $res = bind_and_execute($stmt, $types, $params);
        while ($row = $res->fetch_assoc()) {
            $top_users[$row['username']] = ($top_users[$row['username']] ?? 0) + floatval($row['total']);
        }
    }

    $description = table_has_column($conn, $table, 'description') ? 'e.description' : (table_has_column($conn, $table, 'service') ? 'e.service' : "''");
    if ($table === 'taxi_expense' && table_has_column($conn, $table, 'from_location') && table_has_column($conn, $table, 'to_location')) {
        $description = "CONCAT(e.from_location, ' to ', e.to_location)";
    }
    $stmt = $conn->prepare("SELECT e.username, $description AS description, e.amount, e.date FROM `$table` e $join WHERE $where ORDER BY e.date DESC, e.id DESC LIMIT 8");
    $res = bind_and_execute($stmt, $types, $params);
    while ($row = $res->fetch_assoc()) {
        $row['type'] = $meta['label'];
        $recent_expenses[] = $row;
    }
}

arsort($top_users);
$top_users = array_slice($top_users, 0, 6, true);
usort($recent_expenses, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
$recent_expenses = array_slice($recent_expenses, 0, 10);

$advance_where = ["a.date BETWEEN ? AND ?"];
$advance_types = "ss";
$advance_params = [$from_date, $to_date];
$advance_join = '';
if ($role === 'user') {
    $advance_where[] = "username=?";
    $advance_types .= "s";
    $advance_params[] = $username;
} elseif ($role === 'admin') {
    $advance_join = " INNER JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = a.username COLLATE utf8mb4_unicode_ci";
    $advance_where[] = "u.company_id=?";
    $advance_types .= "s";
    $advance_params[] = $admin_company_id;
} elseif ($selected_company !== 'All' && $selected_company !== '') {
    $advance_join = " INNER JOIN users u ON u.username COLLATE utf8mb4_unicode_ci = a.username COLLATE utf8mb4_unicode_ci";
    $advance_where[] = "u.company_id=?";
    $advance_types .= "s";
    $advance_params[] = $selected_company;
}
$stmt = $conn->prepare("SELECT IFNULL(SUM(a.adv_amt),0) AS total FROM adv_amt a $advance_join WHERE " . implode(" AND ", $advance_where));
$advance_res = bind_and_execute($stmt, $advance_types, $advance_params);
$total_advance = floatval($advance_res->fetch_assoc()['total'] ?? 0);

$days = max(1, ((strtotime($to_date) - strtotime($from_date)) / 86400) + 1);
$daily_average = $total_expense / $days;
$balance = $total_advance - $total_expense;
$back_url = $role === 'superadmin' ? 'dashboard_superadmin.php' : ($role === 'admin' ? 'dashboard_admin.php' : 'dashboard_user.php');
$report_url = $role === 'user' ? 'report.php' : 'all_user_expenses.php';
$selected_company_label = 'All Companies';
if ($role === 'admin') {
    $selected_company_label = 'My Company';
} elseif ($role === 'user') {
    $selected_company_label = ucfirst($username);
} elseif ($selected_company !== 'All') {
    foreach ($companies as $company) {
        if ((string)$company['id'] === (string)$selected_company) {
            $selected_company_label = $company['company_name'];
            break;
        }
    }
}

$category_labels = [];
$category_values = [];
$category_colors = [];
foreach ($expense_tables as $table => $meta) {
    if ($category_totals[$table] > 0) {
        $category_labels[] = $meta['label'] . ' - SAR ' . number_format($category_totals[$table], 2);
        $category_values[] = round($category_totals[$table], 2);
        $category_colors[] = $meta['color'];
    }
}
if (empty($category_labels)) {
    $category_labels = ['No expenses'];
    $category_values = [0];
    $category_colors = ['#cbd5e1'];
}

$month_labels = array_map(function($month) {
    return date('M Y', strtotime($month . '-01'));
}, array_keys($month_totals));
$month_values = array_map(function($value) {
    return round($value, 2);
}, array_values($month_totals));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Analytics | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link rel="stylesheet" href="assets/loader.css">
<style>
    :root {
        --ink: #0f172a;
        --muted: #64748b;
        --line: #dbe4f0;
        --panel: rgba(255,255,255,.92);
        --brand: #2563eb;
        --accent: #0f766e;
    }
    body {
        min-height: 100vh;
        margin: 0;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: var(--ink);
        background:
            radial-gradient(circle at 15% 10%, rgba(37,99,235,.16), transparent 28%),
            radial-gradient(circle at 85% 0%, rgba(15,118,110,.14), transparent 26%),
            linear-gradient(135deg, #f8fafc 0%, #eef4ff 50%, #f7fbfa 100%);
    }
    .navbar {
        background: #0f172a;
        box-shadow: 0 14px 28px rgba(15,23,42,.14);
    }
    .navbar-brand, .navbar-text { color: #fff !important; font-weight: 700; }
    .page-shell { max-width: 1320px; margin: 0 auto; padding: 24px 16px 44px; }
    .hero {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: center;
        padding: 24px;
        border: 1px solid rgba(255,255,255,.78);
        background: var(--panel);
        border-radius: 8px;
        box-shadow: 0 20px 60px rgba(15,23,42,.10);
    }
    .hero h1 { font-weight: 800; margin: 0; letter-spacing: 0; }
    .hero p { color: var(--muted); margin: 6px 0 0; }
    .filter-card, .metric-card, .chart-card, .table-card {
        border: 1px solid var(--line);
        background: var(--panel);
        border-radius: 8px;
        box-shadow: 0 14px 38px rgba(15,23,42,.08);
    }
    .filter-card { padding: 16px; margin: 18px 0; }
    .metric-card { padding: 18px; height: 100%; }
    .metric-icon {
        width: 42px; height: 42px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; background: var(--brand); border-radius: 8px;
        margin-bottom: 14px; font-size: 1.25rem;
    }
    .metric-label { color: var(--muted); margin: 0; font-weight: 700; font-size: .88rem; text-transform: uppercase; }
    .metric-value { font-size: 1.55rem; font-weight: 800; margin: 3px 0 0; }
    .chart-card { padding: 20px; min-height: 360px; }
    .chart-canvas-box {
        position: relative;
        height: 260px;
    }
    .chart-title { font-weight: 800; margin-bottom: 16px; }
    .category-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eef2f7;
    }
    .category-row:last-child { border-bottom: 0; }
    .category-name { display: flex; align-items: center; gap: 10px; font-weight: 700; }
    .category-name i { color: var(--brand); }
    .category-amount { font-weight: 800; }
    .bar-track { grid-column: 1 / -1; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 999px; background: var(--accent); }
    .table-card { overflow: hidden; }
    .table-card .table { margin: 0; }
    .table-card th { color: var(--muted); font-size: .82rem; text-transform: uppercase; }
    .btn-primary { background: var(--brand); border-color: var(--brand); }
    .btn-outline-dark { border-color: #334155; color: #0f172a; }
    .empty-state { color: var(--muted); padding: 28px; text-align: center; }
    .print-meta { display: none; }
    @media (max-width: 768px) {
        .hero { align-items: flex-start; flex-direction: column; }
        .metric-value { font-size: 1.28rem; }
    }
    @media print {
        @page { size: A4 landscape; margin: 6mm; }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            width: 297mm;
            min-height: 210mm;
            background: #fff !important;
        }
        body { color: #0f172a; font-size: 9px; line-height: 1.2; }
        .navbar, .filter-card, .no-print { display: none !important; }
        .page-shell {
            max-width: none;
            width: 100%;
            padding: 0;
        }
        .hero {
            padding: 7px 10px;
            margin-bottom: 6px;
            box-shadow: none;
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .hero h1 { font-size: 18px; }
        .hero p { display: none; }
        .print-meta {
            display: block;
            margin: 0 0 7px;
            color: #475569;
            font-size: 9px;
        }
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
            --bs-gutter-x: 8px;
            --bs-gutter-y: 8px;
        }
        .mb-4 { margin-bottom: 8px !important; }
        .col-lg-3, .col-md-6 {
            flex: 0 0 25% !important;
            max-width: 25% !important;
        }
        .print-charts .col-xl-7,
        .print-charts .col-xl-5,
        .print-details .col-xl-5,
        .print-details .col-xl-7 {
            flex: 0 0 50% !important;
            max-width: 50% !important;
        }
        .print-hide {
            display: none !important;
        }
        .metric-card, .chart-card, .table-card {
            box-shadow: none;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            break-inside: avoid;
            page-break-inside: avoid;
            background: #fff;
        }
        .metric-card {
            min-height: 72px;
            padding: 8px 10px;
        }
        .metric-icon {
            width: 22px;
            height: 22px;
            margin-bottom: 5px;
            border-radius: 4px;
            font-size: 11px;
        }
        .metric-label { font-size: 8px; }
        .metric-value { font-size: 14px; }
        .print-charts .chart-card {
            padding: 10px;
            min-height: 0;
            height: 164px;
            display: flex;
            flex-direction: column;
        }
        .print-details .chart-card,
        .print-details .table-card {
            min-height: 0;
            height: 320px;
            padding: 10px;
            overflow: hidden;
        }
        .print-details .table-card {
            display: flex;
            flex-direction: column;
        }
        .print-details .table-card table {
            flex: 1;
        }
        .chart-title {
            font-size: 12px;
            margin-bottom: 8px;
        }
        .print-charts .chart-canvas-box {
            flex: 1;
            height: 126px !important;
            min-height: 126px !important;
            max-height: 126px !important;
        }
        .print-charts canvas {
            width: 100% !important;
            height: 126px !important;
            max-height: 126px !important;
        }
        .category-row {
            grid-template-columns: 1fr auto;
            gap: 4px 8px;
            padding: 6px 0;
            font-size: 9px;
        }
        .category-name,
        .category-amount {
            line-height: 1.15;
        }
        .bar-track { height: 5px; }
        .table-card .p-3 {
            padding: 0 0 7px !important;
        }
        .spender-table {
            table-layout: fixed;
        }
        .spender-table th:first-child,
        .spender-table td:first-child {
            width: 62%;
        }
        .spender-table td:first-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table {
            font-size: 9px;
            line-height: 1.2;
        }
        .table > :not(caption) > * > * {
            padding: 6px 4px;
        }
        .print-details .empty-state {
            padding: 36px 12px;
            font-size: 12px;
        }
        .table-responsive {
            overflow: hidden !important;
        }
        a { color: inherit !important; text-decoration: none !important; }
    }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid page-shell py-2">
        <a class="navbar-brand d-flex align-items-center" href="<?= htmlspecialchars($back_url) ?>">
            <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
            <span>Vision Angles Security EST.</span>
        </a>
        <div class="navbar-nav ms-auto d-flex align-items-center">
            <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars(ucfirst($username)) ?></span>
            <a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars($back_url) ?>"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
</nav>

<main class="page-shell">
    <section class="hero">
        <div>
            <h1><i class="bi bi-bar-chart-line"></i> Expense Analytics</h1>
            <p>Track spending trends, category mix, advances, and recent expense movement.</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-outline-dark" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print A4</button>
            <a class="btn btn-primary" href="<?= htmlspecialchars($report_url) ?>"><i class="bi bi-table"></i> Expense Report</a>
        </div>
    </section>
    <p class="print-meta">
        Period: <?= htmlspecialchars(date('d M Y', strtotime($from_date))) ?> to <?= htmlspecialchars(date('d M Y', strtotime($to_date))) ?>
        | Region: <?= htmlspecialchars($region_filter) ?>
        | Scope: <?= htmlspecialchars($selected_company_label) ?>
        | Printed: <?= htmlspecialchars(date('d M Y h:i A')) ?>
    </p>

    <form method="get" class="filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-4">
                <label class="form-label">From</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">To</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Region</label>
                <select name="region" class="form-select">
                    <?php foreach (['All', 'Central', 'Eastern', 'Western', 'Northern', 'Southern'] as $region): ?>
                        <option value="<?= htmlspecialchars($region) ?>" <?= $region_filter === $region ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($role === 'superadmin'): ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Company</label>
                <select name="company_id" class="form-select">
                    <option value="All">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= htmlspecialchars($company['id']) ?>" <?= (string)$selected_company === (string)$company['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($company['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                <a class="btn btn-outline-dark" href="analytics.php"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4 print-metrics">
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <span class="metric-icon"><i class="bi bi-cash-stack"></i></span>
                <p class="metric-label">Total Expense</p>
                <p class="metric-value">SAR <?= number_format($total_expense, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <span class="metric-icon" style="background:#16a34a"><i class="bi bi-wallet2"></i></span>
                <p class="metric-label">Total Advance</p>
                <p class="metric-value">SAR <?= number_format($total_advance, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <span class="metric-icon" style="background:#0f766e"><i class="bi bi-calculator"></i></span>
                <p class="metric-label">Balance</p>
                <p class="metric-value">SAR <?= number_format($balance, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <span class="metric-icon" style="background:#ca8a04"><i class="bi bi-receipt"></i></span>
                <p class="metric-label">Records / Pending Bills</p>
                <p class="metric-value"><?= number_format($total_records) ?> / <?= number_format($pending_bills) ?></p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 print-charts">
        <div class="col-xl-7">
            <div class="chart-card">
                <h5 class="chart-title">Six Month Spending Trend</h5>
                <div class="chart-canvas-box">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="chart-card">
                <h5 class="chart-title">Category Share</h5>
                <div class="chart-canvas-box">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 print-details">
        <div class="col-xl-5">
            <div class="chart-card">
                <h5 class="chart-title">Category Breakdown</h5>
                <?php $max_category = max($category_totals ?: [1]); ?>
                <?php foreach ($expense_tables as $table => $meta): ?>
                    <?php $percent = $max_category > 0 ? ($category_totals[$table] / $max_category) * 100 : 0; ?>
                    <div class="category-row">
                        <div class="category-name"><i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i> <?= htmlspecialchars($meta['label']) ?></div>
                        <div class="category-amount">SAR <?= number_format($category_totals[$table], 2) ?></div>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= round($percent, 2) ?>%; background: <?= htmlspecialchars($meta['color']) ?>"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="table-card">
                <div class="p-3 border-bottom">
                    <h5 class="chart-title mb-0"><?= $role === 'user' ? 'Recent Expenses' : 'Top Spenders' ?></h5>
                </div>
                <?php if ($role === 'user'): ?>
                    <div class="empty-state">Your daily average is SAR <?= number_format($daily_average, 2) ?> for the selected period.</div>
                <?php elseif (!empty($top_users)): ?>
                    <table class="table table-hover align-middle spender-table">
                        <thead><tr><th>User</th><th class="text-end">Expense</th></tr></thead>
                        <tbody>
                        <?php $spender_index = 0; foreach ($top_users as $top_user => $amount): if (++$spender_index > 5) break; ?>
                            <tr>
                                <td><i class="bi bi-person-circle me-2 text-primary"></i><?= htmlspecialchars($top_user) ?></td>
                                <td class="text-end fw-bold">SAR <?= number_format($amount, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No spender data for the selected filters.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="table-card print-hide">
        <div class="p-3 border-bottom">
            <h5 class="chart-title mb-0">Recent Expense Entries</h5>
        </div>
        <?php if (!empty($recent_expenses)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_expenses as $expense): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($expense['date']))) ?></td>
                            <td><?= htmlspecialchars($expense['type']) ?></td>
                            <td><?= htmlspecialchars($expense['username']) ?></td>
                            <td><?= htmlspecialchars($expense['description'] ?: '-') ?></td>
                            <td class="text-end fw-bold">SAR <?= number_format(floatval($expense['amount']), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">No expenses found for the selected filters.</div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const moneyTick = value => 'SAR ' + Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 });
const trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($month_labels) ?>,
        datasets: [{
            label: 'Expenses',
            data: <?= json_encode($month_values) ?>,
            backgroundColor: '#2563eb',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { font: { size: 10 } } },
            y: { ticks: { callback: moneyTick, font: { size: 10 } }, beginAtZero: true }
        }
    }
});
const categoryChart = new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($category_labels) ?>,
        datasets: [{
            data: <?= json_encode($category_values) ?>,
            backgroundColor: <?= json_encode($category_colors) ?>,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
        cutout: '62%'
    }
});
window.addEventListener('beforeprint', () => {
    trendChart.resize(500, 126);
    categoryChart.resize(500, 126);
});
window.addEventListener('afterprint', () => {
    trendChart.resize();
    categoryChart.resize();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
