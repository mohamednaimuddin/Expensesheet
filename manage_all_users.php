<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
$message = '';
$message_type = '';

// Fetch all companies for dropdown
$companies = $conn->query("SELECT id, company_name, company_code FROM companies ORDER BY company_name ASC");
$companies_array = [];
while ($row = $companies->fetch_assoc()) {
    $companies_array[] = $row;
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $number = trim($_POST['number'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $company_id = intval($_POST['company_id'] ?? 0);

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($company_id)) {
            $message = "All required fields must be filled!";
            $message_type = "danger";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "Username or Email already exists!";
                $message_type = "danger";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, email, number, role, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $full_name, $username, $hashed_password, $email, $number, $role, $company_id);
                
                if ($stmt->execute()) {
                    $message = "User added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding user: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    if ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $number = trim($_POST['number'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $company_id = intval($_POST['company_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');

        if (empty($full_name) || empty($email) || empty($company_id)) {
            $message = "Required fields cannot be empty!";
            $message_type = "danger";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $id);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "Email already exists for another user!";
                $message_type = "danger";
            } else {
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, number = ?, role = ?, company_id = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("ssssisi", $full_name, $email, $number, $role, $company_id, $hashed_password, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, number = ?, role = ?, company_id = ? WHERE id = ?");
                    $stmt->bind_param("ssssii", $full_name, $email, $number, $role, $company_id, $id);
                }
                
                if ($stmt->execute()) {
                    $message = "User updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating user: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'superadmin'");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Cannot delete super admin account!";
                $message_type = "danger";
            }
        } else {
            $message = "Error deleting user: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Filter parameters
$filter_company = isset($_GET['company']) ? intval($_GET['company']) : 0;
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT u.*, c.company_name, c.company_code 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE u.role != 'superadmin'";

if ($filter_company > 0) {
    $sql .= " AND u.company_id = $filter_company";
}
if (!empty($filter_role)) {
    $sql .= " AND u.role = '" . $conn->real_escape_string($filter_role) . "'";
}
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (u.full_name LIKE '%$search_escaped%' OR u.username LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%')";
}

$sql .= " ORDER BY c.company_name, u.role DESC, u.full_name ASC";
$users = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage All Users | Super Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<link rel="stylesheet" href="assets/dashboard_superadmin.css">
<link rel="stylesheet" href="assets/loader.css">
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-container">
        <div class="brand-loader">
            <img src="assets/visionnew.png" alt="Loading...">
            <div class="dots-loader">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-superadmin">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard_superadmin.php">
            <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
            <span>Vision Angles Security EST.</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <a href="dashboard_superadmin.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2><i class="bi bi-people-fill"></i> Manage All Users</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> Add User
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select name="company" class="form-select">
                        <option value="0">All Companies</option>
                        <?php foreach ($companies_array as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($filter_company == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo ($filter_role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo ($filter_role == 'user') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, username, or email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary me-2"><i class="bi bi-search"></i> Search</button>
                    <a href="manage_all_users.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Company</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php $i = 1; while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['number'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-primary">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($user['company_name'] ?? 'Unassigned'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="user_report.php?username=<?php echo urlencode($user['username']); ?>" class="btn btn-sm btn-info" title="View Report">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn btn-sm btn-warning edit-btn" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-number="<?php echo htmlspecialchars($user['number'] ?? ''); ?>"
                                            data-role="<?php echo $user['role']; ?>"
                                            data-company="<?php echo $user['company_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="number" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company *</label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies_array as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="number" id="edit_number" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company *</label>
                            <select name="company_id" id="edit_company" class="form-select" required>
                                <option value="">Select Company</option>
                                <?php foreach ($companies_array as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone! All expense data for this user will remain.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener('load', function() {
    document.getElementById('pageLoader').classList.add('hidden');
});

// Edit button click
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_fullname').value = this.dataset.fullname;
        document.getElementById('edit_username').value = this.dataset.username;
        document.getElementById('edit_email').value = this.dataset.email;
        document.getElementById('edit_number').value = this.dataset.number;
        document.getElementById('edit_role').value = this.dataset.role;
        document.getElementById('edit_company').value = this.dataset.company;
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
});

// Delete button click
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_id').value = this.dataset.id;
        document.getElementById('delete_name').textContent = this.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    });
});
</script>
</body>
</html>
