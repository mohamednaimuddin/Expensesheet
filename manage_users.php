<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
@include 'log_helper.php';

$username_session = $_SESSION['username'];

// Ensure is_active column exists on users table (auto-migration)
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

// Get logged-in admin's company
$admin_company_id = '';
$admin_company_name = '';
$res = $conn->query("SELECT company_id FROM users WHERE username='" . $conn->real_escape_string($username_session) . "' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $admin_company_id = $res->fetch_assoc()['company_id'];
}
if ($admin_company_id !== '') {
    $cn = $conn->query("SELECT company_name FROM companies WHERE id='" . $conn->real_escape_string($admin_company_id) . "' LIMIT 1");
    if ($cn && $cn->num_rows > 0) {
        $admin_company_name = $cn->fetch_assoc()['company_name'];
    }
}

$message = '';
$message_type = '';

// Helper: ensure target user belongs to admin's company and is a 'user' role
function user_belongs_to_company($conn, $id, $company_id) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND company_id=? AND role='user' LIMIT 1");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = trim($_POST['password']  ?? '');
        $number    = trim($_POST['number']    ?? '');

        if ($full_name === '' || $username === '' || $email === '' || $password === '' || $number === '') {
            $message = "All fields are required!"; $message_type = "danger";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $message = "Username or Email already exists!"; $message_type = "danger";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'user';
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, email, number, role, company_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("ssssssi", $full_name, $username, $hashed_password, $email, $number, $role, $admin_company_id);
                if ($stmt->execute()) {
                    if (function_exists('logActivity')) {
                        logActivity($conn, defined('LOG_ADD_USER') ? LOG_ADD_USER : 'add_user', "Added new user: $username ($full_name)");
                    }
                    $message = "User added successfully!"; $message_type = "success";
                } else {
                    $message = "Error: " . $stmt->error; $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    elseif ($action === 'disable' || $action === 'enable') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && user_belongs_to_company($conn, $id, intval($admin_company_id))) {
            $new_status = ($action === 'disable') ? 0 : 1;
            $stmt = $conn->prepare("UPDATE users SET is_active=? WHERE id=?");
            $stmt->bind_param("ii", $new_status, $id);
            if ($stmt->execute()) {
                $message = $action === 'disable' ? "User disabled successfully." : "User enabled successfully.";
                $message_type = "success";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'update_user', ($action === 'disable' ? 'Disabled' : 'Enabled') . " user id=$id");
                }
            } else {
                $message = "Error updating user: " . $stmt->error; $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Invalid user."; $message_type = "danger";
        }
    }
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0 && user_belongs_to_company($conn, $id, intval($admin_company_id))) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='user'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "User deleted successfully."; $message_type = "success";
                if (function_exists('logActivity')) {
                    logActivity($conn, 'delete_user', "Deleted user id=$id");
                }
            } else {
                $message = "Error deleting user: " . $stmt->error; $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Invalid user."; $message_type = "danger";
        }
    }
}

// Fetch users (all, including disabled, so admin can manage them)
$users = [];
if ($admin_company_id !== '') {
    $stmt = $conn->prepare("SELECT id, full_name, username, email, number, is_active FROM users WHERE role='user' AND company_id=? ORDER BY is_active DESC, full_name ASC");
    $stmt->bind_param("i", $admin_company_id);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) { $users[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link rel="stylesheet" href="assets/dashboard_admin.css">
<link rel="stylesheet" href="assets/loader.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<style>
    .mu-wrap { max-width: 1300px; margin: 24px auto; }

    .mu-section-title {
        font-family: 'Poppins', 'Inter', sans-serif;
        font-weight: 700;
        color: var(--ink-900);
        display: flex; align-items: center; gap: 10px;
        margin-bottom: 18px;
    }
    .mu-section-title small { color: var(--ink-500); font-weight: 500; }

    .mu-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-brd);
        border-radius: var(--radius-lg);
        backdrop-filter: blur(18px) saturate(150%);
        -webkit-backdrop-filter: blur(18px) saturate(150%);
        box-shadow: var(--glass-shadow);
        color: var(--ink-900);
        padding: 26px;
        position: relative;
        overflow: hidden;
    }
    .mu-card::before {
        content: "";
        position: absolute; inset: 0;
        background: linear-gradient(180deg, rgba(255,255,255,0.55), rgba(255,255,255,0) 60%);
        pointer-events: none;
    }
    .mu-card h3 {
        font-family: 'Poppins', 'Inter', sans-serif;
        font-weight: 700;
        color: var(--ink-900);
        margin-bottom: 18px;
        display: flex; align-items: center; gap: 8px;
        position: relative;
    }
    .mu-card h3 i { color: var(--blue-600); }

    /* Form fields */
    .mu-card label.form-label {
        color: var(--ink-700);
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 6px;
    }
    .mu-card .form-control,
    .mu-card .form-select {
        background: rgba(255,255,255,0.85);
        color: var(--ink-900);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 10px 14px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .mu-card .form-control::placeholder { color: var(--ink-400); }
    .mu-card .form-control:focus,
    .mu-card .form-select:focus {
        background: #fff;
        border-color: var(--blue-500);
        box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        color: var(--ink-900);
    }

    .btn-add {
        background: linear-gradient(135deg, var(--lime-500), var(--green-600));
        color: #fff !important;
        border: none;
        border-radius: 999px;
        padding: 10px 22px;
        font-weight: 600;
        box-shadow: 0 8px 22px rgba(101,163,13,0.28);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }
    .btn-add:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 12px 28px rgba(101,163,13,0.38); }

    /* Table */
    .mu-table {
        width: 100%;
        color: var(--ink-900);
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .mu-table thead th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--ink-500);
        padding: 10px 14px;
        border: none;
        font-weight: 700;
    }
    .mu-table tbody tr {
        background: rgba(255,255,255,0.85);
        box-shadow: 0 4px 14px rgba(15,23,42,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }
    .mu-table tbody tr:hover {
        transform: translateY(-1px);
        background: #ffffff;
        box-shadow: 0 8px 22px rgba(15,23,42,0.08);
    }
    .mu-table tbody tr.disabled-row {
        background: rgba(241,245,249,0.85);
        opacity: 0.85;
    }
    .mu-table tbody tr.disabled-row td { color: var(--ink-500); }
    .mu-table tbody td {
        padding: 14px;
        vertical-align: middle;
        border: none;
        font-weight: 500;
    }
    .mu-table tbody tr td:first-child { border-top-left-radius: 14px; border-bottom-left-radius: 14px; }
    .mu-table tbody tr td:last-child  { border-top-right-radius: 14px; border-bottom-right-radius: 14px; }

    .badge-active   { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: #fff; padding: 6px 12px; border-radius: 999px; font-weight: 600; font-size: 0.75rem; }
    .badge-disabled { background: rgba(100,116,139,0.18); color: var(--ink-700); padding: 6px 12px; border-radius: 999px; font-weight: 600; font-size: 0.75rem; }

    .actions .btn {
        margin-left: 4px;
        border-radius: 999px;
        padding: 6px 14px;
        font-weight: 600;
        font-size: 0.82rem;
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }
    .actions .btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
    .actions .btn-warning { background: linear-gradient(135deg, #fbbf24, var(--amber-500)); color: #fff; box-shadow: 0 6px 16px rgba(245,158,11,0.28); }
    .actions .btn-success { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: #fff; box-shadow: 0 6px 16px rgba(16,185,129,0.28); }
    .actions .btn-danger  { background: linear-gradient(135deg, #fb7185, var(--rose-500)); color: #fff; box-shadow: 0 6px 16px rgba(244,63,94,0.28); }

    .alert { border-radius: 14px; border: none; box-shadow: 0 6px 18px rgba(15,23,42,0.06); }
    .alert-success { background: rgba(16,185,129,0.12); color: var(--green-600); }
    .alert-danger  { background: rgba(239,68,68,0.10);  color: var(--red-500); }

    .btn-outline-light {
        background: rgba(15,23,42,0.05);
        color: var(--ink-700) !important;
        border: 1px solid rgba(15,23,42,0.10);
        border-radius: 999px;
        font-weight: 600;
        padding: 8px 18px;
    }
    .btn-outline-light:hover { background: rgba(15,23,42,0.10); color: var(--ink-900) !important; }

    /* Delete confirmation modal */
    .modal-danger .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 30px 80px rgba(15,23,42,0.25);
        background: #fff;
    }
    .modal-danger .modal-header {
        background: linear-gradient(135deg, #fb7185, var(--rose-500));
        color: #fff;
        border: none;
        padding: 22px 26px;
        align-items: center;
        gap: 14px;
    }
    .modal-danger .modal-header .danger-icon {
        width: 48px; height: 48px;
        border-radius: 50%;
        background: rgba(255,255,255,0.22);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.6rem;
    }
    .modal-danger .modal-title {
        font-family: 'Poppins','Inter',sans-serif;
        font-weight: 700;
        font-size: 1.2rem;
    }
    .modal-danger .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.85;
    }
    .modal-danger .modal-body {
        padding: 24px 26px;
        color: var(--ink-700);
        font-size: 0.97rem;
        line-height: 1.55;
    }
    .modal-danger .modal-body .target-name {
        display: inline-block;
        background: rgba(244,63,94,0.10);
        color: var(--rose-500);
        padding: 4px 12px;
        border-radius: 999px;
        font-weight: 700;
    }
    .modal-danger .modal-body ul {
        margin: 14px 0 18px;
        padding-left: 18px;
    }
    .modal-danger .modal-body ul li { margin-bottom: 4px; }
    .modal-danger .warn-banner {
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.20);
        color: var(--red-500);
        border-radius: 12px;
        padding: 12px 14px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex; align-items: center; gap: 8px;
    }
    .modal-danger .modal-footer {
        border: none;
        padding: 18px 26px 22px;
        gap: 8px;
    }
    .modal-danger .btn-cancel {
        background: rgba(15,23,42,0.05);
        color: var(--ink-700);
        border: 1px solid rgba(15,23,42,0.10);
        border-radius: 999px;
        padding: 9px 22px;
        font-weight: 600;
    }
    .modal-danger .btn-cancel:hover { background: rgba(15,23,42,0.10); color: var(--ink-900); }
    .modal-danger .btn-confirm-delete {
        background: linear-gradient(135deg, #fb7185, var(--rose-500));
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 9px 22px;
        font-weight: 700;
        box-shadow: 0 8px 22px rgba(244,63,94,0.35);
    }
    .modal-danger .btn-confirm-delete:hover { filter: brightness(1.05); color: #fff; }
    .modal-danger .btn-confirm-delete:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
</style>
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
            <div class="dots-loader"><span></span><span></span><span></span></div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg glass-nav">
  <div class="container-fluid app-container">
    <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    <div class="navbar-nav ms-auto d-flex align-items-center flex-row">
      <a href="dashboard_admin.php" class="btn btn-outline-light me-2"><i class="bi bi-house"></i> Dashboard</a>
      <a href="logout.php" class="btn btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid app-container mu-wrap">

    <h2 class="mu-section-title"><i class="bi bi-people-fill"></i> Manage Users
        <small>— <?php echo htmlspecialchars($admin_company_name ?: ('Company #' . $admin_company_id)); ?></small>
    </h2>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Add User -->
    <div class="mu-card mb-4">
        <h3><i class="bi bi-person-plus-fill"></i> Add New User</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="number" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-add w-100"><i class="bi bi-plus-circle"></i> Add User</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <div class="mu-card">
        <h3><i class="bi bi-list-ul"></i> Users in <?php echo htmlspecialchars($admin_company_name ?: ('Company #' . $admin_company_id)); ?></h3>
        <div class="table-responsive">
            <table class="mu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($users) === 0): ?>
                    <tr><td colspan="7" class="text-center">No users in your company yet.</td></tr>
                <?php else: $i = 1; foreach ($users as $u): ?>
                    <tr class="<?php echo $u['is_active'] ? '' : 'disabled-row'; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td>@<?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['number']); ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end actions">
                            <?php if ($u['is_active']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Disable this user? They will be hidden from the dashboard but their data is kept.');">
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-slash-circle"></i> Disable</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="enable">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Enable</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline js-delete-form" data-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>" data-fullname="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="button" class="btn btn-danger btn-sm js-delete-btn"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-danger" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <span class="danger-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <h5 class="modal-title" id="deleteUserModalLabel">Delete User Permanently?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">You are about to permanently delete <span class="target-name" id="deleteTargetName">user</span>.</p>
        <p class="mb-2">This will remove:</p>
        <ul>
          <li>The user account and login credentials</li>
          <li>All expense records, advances, and reports linked to this user</li>
          <li>All associated history and activity data</li>
        </ul>
        <div class="warn-banner">
          <i class="bi bi-shield-exclamation"></i>
          This action <u>cannot be undone</u> and the data <u>cannot be retrieved</u>.
        </div>
        <div class="mt-3">
          <label class="form-label" style="font-weight:600; color: var(--ink-700);">Type <code>DELETE</code> to confirm:</label>
          <input type="text" class="form-control" id="deleteConfirmInput" placeholder="Type DELETE here" autocomplete="off">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-confirm-delete" id="confirmDeleteBtn" disabled>
          <i class="bi bi-trash"></i> Yes, Delete Permanently
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener('load', function() { document.getElementById('pageLoader').classList.add('hidden'); });
window.addEventListener('pageshow', function() { document.getElementById('pageLoader').classList.add('hidden'); });
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript"])').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
            if (el.getAttribute('target') === '_blank') return;
            document.getElementById('pageLoader').classList.remove('hidden');
        });
    });

    // Delete confirmation modal logic
    var deleteModalEl = document.getElementById('deleteUserModal');
    var deleteModal = new bootstrap.Modal(deleteModalEl);
    var confirmInput = document.getElementById('deleteConfirmInput');
    var confirmBtn = document.getElementById('confirmDeleteBtn');
    var targetNameEl = document.getElementById('deleteTargetName');
    var pendingForm = null;

    document.querySelectorAll('.js-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = btn.closest('.js-delete-form');
            pendingForm = form;
            var name = form.getAttribute('data-fullname') || form.getAttribute('data-username') || 'this user';
            targetNameEl.textContent = name;
            confirmInput.value = '';
            confirmBtn.disabled = true;
            deleteModal.show();
            setTimeout(function() { confirmInput.focus(); }, 250);
        });
    });

    confirmInput.addEventListener('input', function() {
        confirmBtn.disabled = (confirmInput.value.trim().toUpperCase() !== 'DELETE');
    });

    confirmBtn.addEventListener('click', function() {
        if (!pendingForm) return;
        if (confirmInput.value.trim().toUpperCase() !== 'DELETE') return;
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        document.getElementById('pageLoader').classList.remove('hidden');
        pendingForm.submit();
    });

    deleteModalEl.addEventListener('hidden.bs.modal', function() {
        pendingForm = null;
        confirmInput.value = '';
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="bi bi-trash"></i> Yes, Delete Permanently';
    });
});
</script>
</body>
</html>
