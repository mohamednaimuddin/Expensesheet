<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    exit("Unauthorized");
}

include 'config.php';

// Get params
$username = $_GET['username'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$region_filter = $_GET['region'] ?? 'All';

if (!$username || !$from_date || !$to_date) {
    die("Missing parameters");
}

// Find current max invoice
$max_query = $conn->query("SELECT MAX(CAST(invoice_no AS UNSIGNED)) AS max_inv FROM invoices");
$row = $max_query->fetch_assoc();
$next_invoice = ($row['max_inv'] !== null) ? $row['max_inv'] + 1 : 1;

// Insert new invoice record

$stmt = $conn->prepare("
    INSERT INTO invoices (username, from_date, to_date, region, invoice_no) 
    VALUES (?, ?, ?, ?, ?)
");
if (!$stmt) {
    http_response_code(500);
    echo "ERROR: Prepare failed: ".$conn->error;
    exit;
}
$stmt->bind_param("ssssi", $username, $from_date, $to_date, $region_filter, $next_invoice);
if (!$stmt->execute()) {
    http_response_code(500);
    echo "ERROR: Execute failed: ".$stmt->error;
    exit;
}
// Return new invoice number (5-digit padded)
echo str_pad($next_invoice, 5, "0", STR_PAD_LEFT);
