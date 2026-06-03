<?php
include 'config.php';

// Show all indexes for the invoices table
$res = $conn->query("SHOW INDEX FROM invoices");
echo "<pre>";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
