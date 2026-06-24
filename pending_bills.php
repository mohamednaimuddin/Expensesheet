<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!empty($_POST['update_bill']) && isset($_POST['id'], $_POST['category'])) {
    $id = (int)$_POST['id'];
    $category = $_POST['category'];
    $bill_value = $_POST['update_bill'] === 'yes' ? 'Yes' : 'No';

    $table_map = [
        'Fuel'        => 'fuel_expense',
        'Room'        => 'room_expense',
        'Other'       => 'other_expense',
        'Tools'       => 'tools_expense',
        'Labour'      => 'labour_expense',
        'Accessories' => 'accessories_expense',
        'Tv'          => 'tv_expense',
        'Vehicle'     => 'vehicle_expense',
        'Taxi'        => 'taxi_expense'
    ];

    if (isset($table_map[$category])) {
        $stmt = $conn->prepare("UPDATE {$table_map[$category]} SET bill=? WHERE id=?");
        $stmt->bind_param("si", $bill_value, $id);
        $stmt->execute();
        echo 'success';
        exit;
    }
}

$month_year_filter = $_GET['month_year'] ?? date('Y-m');
$username_filter = trim($_GET['username'] ?? '');
$region_filter = $_GET['region'] ?? 'All';
$per_page = isset($_GET['per_page']) ? max(10, min(200, (int)$_GET['per_page'])) : 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$filter_sql = '';
if ($month_year_filter) {
    $parts = explode('-', $month_year_filter);
    if (count($parts) === 2) {
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $filter_sql .= " AND YEAR(date) = $year AND MONTH(date) = $month";
    }
}

if ($username_filter !== '') {
    $username_safe = $conn->real_escape_string($username_filter);
    $filter_sql .= " AND username='$username_safe'";
}

if ($region_filter !== '' && $region_filter !== 'All') {
    $region_safe = $conn->real_escape_string($region_filter);
    $filter_sql .= " AND region='$region_safe'";
}

$usernames = [];
foreach (['fuel_expense','room_expense','other_expense','tools_expense','labour_expense','accessories_expense','tv_expense','vehicle_expense','taxi_expense'] as $table) {
    $res = $conn->query("SELECT DISTINCT username FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $usernames[$row['username']] = $row['username'];
        }
    }
}
ksort($usernames);

$query = "
    SELECT 'Fuel' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM fuel_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Room' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM room_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Other' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM other_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Tools' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM tools_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Labour' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM labour_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Accessories' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM accessories_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Tv' AS expense_category, id, date, division, company, store, location, description, amount, bill
    FROM tv_expense
    WHERE (bill IS NULL OR TRIM(bill)='' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Vehicle' AS expense_category, ve.id, ve.date, '' AS division, '' AS company, '' AS store, '' AS location,
    CONCAT(IFNULL(v.model,''), ' - ', IFNULL(v.number_plate,''), ' - ', ve.service, IF(ve.description IS NOT NULL, CONCAT(' - ', ve.description), '')) AS description,
    ve.amount, ve.bill
    FROM vehicle_expense ve
    LEFT JOIN vehicle v ON ve.vehicle_id = v.id
    WHERE (ve.bill IS NULL OR TRIM(ve.bill) = '' OR LOWER(ve.bill) IN ('no','pending','n','0')) $filter_sql

    UNION ALL

    SELECT 'Taxi' AS expense_category, id, date, division COLLATE utf8mb4_unicode_ci AS division, company COLLATE utf8mb4_unicode_ci AS company, store COLLATE utf8mb4_unicode_ci AS store, '' AS location,
    CONCAT(from_location, ' to ', to_location) COLLATE utf8mb4_unicode_ci AS description,
    amount, bill COLLATE utf8mb4_unicode_ci AS bill
    FROM taxi_expense
    WHERE (bill IS NULL OR TRIM(bill) = '' OR LOWER(bill) IN ('no','pending','n','0')) $filter_sql

    ORDER BY date ASC
";

$result = $conn->query($query);
if (!$result) die("Query failed: " . $conn->error);

$pending_rows = [];
$total_amount = 0.0;
while ($row = $result->fetch_assoc()) {
    $pending_rows[] = $row;
    $total_amount += (float)$row['amount'];
}

$total_records = count($pending_rows);
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$paged_rows = array_slice($pending_rows, $offset, $per_page);
$page_amount = array_sum(array_map(function($row) {
    return (float)$row['amount'];
}, $paged_rows));

function build_pending_query($params = []) {
    $current = $_GET;
    unset($current['page']);
    $merged = array_merge($current, $params);
    return http_build_query($merged);
}

$selected_month_label = date('F Y', strtotime($month_year_filter . '-01'));
$back_url = $_SESSION['role'] === 'superadmin' ? 'dashboard_superadmin.php' : 'dashboard_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Bills | VisionAngles</title>
<link rel="icon" type="image/png" href="assets/vision.ico">
<link href="assets/vendor/bootstrap-5.0.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/user_report.css" rel="stylesheet">
<style>
.pending-row td { background: rgba(254,243,199,0.65) !important; }
.pending-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(245,158,11,0.13);
    border: 1px solid rgba(245,158,11,0.30);
    color: #92400e;
    font-weight: 800;
    white-space: nowrap;
}
.bill-status-cell { min-width: 84px; }
.confirm-bill-modal .modal-content {
    border: 1px solid var(--glass-brd);
    border-radius: 18px;
    box-shadow: 0 24px 64px rgba(15,23,42,0.18);
}
.confirm-bill-modal .modal-header,
.confirm-bill-modal .modal-footer {
    border-color: rgba(15,23,42,0.08);
}
.confirm-bill-modal .modal-title {
    color: var(--ink-900);
    font-weight: 800;
}
.confirm-bill-modal .modal-footer {
    gap: 8px;
}
@page {
    size: A4 landscape;
    margin: 10mm 12mm;
}
@media print {
    html, body {
        width: 100%;
        overflow: visible !important;
    }
    body {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 10px !important;
        background: #fff !important;
    }
    .report-page-shell,
    .report-header,
    .print-header,
    .table-card,
    .report-footer {
        width: 100% !important;
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .report-header {
        margin-bottom: 10px !important;
        padding-bottom: 8px !important;
    }
    .report-title-wrap {
        justify-content: center !important;
        width: 100% !important;
    }
    .report-header-meta {
        display: none !important;
    }
    .report-glass-page .report-header h2 {
        width: 100% !important;
        text-align: center !important;
        font-size: 20px !important;
    }
    .print-header {
        font-size: 10.5px !important;
        margin-bottom: 8px !important;
    }
    .table-card,
    .table-responsive {
        overflow: visible !important;
    }
    .table-card table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }
    .table-card thead th,
    .table-card tbody td,
    .table-card tfoot td {
        padding: 4px 4px !important;
        font-size: 8.5px !important;
        line-height: 1.2 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }
    .table-card thead th:nth-child(1),
    .table-card tbody td:nth-child(1) { width: 5% !important; }
    .table-card thead th:nth-child(2),
    .table-card tbody td:nth-child(2) { width: 8% !important; }
    .table-card thead th:nth-child(3),
    .table-card tbody td:nth-child(3) { width: 8% !important; }
    .table-card thead th:nth-child(4),
    .table-card tbody td:nth-child(4) { width: 11% !important; }
    .table-card thead th:nth-child(5),
    .table-card tbody td:nth-child(5) { width: 13% !important; }
    .table-card thead th:nth-child(6),
    .table-card tbody td:nth-child(6) { width: 10% !important; }
    .table-card thead th:nth-child(7),
    .table-card tbody td:nth-child(7) { width: 10% !important; }
    .table-card thead th:nth-child(8),
    .table-card tbody td:nth-child(8) { width: 25% !important; }
    .table-card thead th:nth-child(9),
    .table-card tbody td:nth-child(9) { width: 7% !important; }
    .table-card thead th:nth-child(10),
    .table-card tbody td:nth-child(10) { width: 3% !important; }
    .report-footer {
        margin-top: 22px !important;
        font-size: 10px !important;
    }
}
</style>
</head>
<body class="report-glass-page">

<div class="report-page-shell">
    <div class="report-header">
        <div class="report-title-wrap">
            <img src="assets/visionlogo.jpg" alt="Company Logo">
            <h2><i class="bi bi-receipt-cutoff me-2"></i>No Bill Payment Slip</h2>
        </div>
        <div class="report-header-meta">
            <span class="pending-chip"><i class="bi bi-hourglass-split"></i><?= count($pending_rows) ?> Pending</span>
        </div>
    </div>

    <form method="get" id="filterForm" class="report-toolbar report-toolbar-actions-left mb-3">
        <div class="toolbar-actions">
            <button type="button" class="btn-glass btn-glass-danger" onclick="window.location.href='<?= htmlspecialchars($back_url) ?>'"><i class="bi bi-house"></i> Home</button>
            <button type="button" class="btn-glass btn-glass-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="toolbar-divider d-none d-md-block"></div>
        <div class="toolbar-filter-group">
            <div class="toolbar-field">
                <label for="month_year" class="form-label">Month & Year</label>
                <input type="month" id="month_year" name="month_year" class="form-control" value="<?= htmlspecialchars($month_year_filter ?: date('Y-m')) ?>">
            </div>
            <div class="toolbar-field">
                <label for="region" class="form-label">Region</label>
                <select class="form-select" name="region" id="region">
                    <?php foreach (['All','Dammam','Riyadh','Jeddah','Other'] as $region): ?>
                    <option value="<?= htmlspecialchars($region) ?>" <?= ($region_filter === $region) ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-field">
                <label class="form-label" for="username">Username</label>
                <select class="form-select" name="username" id="username">
                    <option value="">All Users</option>
                    <?php foreach ($usernames as $uname): ?>
                    <option value="<?= htmlspecialchars($uname) ?>" <?= ($username_filter === $uname) ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-field">
                <label class="form-label" for="per_page">Rows</label>
                <select class="form-select" name="per_page" id="per_page">
                    <?php foreach ([10, 25, 50, 100, 200] as $option): ?>
                    <option value="<?= $option ?>" <?= ($per_page === $option) ? 'selected' : '' ?>><?= $option ?> per page</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-search-actions">
                <button class="btn-glass btn-glass-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                <button type="button" class="btn-glass btn-glass-secondary" onclick="window.location.href='pending_bills.php'"><i class="bi bi-x-circle"></i> Clear</button>
            </div>
        </div>
    </form>

    <div class="info-strip print-header">
        <div class="d-flex flex-wrap gap-2">
            <span class="chip"><i class="bi bi-calendar-event"></i> Month: <?= htmlspecialchars($selected_month_label) ?></span>
            <span class="chip"><i class="bi bi-person"></i> User: <?= $username_filter ? htmlspecialchars($username_filter) : 'All Users' ?></span>
            <span class="chip"><i class="bi bi-geo-alt"></i> Region: <?= htmlspecialchars($region_filter) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="chip"><i class="bi bi-list-check"></i> Showing: <?= count($paged_rows) ?> of <?= $total_records ?></span>
            <span class="chip"><i class="bi bi-file-earmark-text"></i> Page: <?= $page ?> of <?= $total_pages ?></span>
            <span class="chip"><i class="bi bi-cash-stack"></i> Total: SAR <?= number_format($total_amount, 2) ?></span>
        </div>
    </div>

    <div class="print-section table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>SI No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Division</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Store</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Bill</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; foreach ($paged_rows as $row): ?>
                    <?php $is_pending = empty($row['bill']) || strtolower($row['bill']) === 'no'; ?>
                    <tr class="<?= $is_pending ? 'pending-row' : '' ?>">
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['expense_category']) ?></td>
                        <td><?= htmlspecialchars($row['division'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['company'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['location'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['store'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td>SAR <?= number_format((float)$row['amount'], 2) ?></td>
                        <td class="bill-status-cell">
                            <?php if ($is_pending): ?>
                            <button class="btn-glass btn-glass-success action-btn" style="padding:3px 10px; min-height:28px; font-size:0.75rem;" data-id="<?= $row['id'] ?>" data-category="<?= htmlspecialchars($row['expense_category']) ?>" data-value="yes">Yes</button>
                            <?php else: ?>
                            <?= htmlspecialchars($row['bill']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($paged_rows)): ?>
                    <tr><td colspan="10" class="text-center">No pending bills found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" class="text-end fw-bold">Page Total:</td>
                        <td colspan="2">SAR <?= number_format($page_amount, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="8" class="text-end fw-bold">Total Spend:</td>
                        <td colspan="2">SAR <?= number_format($total_amount, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Pending bills pages" class="mt-3 no-print">
        <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= build_pending_query(['page' => 1]) ?>">First</a>
            </li>
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= build_pending_query(['page' => max(1, $page - 1)]) ?>">&laquo;</a>
            </li>
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            for ($p = $start_page; $p <= $end_page; $p++):
            ?>
            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                <a class="page-link" href="?<?= build_pending_query(['page' => $p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if ($end_page < $total_pages) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= build_pending_query(['page' => min($total_pages, $page + 1)]) ?>">&raquo;</a>
            </li>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= build_pending_query(['page' => $total_pages]) ?>">Last</a>
            </li>
        </ul>
        <p class="text-center text-muted small mb-0">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> pending bills)</p>
    </nav>
    <?php endif; ?>

    <div class="report-footer">
        <div>Prepared By:</div>
        <div>Verified By:</div>
        <div>Approved By:</div>
    </div>
</div>

<div class="modal fade confirm-bill-modal" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirm Update</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">Are you sure you want to mark this bill as <strong>Yes</strong>?</div>
      <div class="modal-footer">
        <button type="button" class="btn-glass btn-glass-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
        <button type="button" class="btn-glass btn-glass-success" id="confirmYesBtn"><i class="bi bi-check-circle"></i> Yes, Update</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap-5.0.2/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let pendingUpdate = null;
    const modalElement = document.getElementById('confirmModal');
    const confirmModal = modalElement ? new bootstrap.Modal(modalElement) : null;

    document.querySelectorAll('.action-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            pendingUpdate = {
                btn: btn,
                id: btn.dataset.id,
                category: btn.dataset.category,
                updateBill: btn.dataset.value
            };
            if (confirmModal) confirmModal.show();
        });
    });

    const confirmBtn = document.getElementById('confirmYesBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!pendingUpdate) return;
            updateBill(pendingUpdate.btn, pendingUpdate.id, pendingUpdate.category, pendingUpdate.updateBill);
            if (confirmModal) confirmModal.hide();
            pendingUpdate = null;
        });
    }

    function updateBill(btn, id, category, updateBillValue) {
        const body = new URLSearchParams();
        body.set('id', id);
        body.set('category', category);
        body.set('update_bill', updateBillValue);

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        })
        .then(function(response) { return response.text(); })
        .then(function(text) {
            if (text.trim() === 'success') {
                btn.closest('td').textContent = updateBillValue === 'yes' ? 'Yes' : 'No';
            } else {
                alert('Update failed');
            }
        })
        .catch(function() {
            alert('Update failed');
        });
    }
});
</script>
</body>
</html>
