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

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $number = trim($_POST['number'] ?? '');
        $company_id = intval($_POST['company_id'] ?? 0);

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($company_id)) {
            $message = "All required fields must be filled!";
            $message_type = "danger";
        } else {
            // Check if username or email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "Username or Email already exists!";
                $message_type = "danger";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'admin';
                
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, email, number, role, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $full_name, $username, $hashed_password, $email, $number, $role, $company_id);
                
                if ($stmt->execute()) {
                    $message = "Admin added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding admin: " . $stmt->error;
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
        $company_id = intval($_POST['company_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');

        if (empty($full_name) || empty($email) || empty($company_id)) {
            $message = "Required fields cannot be empty!";
            $message_type = "danger";
        } else {
            // Check if email already exists for another user
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
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, number = ?, company_id = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("sssisi", $full_name, $email, $number, $company_id, $hashed_password, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, number = ?, company_id = ? WHERE id = ?");
                    $stmt->bind_param("sssii", $full_name, $email, $number, $company_id, $id);
                }
                
                if ($stmt->execute()) {
                    $message = "Admin updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating admin: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Admin deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting admin: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Filter by company
$filter_company = isset($_GET['company']) ? intval($_GET['company']) : 0;

// Fetch all admins
$sql = "SELECT u.*, c.company_name, c.company_code 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE u.role = 'admin'";
if ($filter_company > 0) {
    $sql .= " AND u.company_id = $filter_company";
}
$sql .= " ORDER BY c.company_name, u.full_name ASC";
$admins = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Admins | Super Admin</title>
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
        <h2><i class="bi bi-person-badge"></i> Manage Admins</h2>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <select name="company" class="form-select" style="min-width: 200px;">
                    <option value="0">All Companies</option>
                    <?php foreach ($companies_array as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filter_company == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary"><i class="bi bi-filter"></i> Filter</button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="bi bi-plus-lg"></i> Add Admin
            </button>
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
                            <th>Company</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($admins && $admins->num_rows > 0): ?>
                            <?php $i = 1; while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></td>
                                <td>@<?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['number'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($admin['company_name'] ?? 'Unassigned'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" 
                                            data-id="<?php echo $admin['id']; ?>"
                                            data-fullname="<?php echo htmlspecialchars($admin['full_name']); ?>"
                                            data-username="<?php echo htmlspecialchars($admin['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($admin['email']); ?>"
                                            data-number="<?php echo htmlspecialchars($admin['number'] ?? ''); ?>"
                                            data-company="<?php echo $admin['company_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="<?php echo $admin['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($admin['full_name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No admins found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Admin</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Company *</label>
                        <select name="company_id" class="form-select" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies_array as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['company_name']); ?> (<?php echo $c['company_code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Admin</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Company *</label>
                        <select name="company_id" id="edit_company" class="form-select" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies_array as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['company_name']); ?> (<?php echo $c['company_code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Update Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete admin <strong id="delete_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone!</small></p>
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
        document.getElementById('edit_company').value = this.dataset.company;
        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    });
});

// Delete button click
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_id').value = this.dataset.id;
        document.getElementById('delete_name').textContent = this.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteAdminModal')).show();
    });
});
</script>
</body>
</html>
