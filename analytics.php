<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['user', 'admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$conn->query("CREATE TABLE IF NOT EXISTS petro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `date` DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_by VARCHAR(50) NOT NULL,
    company_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_petro_date (`date`),
    INDEX idx_petro_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

function load_region_options($conn, $expense_tables) {
    $regions = [];
    foreach (array_keys($expense_tables) as $table) {
        if (!table_has_column($conn, $table, 'region')) {
            continue;
        }
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $res = $conn->query("SELECT DISTINCT TRIM(region) AS region_name FROM `$safe_table` WHERE region IS NOT NULL AND TRIM(region) <> ''");
        if (!$res) {
            continue;
        }
        while ($row = $res->fetch_assoc()) {
            $region_name = trim($row['region_name'] ?? '');
            if ($region_name !== '') {
                $regions[$region_name] = true;
            }
        }
    }

    $preferred_order = ['Riyadh', 'Dammam', 'Jeddah', 'Other'];
    $ordered = [];
    foreach ($preferred_order as $region) {
        if (isset($regions[$region])) {
            $ordered[] = $region;
            unset($regions[$region]);
        }
    }
    $remaining = array_keys($regions);
    natcasesort($remaining);
    return array_merge($ordered, array_values($remaining));
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

$region_options = load_region_options($conn, $expense_tables);
if ($region_filter !== 'All' && !in_array($region_filter, $region_options, true)) {
    $region_filter = 'All';
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
        $where[] = "LOWER(TRIM(e.region))=LOWER(?)";
        $types .= "s";
        $params[] = $region_filter;
    }

    return [$join, implode(" AND ", $where), $types, $params];
}

function build_petro_scope_sql($role, $username, $admin_company_id, $selected_company) {
    $where = ["p.date BETWEEN ? AND ?"];
    $types = "ss";
    $params = [$GLOBALS['from_date'], $GLOBALS['to_date']];

    if ($role === 'user') {
        $where[] = "p.created_by=?";
        $types .= "s";
        $params[] = $username;
    } elseif ($role === 'admin') {
        $where[] = "p.company_id=?";
        $types .= "s";
        $params[] = $admin_company_id;
    } elseif ($selected_company !== 'All' && $selected_company !== '') {
        $where[] = "p.company_id=?";
        $types .= "s";
        $params[] = $selected_company;
    }

    return [implode(" AND ", $where), $types, $params];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_petro'])) {
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        die("Not allowed.");
    }

    $petro_month = trim($_POST['petro_month'] ?? '');
    $petro_amount = floatval($_POST['petro_amount'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}$/', $petro_month) || $petro_amount <= 0) {
        die("Invalid Petro Fuel entry.");
    }

    $petro_date = $petro_month . '-01';
    $petro_company_id = null;
    if ($role === 'admin') {
        $petro_company_id = $admin_company_id !== '' ? intval($admin_company_id) : null;
    } elseif ($selected_company !== 'All' && $selected_company !== '') {
        $petro_company_id = intval($selected_company);
    }

    if ($petro_company_id === null) {
        $stmt = $conn->prepare("INSERT INTO petro (`date`, amount, created_by, company_id) VALUES (?, ?, ?, NULL)");
        $stmt->bind_param("sds", $petro_date, $petro_amount, $username);
    } else {
        $stmt = $conn->prepare("INSERT INTO petro (`date`, amount, created_by, company_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdsi", $petro_date, $petro_amount, $username, $petro_company_id);
    }
    $stmt->execute();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
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

[$petro_where, $petro_types, $petro_params] = build_petro_scope_sql($role, $username, $admin_company_id, $selected_company);
$stmt = $conn->prepare("SELECT IFNULL(SUM(p.amount),0) AS total, COUNT(*) AS records FROM petro p WHERE $petro_where");
$res = bind_and_execute($stmt, $petro_types, $petro_params);
$petro_row = $res->fetch_assoc();
$petro_total = floatval($petro_row['total'] ?? 0);
$petro_records = intval($petro_row['records'] ?? 0);
$fuel_base_total = floatval($category_totals['fuel_expense'] ?? 0);
$category_totals['fuel_expense'] = ($category_totals['fuel_expense'] ?? 0) + $petro_total;
$category_counts['fuel_expense'] = ($category_counts['fuel_expense'] ?? 0) + $petro_records;
$total_expense += $petro_total;
$total_records += $petro_records;

$stmt = $conn->prepare("SELECT DATE_FORMAT(p.date, '%Y-%m') AS month_key, IFNULL(SUM(p.amount),0) AS total FROM petro p WHERE $petro_where GROUP BY month_key");
$res = bind_and_execute($stmt, $petro_types, $petro_params);
while ($row = $res->fetch_assoc()) {
    if (isset($month_totals[$row['month_key']])) {
        $month_totals[$row['month_key']] += floatval($row['total']);
    }
}

$stmt = $conn->prepare("SELECT p.created_by AS username, 'Petro Fuel' AS description, p.amount, p.date FROM petro p WHERE $petro_where ORDER BY p.date DESC, p.id DESC");
$res = bind_and_execute($stmt, $petro_types, $petro_params);
$petro_transactions = [];
while ($row = $res->fetch_assoc()) {
    $row['type'] = 'Fuel';
    $petro_transactions[] = $row;
    $recent_expenses[] = $row;
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
    .filter-card {
        padding: 18px;
        margin: 18px 0;
    }
    .analytics-filter {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
        gap: 14px;
        align-items: end;
    }
    .filter-card.has-company .analytics-filter {
        grid-template-columns: repeat(4, minmax(170px, 1fr)) auto;
    }
    .filter-field {
        min-width: 0;
    }
    .filter-label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 7px;
        color: #475569;
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .filter-control {
        position: relative;
    }
    .filter-control > i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #2563eb;
        font-size: 1rem;
        pointer-events: none;
        z-index: 2;
    }
    .filter-control .form-control,
    .filter-control .form-select {
        min-height: 46px;
        padding-left: 40px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        color: #0f172a;
        background-color: rgba(255,255,255,.92);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
        font-weight: 700;
    }
    .filter-control .form-select {
        padding-right: 42px;
    }
    .filter-control .form-control:focus,
    .filter-control .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37,99,235,.12);
    }
    .filter-control .date-display {
        cursor: pointer;
    }
    .date-picker-wrap {
        position: relative;
    }
    .analytics-calendar {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: min(316px, calc(100vw - 32px));
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 18px 48px rgba(15,23,42,.18);
        z-index: 50;
        display: none;
    }
    .analytics-calendar.is-open {
        display: block;
    }
    .calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
    }
    .calendar-selects {
        display: grid;
        grid-template-columns: minmax(120px, 1fr) 86px;
        gap: 7px;
        flex: 1;
        min-width: 0;
    }
    .calendar-selects select {
        min-height: 34px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background-color: #fff;
        color: #0f172a;
        font-weight: 800;
        padding: 4px 28px 4px 8px;
    }
    .calendar-nav {
        display: flex;
        gap: 4px;
    }
    .calendar-nav button,
    .calendar-day {
        border: 0;
        background: transparent;
        border-radius: 6px;
    }
    .calendar-nav button {
        width: 34px;
        height: 34px;
        color: #334155;
        font-size: 1.1rem;
    }
    .calendar-nav button:hover,
    .calendar-day:hover {
        background: #e0ecff;
        color: #1d4ed8;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        text-align: center;
    }
    .calendar-weekday {
        color: #475569;
        font-size: .78rem;
        font-weight: 800;
        padding: 5px 0;
    }
    .calendar-day {
        min-height: 34px;
        color: #0f172a;
        font-weight: 700;
    }
    .calendar-day.is-muted {
        color: #94a3b8;
        font-weight: 600;
    }
    .calendar-day.is-today {
        box-shadow: inset 0 0 0 1px #93c5fd;
    }
    .calendar-day.is-selected {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 8px 18px rgba(37,99,235,.24);
    }
    .calendar-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 9px;
        border-top: 1px solid #e2e8f0;
    }
    .calendar-foot button {
        border: 0;
        background: transparent;
        color: #2563eb;
        font-weight: 800;
        padding: 5px 7px;
        border-radius: 6px;
    }
    .calendar-foot button:hover {
        background: #eff6ff;
    }
    .filter-actions {
        display: grid;
        grid-template-columns: minmax(116px, 1fr) 46px;
        gap: 9px;
    }
    .filter-actions .btn {
        min-height: 46px;
        border-radius: 8px;
        font-weight: 800;
        white-space: nowrap;
    }
    .filter-reset {
        width: 46px;
        padding-left: 0;
        padding-right: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .filter-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid #e2e8f0;
    }
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 30px;
        padding: 5px 10px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #334155;
        font-size: .84rem;
        font-weight: 700;
    }
    .metric-card { padding: 18px; height: 100%; }
    .metric-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .metric-icon {
        width: 42px; height: 42px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; background: var(--brand); border-radius: 8px;
        flex: 0 0 auto; font-size: 1.25rem;
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
    .category-label-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }
    .btn-petro-inline {
        min-width: 0;
        height: 28px;
        padding: 0 9px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        border: 1px solid rgba(37,99,235,.26);
        background: #eff6ff;
        color: #1d4ed8;
        flex: 0 0 auto;
        font-size: .78rem;
        font-weight: 800;
        line-height: 1;
    }
    .btn-petro-inline:hover {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 8px 18px rgba(37,99,235,.22);
    }
    .btn-petro-inline i {
        color: currentColor;
    }
    .btn-petro-view {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #334155;
    }
    .btn-petro-view:hover {
        background: #0f172a;
        color: #fff;
        box-shadow: 0 8px 18px rgba(15,23,42,.18);
    }
    .bar-track { grid-column: 1 / -1; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
    .bar-stack {
        display: flex;
    }
    .bar-fill { height: 100%; border-radius: 999px; background: var(--accent); }
    .bar-segment {
        height: 100%;
        min-width: 0;
    }
    .bar-segment:first-child {
        border-radius: 999px 0 0 999px;
    }
    .bar-segment:last-child {
        border-radius: 0 999px 999px 0;
    }
    .bar-segment:only-child {
        border-radius: 999px;
    }
    .bar-segment-only {
        border-radius: 999px !important;
    }
    .table-card { overflow: hidden; }
    .table-card .table { margin: 0; }
    .table-card th { color: var(--muted); font-size: .82rem; text-transform: uppercase; }
    .btn-primary { background: var(--brand); border-color: var(--brand); }
    .btn-outline-dark { border-color: #334155; color: #0f172a; }
    .empty-state { color: var(--muted); padding: 28px; text-align: center; }
    .print-meta { display: none; }
    .petro-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15,23,42,.55);
        z-index: 100000;
    }
    .petro-modal.is-open {
        display: flex;
    }
    .petro-dialog {
        width: min(420px, 100%);
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,.7);
        background: #fff;
        box-shadow: 0 28px 80px rgba(15,23,42,.28);
        overflow: hidden;
    }
    .petro-dialog-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .petro-dialog-head h5 {
        margin: 0;
        font-weight: 800;
    }
    .petro-close {
        border: 0;
        background: #f1f5f9;
        color: #334155;
        width: 34px;
        height: 34px;
        border-radius: 8px;
    }
    .petro-close:hover {
        background: #e2e8f0;
    }
    .petro-dialog-body {
        padding: 18px;
    }
    @media (max-width: 768px) {
        .hero { align-items: flex-start; flex-direction: column; }
        .metric-value { font-size: 1.28rem; }
        .analytics-filter,
        .filter-card.has-company .analytics-filter {
            grid-template-columns: 1fr;
        }
        .filter-actions {
            grid-template-columns: 1fr 46px;
        }
    }
    @media print {
        @page { size: A4 landscape; margin: 6mm; }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            width: auto;
            height: auto;
            min-height: 0;
            margin: 0;
            overflow: visible;
            background: #fff !important;
        }
        body { color: #0f172a; font-size: 10px; line-height: 1.25; }
        main.page-shell {
            zoom: 1;
        }
        .navbar, .filter-card, .no-print { display: none !important; }
        .page-shell {
            max-width: none;
            width: 100%;
            padding: 0;
            overflow: visible;
            box-sizing: border-box;
        }
        main.page-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            column-gap: 6px;
            row-gap: 3px;
        }
        .hero {
            grid-column: 1 / -1;
            padding: 5px 9px;
            margin-bottom: 4px;
            box-shadow: none;
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .hero h1 { font-size: 16px; }
        .hero p { display: none; }
        .print-meta {
            grid-column: 1 / -1;
            display: block;
            margin: 0 0 5px;
            color: #475569;
            font-size: 8.5px;
        }
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
            --bs-gutter-x: 6px;
            --bs-gutter-y: 0;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            width: 100% !important;
        }
        .row > * {
            margin-top: 0 !important;
            padding-left: 3px !important;
            padding-right: 3px !important;
        }
        .mb-4 { margin-bottom: 4px !important; }
        .print-charts {
            margin-bottom: 0 !important;
        }
        .print-metrics {
            grid-column: 1 / -1;
        }
        .col-lg-3, .col-md-6 {
            flex: 0 0 25% !important;
            max-width: 25% !important;
        }
        .print-charts,
        .print-details {
            display: contents !important;
        }
        .print-charts > *,
        .print-details > * {
            width: auto !important;
            max-width: none !important;
            flex: none !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        .print-charts .col-xl-7 {
            grid-column: 1;
            grid-row: 4;
        }
        .print-charts .col-xl-5 {
            grid-column: 2;
            grid-row: 4;
        }
        .print-details .col-xl-5 {
            grid-column: 1;
            grid-row: 5;
        }
        .print-details .col-xl-7 {
            grid-column: 2;
            grid-row: 5;
            margin: 0 !important;
        }
        .print-hide {
            display: none !important;
        }
        .metric-card, .chart-card, .table-card {
            box-shadow: none;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            background: #fff;
        }
        .metric-card {
            min-height: 52px;
            padding: 6px 8px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .metric-head {
            gap: 5px;
            margin-bottom: 3px;
        }
        .metric-icon {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            font-size: 9px;
        }
        .metric-label { font-size: 7px; }
        .metric-value { font-size: 12px; }
        .print-charts .chart-card {
            padding: 7px 8px;
            min-height: 0;
            height: 226px;
            display: flex;
            flex-direction: column;
        }
        .print-charts .category-share-card {
            height: 226px;
        }
        .print-details {
            margin-top: 0 !important;
        }
        .print-details .chart-card,
        .print-details .table-card {
            min-height: 0;
            height: 330px;
            padding: 7px 8px;
            overflow: hidden;
        }
        .print-details .chart-card {
            height: 365px;
        }
        .print-details .top-spenders-card {
            height: 330px;
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
            margin-bottom: 6px;
        }
        .print-charts .chart-canvas-box {
            flex: 1;
            height: 186px !important;
            min-height: 186px !important;
            max-height: 186px !important;
        }
        .print-charts canvas {
            width: 100% !important;
            height: 186px !important;
            max-height: 186px !important;
        }
        .print-charts .category-share-card .chart-canvas-box {
            height: 186px !important;
            min-height: 186px !important;
            max-height: 186px !important;
        }
        .print-charts .category-share-card canvas {
            height: 186px !important;
            max-height: 186px !important;
        }
        .category-row {
            grid-template-columns: 1fr auto;
            gap: 4px 10px;
            padding: 3.5px 0;
            font-size: 11px;
        }
        .category-name,
        .category-amount {
            line-height: 1.15;
        }
        .category-name { gap: 7px; }
        .category-name i { font-size: 11px; }
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
            font-size: 10.5px;
            line-height: 1.18;
        }
        .table > :not(caption) > * > * {
            padding: 6px 5px;
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
            <button class="btn btn-outline-dark" type="button" style="pointer-events: none;">
    <i class="bi bi-printer"></i> Print A4
</button>
            <a class="btn btn-primary" href="<?= htmlspecialchars($report_url) ?>"><i class="bi bi-table"></i> Expense Report</a>
        </div>
    </section>
    <p class="print-meta">
        Period: <?= htmlspecialchars(date('d M Y', strtotime($from_date))) ?> to <?= htmlspecialchars(date('d M Y', strtotime($to_date))) ?>
        | Region: <?= htmlspecialchars($region_filter) ?>
        | Scope: <?= htmlspecialchars($selected_company_label) ?>
        | Printed: <?= htmlspecialchars(date('d M Y h:i A')) ?>
    </p>

    <form method="get" class="filter-card <?= $role === 'superadmin' ? 'has-company' : '' ?>">
        <div class="analytics-filter">
            <div class="filter-field">
                <label class="filter-label" for="from_date_display"><i class="bi bi-calendar-event"></i> From</label>
                <div class="filter-control date-picker-wrap">
                    <i class="bi bi-calendar3"></i>
                    <input type="hidden" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
                    <input type="text" id="from_date_display" class="form-control date-display js-date-picker" value="<?= htmlspecialchars(date('d-m-Y', strtotime($from_date))) ?>" data-target="from_date" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off">
                </div>
            </div>
            <div class="filter-field">
                <label class="filter-label" for="to_date_display"><i class="bi bi-calendar-check"></i> To</label>
                <div class="filter-control date-picker-wrap">
                    <i class="bi bi-calendar-range"></i>
                    <input type="hidden" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
                    <input type="text" id="to_date_display" class="form-control date-display js-date-picker" value="<?= htmlspecialchars(date('d-m-Y', strtotime($to_date))) ?>" data-target="to_date" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off">
                </div>
            </div>
            <div class="filter-field">
                <label class="filter-label" for="region"><i class="bi bi-geo-alt"></i> Region</label>
                <div class="filter-control">
                    <i class="bi bi-map"></i>
                    <select id="region" name="region" class="form-select">
                        <?php foreach (array_merge(['All'], $region_options) as $region): ?>
                            <option value="<?= htmlspecialchars($region) ?>" <?= $region_filter === $region ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($role === 'superadmin'): ?>
            <div class="filter-field">
                <label class="filter-label" for="company_id"><i class="bi bi-building"></i> Company</label>
                <div class="filter-control">
                    <i class="bi bi-buildings"></i>
                    <select id="company_id" name="company_id" class="form-select">
                        <option value="All">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= htmlspecialchars($company['id']) ?>" <?= (string)$selected_company === (string)$company['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <div class="filter-actions">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                <a class="btn btn-outline-dark filter-reset" href="analytics.php" title="Reset filters" aria-label="Reset filters">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
        <div class="filter-summary">
            <span class="filter-chip"><i class="bi bi-calendar-week"></i> <?= htmlspecialchars(date('d-m-Y', strtotime($from_date))) ?> to <?= htmlspecialchars(date('d-m-Y', strtotime($to_date))) ?></span>
            <span class="filter-chip"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($region_filter) ?></span>
            <span class="filter-chip"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($selected_company_label) ?></span>
        </div>
    </form>

    <div class="row g-3 mb-4 print-metrics">
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-icon"><i class="bi bi-cash-stack"></i></span>
                    <p class="metric-label">Total Expense</p>
                </div>
                <p class="metric-value">SAR <?= number_format($total_expense, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-icon" style="background:#16a34a"><i class="bi bi-wallet2"></i></span>
                    <p class="metric-label">Total Advance</p>
                </div>
                <p class="metric-value">SAR <?= number_format($total_advance, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-icon" style="background:#0f766e"><i class="bi bi-calculator"></i></span>
                    <p class="metric-label">Balance</p>
                </div>
                <p class="metric-value">SAR <?= number_format($balance, 2) ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-icon" style="background:#ca8a04"><i class="bi bi-receipt"></i></span>
                    <p class="metric-label">Records / Pending Bills</p>
                </div>
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
            <div class="chart-card category-share-card">
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
                    <?php
                        $fuel_segment_width = 0;
                        $petro_segment_width = 0;
                        if ($table === 'fuel_expense' && $category_totals[$table] > 0) {
                            $fuel_segment_width = max(0, min(100, ($fuel_base_total / $category_totals[$table]) * $percent));
                            $petro_segment_width = max(0, min(100, ($petro_total / $category_totals[$table]) * $percent));
                        }
                    ?>
                    <div class="category-row">
                        <div class="category-name">
                            <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                            <span class="category-label-actions">
                                <span><?= htmlspecialchars($meta['label']) ?></span>
                                <?php if ($table === 'fuel_expense' && in_array($role, ['admin', 'superadmin'], true)): ?>
                                <button class="btn-petro-inline no-print" type="button" onclick="openPetroModal()" title="Add Petro Fuel" aria-label="Add Petro Fuel">
                                    <i class="bi bi-plus-lg"></i> Petro
                                </button>
                                <button class="btn-petro-inline btn-petro-view no-print" type="button" onclick="openPetroViewModal()" title="View Petro transactions" aria-label="View Petro transactions">
                                    View Petro
                                </button>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="category-amount">SAR <?= number_format($category_totals[$table], 2) ?></div>
                        <?php if ($table === 'fuel_expense'): ?>
                            <div class="bar-track bar-stack">
                                <?php if ($fuel_segment_width > 0): ?>
                                <div class="bar-segment <?= $petro_segment_width <= 0 ? 'bar-segment-only' : '' ?>" title="Fuel: SAR <?= htmlspecialchars(number_format($fuel_base_total, 2)) ?>" style="width: <?= round($fuel_segment_width, 2) ?>%; background: <?= htmlspecialchars($meta['color']) ?>"></div>
                                <?php endif; ?>
                                <?php if ($petro_segment_width > 0): ?>
                                <div class="bar-segment <?= $fuel_segment_width <= 0 ? 'bar-segment-only' : '' ?>" title="Petro: SAR <?= htmlspecialchars(number_format($petro_total, 2)) ?>" style="width: <?= round($petro_segment_width, 2) ?>%; background: #94a3b8"></div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="bar-track"><div class="bar-fill" style="width: <?= round($percent, 2) ?>%; background: <?= htmlspecialchars($meta['color']) ?>"></div></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="table-card top-spenders-card">
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

<div class="petro-modal" id="petroModal" aria-hidden="true">
    <div class="petro-dialog">
        <div class="petro-dialog-head">
            <h5><i class="bi bi-fuel-pump text-primary"></i> Add Petro Fuel</h5>
            <button type="button" class="petro-close" onclick="closePetroModal()" aria-label="Close Petro Fuel form">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="post" class="petro-dialog-body">
            <input type="hidden" name="add_petro" value="1">
            <div class="mb-3">
                <label for="petro_month" class="form-label fw-bold">Month</label>
                <input type="month" id="petro_month" name="petro_month" class="form-control" value="<?= htmlspecialchars(date('Y-m', strtotime($from_date))) ?>" required>
            </div>
            <div class="mb-3">
                <label for="petro_amount" class="form-label fw-bold">Amount</label>
                <input type="number" id="petro_amount" name="petro_amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            <?php if ($role === 'superadmin'): ?>
            <p class="text-muted small mb-3">Entry scope: <?= htmlspecialchars($selected_company_label) ?></p>
            <?php endif; ?>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-outline-dark" onclick="closePetroModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Submit</button>
            </div>
        </form>
    </div>
</div>

<div class="petro-modal" id="petroViewModal" aria-hidden="true">
    <div class="petro-dialog">
        <div class="petro-dialog-head">
            <h5><i class="bi bi-list-ul text-primary"></i> Petro Fuel Transactions</h5>
            <button type="button" class="petro-close" onclick="closePetroViewModal()" aria-label="Close Petro transactions">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="petro-dialog-body">
            <?php if (!empty($petro_transactions)): ?>
            <div class="mb-3">
                <label for="petro_view_month" class="form-label fw-bold">Filter Month</label>
                <input type="month" id="petro_view_month" class="form-control" value="<?= htmlspecialchars(date('Y-m', strtotime($from_date))) ?>" onchange="filterPetroTransactions()">
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entered By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($petro_transactions as $petro): ?>
                        <tr class="petro-transaction-row" data-month="<?= htmlspecialchars(date('Y-m', strtotime($petro['date']))) ?>" data-amount="<?= htmlspecialchars(floatval($petro['amount'])) ?>">
                            <td><?= htmlspecialchars(date('d M Y', strtotime($petro['date']))) ?></td>
                            <td><?= htmlspecialchars($petro['username']) ?></td>
                            <td class="text-end fw-bold">SAR <?= number_format(floatval($petro['amount']), 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="petroNoRows" style="display:none;">
                            <td colspan="3" class="text-center text-muted py-3">No Petro Fuel transactions found for this month.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="fw-bold">Total</td>
                            <td class="text-end fw-bold" id="petroViewTotal">SAR <?= number_format($petro_total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state py-4">No Petro Fuel transactions found for the selected filters.</div>
            <?php endif; ?>
            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-outline-dark" onclick="closePetroViewModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openPetroModal() {
    const modal = document.getElementById('petroModal');
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    const amount = document.getElementById('petro_amount');
    if (amount) amount.focus();
}

function closePetroModal() {
    const modal = document.getElementById('petroModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

function openPetroViewModal() {
    const modal = document.getElementById('petroViewModal');
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    filterPetroTransactions();
}

function closePetroViewModal() {
    const modal = document.getElementById('petroViewModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

function filterPetroTransactions() {
    const monthInput = document.getElementById('petro_view_month');
    const selectedMonth = monthInput ? monthInput.value : '';
    const rows = document.querySelectorAll('.petro-transaction-row');
    const noRows = document.getElementById('petroNoRows');
    const totalCell = document.getElementById('petroViewTotal');
    let total = 0;
    let visibleCount = 0;

    rows.forEach(function(row) {
        const show = !selectedMonth || row.dataset.month === selectedMonth;
        row.style.display = show ? '' : 'none';
        if (show) {
            total += Number(row.dataset.amount || 0);
            visibleCount++;
        }
    });

    if (noRows) {
        noRows.style.display = visibleCount === 0 ? '' : 'none';
    }
    if (totalCell) {
        totalCell.textContent = 'SAR ' + total.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('petroModal');
    if (modal && event.target === modal) {
        closePetroModal();
    }
    const viewModal = document.getElementById('petroViewModal');
    if (viewModal && event.target === viewModal) {
        closePetroViewModal();
    }
});

(function() {
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    let activePicker = null;

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function parseIso(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) return new Date();
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function parseDisplay(value) {
        const match = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec((value || '').trim());
        if (!match) return null;
        const day = Number(match[1]);
        const month = Number(match[2]) - 1;
        const year = Number(match[3]);
        const date = new Date(year, month, day);
        if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
            return null;
        }
        return date;
    }

    function toIso(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function toDisplay(date) {
        return pad(date.getDate()) + '-' + pad(date.getMonth() + 1) + '-' + date.getFullYear();
    }

    function sameDay(a, b) {
        return a && b &&
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate();
    }

    function closeCalendar() {
        if (!activePicker) return;
        activePicker.calendar.remove();
        activePicker = null;
    }

    function syncTypedDate(input) {
        const hidden = document.getElementById(input.dataset.target);
        if (!hidden) return false;
        const date = parseDisplay(input.value);
        if (!date) {
            input.classList.add('is-invalid');
            return false;
        }
        hidden.value = toIso(date);
        input.value = toDisplay(date);
        input.classList.remove('is-invalid');
        return true;
    }

    function renderCalendar(picker) {
        const selectedDate = parseIso(picker.hidden.value);
        const year = picker.viewDate.getFullYear();
        const month = picker.viewDate.getMonth();
        const firstOfMonth = new Date(year, month, 1);
        const startDate = new Date(year, month, 1 - firstOfMonth.getDay());
        const today = new Date();

        picker.calendar.innerHTML = '';

        const head = document.createElement('div');
        head.className = 'calendar-head';

        const selects = document.createElement('div');
        selects.className = 'calendar-selects';

        const monthSelect = document.createElement('select');
        monthSelect.setAttribute('aria-label', 'Select month');
        monthSelect.addEventListener('mousedown', function(event) {
            event.stopPropagation();
        });
        monthNames.forEach(function(monthName, index) {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = monthName;
            option.selected = index === month;
            monthSelect.appendChild(option);
        });
        monthSelect.addEventListener('change', function() {
            picker.viewDate = new Date(year, Number(this.value), 1);
            renderCalendar(picker);
        });

        const yearSelect = document.createElement('select');
        yearSelect.setAttribute('aria-label', 'Select year');
        yearSelect.addEventListener('mousedown', function(event) {
            event.stopPropagation();
        });
        for (let optionYear = year - 10; optionYear <= year + 10; optionYear++) {
            const option = document.createElement('option');
            option.value = String(optionYear);
            option.textContent = String(optionYear);
            option.selected = optionYear === year;
            yearSelect.appendChild(option);
        }
        yearSelect.addEventListener('change', function() {
            picker.viewDate = new Date(Number(this.value), month, 1);
            renderCalendar(picker);
        });

        selects.append(monthSelect, yearSelect);

        const nav = document.createElement('div');
        nav.className = 'calendar-nav';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Previous month');
        prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
        prev.addEventListener('mousedown', function(event) {
            event.preventDefault();
            picker.viewDate = new Date(year, month - 1, 1);
            renderCalendar(picker);
        });

        const next = document.createElement('button');
        next.type = 'button';
        next.setAttribute('aria-label', 'Next month');
        next.innerHTML = '<i class="bi bi-chevron-right"></i>';
        next.addEventListener('mousedown', function(event) {
            event.preventDefault();
            picker.viewDate = new Date(year, month + 1, 1);
            renderCalendar(picker);
        });

        nav.append(prev, next);
        head.append(selects, nav);
        picker.calendar.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'calendar-grid';

        weekdays.forEach(function(day) {
            const weekday = document.createElement('div');
            weekday.className = 'calendar-weekday';
            weekday.textContent = day;
            grid.appendChild(weekday);
        });

        for (let i = 0; i < 42; i++) {
            const date = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + i);
            const day = document.createElement('button');
            day.type = 'button';
            day.className = 'calendar-day';
            day.textContent = date.getDate();
            if (date.getMonth() !== month) day.classList.add('is-muted');
            if (sameDay(date, today)) day.classList.add('is-today');
            if (sameDay(date, selectedDate)) day.classList.add('is-selected');
            day.addEventListener('mousedown', function(event) {
                event.preventDefault();
                picker.hidden.value = toIso(date);
                picker.input.value = toDisplay(date);
                closeCalendar();
            });
            grid.appendChild(day);
        }

        picker.calendar.appendChild(grid);

        const foot = document.createElement('div');
        foot.className = 'calendar-foot';

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.textContent = 'Clear';
        clear.addEventListener('mousedown', function(event) {
            event.preventDefault();
            picker.hidden.value = '';
            picker.input.value = '';
            closeCalendar();
        });

        const todayButton = document.createElement('button');
        todayButton.type = 'button';
        todayButton.textContent = 'Today';
        todayButton.addEventListener('mousedown', function(event) {
            event.preventDefault();
            picker.hidden.value = toIso(today);
            picker.input.value = toDisplay(today);
            closeCalendar();
        });

        foot.append(clear, todayButton);
        picker.calendar.appendChild(foot);
    }

    function openCalendar(input) {
        const hidden = document.getElementById(input.dataset.target);
        if (!hidden) return;
        if (activePicker && activePicker.input === input) {
            return;
        }
        if (input.value.trim() !== '') {
            syncTypedDate(input);
        }
        closeCalendar();

        const calendar = document.createElement('div');
        calendar.className = 'analytics-calendar is-open';
        const selected = parseIso(hidden.value);

        const picker = {
            input,
            hidden,
            calendar,
            viewDate: new Date(selected.getFullYear(), selected.getMonth(), 1)
        };

        activePicker = picker;
        input.closest('.date-picker-wrap').appendChild(calendar);
        renderCalendar(picker);
    }

    document.querySelectorAll('.js-date-picker').forEach(function(input) {
        input.addEventListener('click', function() {
            openCalendar(input);
        });
        input.addEventListener('focus', function() {
            openCalendar(input);
        });
        input.addEventListener('change', function() {
            if (input.value.trim() !== '') {
                syncTypedDate(input);
            }
        });
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openCalendar(input);
            }
            if (event.key === 'Escape') {
                closeCalendar();
            }
        });
    });

    const filterForm = document.querySelector('.filter-card');
    if (filterForm) {
        filterForm.addEventListener('submit', function(event) {
            let valid = true;
            document.querySelectorAll('.js-date-picker').forEach(function(input) {
                if (input.value.trim() !== '' && !syncTypedDate(input)) {
                    valid = false;
                }
            });
            if (!valid) {
                event.preventDefault();
            }
        });
    }

    document.addEventListener('click', function(event) {
        if (!activePicker) return;
        if (!activePicker.calendar.contains(event.target) && event.target !== activePicker.input) {
            closeCalendar();
        }
    });
})();
</script>
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
    trendChart.resize(500, 186);
    categoryChart.resize(500, 186);
});
window.addEventListener('afterprint', () => {
    trendChart.resize();
    categoryChart.resize();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
