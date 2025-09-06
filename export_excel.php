<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

// Get filter values from GET
$username = $_GET['username'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

// Convert dates to correct format
if ($from_date) $from_date = date('Y-m-d', strtotime($from_date));
if ($to_date) $to_date = date('Y-m-d', strtotime($to_date));

// Function to fetch filtered expenses
function get_expenses($conn, $table, $username, $from_date, $to_date, $region_filter) {
    $sql = "SELECT * FROM $table WHERE username = ?";
    $params = [$username];
    $types = "s";

    if ($from_date && $to_date) {
        $sql .= " AND `date` BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
        $types .= "ss";
    }

    if ($region_filter && $region_filter !== 'All') {
        $sql .= " AND region = ?";
        $params[] = $region_filter;
        $types .= "s";
    }

    $sql .= " ORDER BY `date` DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch all filtered expenses
$fuel_expenses = get_expenses($conn, 'fuel_expense', $username, $from_date, $to_date, $region_filter);
$food_expenses = get_expenses($conn, 'food_expense', $username, $from_date, $to_date, $region_filter);
$room_expenses = get_expenses($conn, 'room_expense', $username, $from_date, $to_date, $region_filter);
$other_expenses = get_expenses($conn, 'other_expense', $username, $from_date, $to_date, $region_filter);

// Set headers for Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=expense_report_{$username}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>
<tr>
<th>SI. No</th>
<th>Date</th>
<th>Division</th>
<th>Type</th>
<th>Company Name</th>
<th>Location</th> 
<th>Store</th>
<th>Description</th>
<th>Amount</th>
<th>Remark</th>
</tr>";

$si = 1;
$expenses_list = [
    'Fuel' => $fuel_expenses,
    'Food' => $food_expenses,
    'Room' => $room_expenses,
    'Other' => $other_expenses
];

// Loop through expenses exactly like user_report.php
foreach ($expenses_list as $type => $expenses) {
    if($expenses && mysqli_num_rows($expenses) > 0) {
        while($row = mysqli_fetch_assoc($expenses)) {
            $excel_date = date('Y-m-d', strtotime($row['date'])); // Correct date format
            $remark = $row['remark'] ?? ''; // Avoid undefined key warning
            echo "<tr>
            <td>{$si}</td>
            <td>{$excel_date}</td>
            <td>{$row['division']}</td>
            <td>{$type}</td>
            <td>{$row['company']}</td>
            <td>{$row['location']}</td>
            <td>{$row['store']}</td>
            <td>{$row['description']}</td>
            <td>{$row['amount']}</td>
            <td>{$remark}</td>
            </tr>";
            $si++;
        }
    }
}

echo "</table>";
exit();
?>
