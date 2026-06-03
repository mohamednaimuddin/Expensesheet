<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';
include 'log_helper.php';

$username = $_GET['username'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';
$type_filter = $_GET['type'] ?? 'All';

if ($from_date) $from_date = date('Y-m-d', strtotime($from_date));
if ($to_date) $to_date = date('Y-m-d', strtotime($to_date));

$expense_tables = [
    'Fuel' => 'fuel_expense',
    'Food' => 'food_expense',
    'Room' => 'room_expense',
    'Other' => 'other_expense',
    'Tools' => 'tools_expense',
    'Labour' => 'labour_expense',
    'Accessories' => 'accessories_expense',
    'TV' => 'tv_expense',
    'Vehicle' => 'vehicle_expense',
    'Taxi' => 'taxi_expense'
];

if ($username === '' || ($type_filter !== 'All' && !isset($expense_tables[$type_filter]))) {
    http_response_code(400);
    exit('Invalid export request');
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_export_expenses($conn, $type, $table, $username, $from_date, $to_date, $region_filter) {
    if ($table === 'vehicle_expense') {
        $sql = "SELECT ve.id, ve.username,
                       CONCAT(IFNULL(v.model,''), ' - ', IFNULL(v.number_plate,''), ' - ', ve.service, IF(ve.description IS NOT NULL AND ve.description != '', CONCAT(' - ', ve.description), '')) AS description,
                       ve.amount, ve.date, ve.service, ve.bill,
                       '' as division, '' as company, '' as location, '' as store, '' as region, '' as remark
                FROM vehicle_expense ve
                LEFT JOIN vehicle v ON ve.vehicle_id = v.id
                WHERE ve.username=? AND ve.submitted=1";
    } elseif ($table === 'taxi_expense') {
        $sql = "SELECT id, username, CONCAT(from_location, ' -> ', to_location) AS description,
                       amount, date, division, company, '' as location, store, region, bill,
                       '' as remark
                FROM taxi_expense
                WHERE username=? AND submitted=1";
    } else {
        $sql = "SELECT id, username, description, amount, date, division, company, location, store, region, bill,
                       '' as remark
                FROM $table
                WHERE username=? AND submitted=1";
    }

    $types = "s";
    $params = [$username];

    if ($from_date && $to_date) {
        $sql .= " AND `date` BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $from_date;
        $params[] = $to_date;
    }

    if ($region_filter !== 'All') {
        $sql .= " AND region=?";
        $types .= "s";
        $params[] = $region_filter;
    }

    $sql .= " ORDER BY `date` ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        exit("Export prepare failed for " . h($type) . ": " . h($conn->error));
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$all_expenses = [];
foreach ($expense_tables as $type => $table) {
    if ($type_filter !== 'All' && $type !== $type_filter) continue;

    $result = get_export_expenses($conn, $type, $table, $username, $from_date, $to_date, $region_filter);
    while ($row = $result->fetch_assoc()) {
        $row['type'] = $type;
        $all_expenses[] = $row;
    }
}

usort($all_expenses, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

logActivity($conn, LOG_EXPORT, "Exported expense report for user: $username");

$safe_username = preg_replace('/[^A-Za-z0-9_-]+/', '_', $username);
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=expense_report_{$safe_username}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
echo "<table border='1'>";
echo "<tr>
<th>SI. No</th>
<th>Date</th>
<th>Type</th>
<th>Division</th>
<th>Company</th>
<th>Location</th>
<th>Store</th>
<th>Description</th>
<th>Amount</th>
<th>Remark</th>
</tr>";

$si = 1;
foreach ($all_expenses as $row) {
    $excel_date = $row['date'] ? date('d M Y', strtotime($row['date'])) : '';
    echo "<tr>
<td>" . $si . "</td>
<td>" . h($excel_date) . "</td>
<td>" . h($row['type']) . "</td>
<td>" . h($row['division'] ?? '') . "</td>
<td>" . h($row['company'] ?? '') . "</td>
<td>" . h($row['location'] ?? '') . "</td>
<td>" . h($row['store'] ?? '') . "</td>
<td>" . h($row['description'] ?? '') . "</td>
<td>" . h(number_format((float) ($row['amount'] ?? 0), 2, '.', '')) . "</td>
<td>" . h($row['remark'] ?? '') . "</td>
</tr>";
    $si++;
}

if ($si === 1) {
    echo "<tr><td colspan='10'>No expenses found for this user.</td></tr>";
}

echo "</table>";
exit();
?>
