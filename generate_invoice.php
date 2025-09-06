<?php
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
$max_query = $conn->query("SELECT MAX(invoice_no) AS max_inv FROM invoices");
$row = $max_query->fetch_assoc();
$next_invoice = ($row['max_inv'] !== null) ? $row['max_inv'] + 1 : 1;

// Insert new invoice record
$stmt = $conn->prepare("
    INSERT INTO invoices (username, from_date, to_date, region, invoice_no) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssi", $username, $from_date, $to_date, $region_filter, $next_invoice);
$stmt->execute();

// Return new invoice number (5-digit padded)
echo str_pad($next_invoice, 5, "0", STR_PAD_LEFT);
