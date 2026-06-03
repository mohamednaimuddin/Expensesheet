<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
$message = '';
$message_type = '';

// Handle Add Company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $company_name = trim($_POST['company_name'] ?? '');
        $company_code = trim($_POST['company_code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($company_name) || empty($company_code)) {
            $message = "Company name and code are required!";
            $message_type = "danger";
        } else {
            // Check if company code already exists
            $check = $conn->prepare("SELECT id FROM companies WHERE company_code = ?");
            $check->bind_param("s", $company_code);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "Company code already exists!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO companies (company_name, company_code, address, phone, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $company_name, $company_code, $address, $phone, $email);
                
                if ($stmt->execute()) {
                    $message = "Company added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding company: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    if ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $company_name = trim($_POST['company_name'] ?? '');
        $company_code = trim($_POST['company_code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($company_name) || empty($company_code)) {
            $message = "Company name and code are required!";
            $message_type = "danger";
        } else {
            // Check if company code already exists for another company
            $check = $conn->prepare("SELECT id FROM companies WHERE company_code = ? AND id != ?");
            $check->bind_param("si", $company_code, $id);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "Company code already exists for another company!";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE companies SET company_name = ?, company_code = ?, address = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $company_name, $company_code, $address, $phone, $email, $id);
                
                if ($stmt->execute()) {
                    $message = "Company updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating company: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        
        // Check if company has users
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE company_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $result = $check->get_result();
        $user_count = $result->fetch_assoc()['cnt'];
        
        if ($user_count > 0) {
            $message = "Cannot delete company! It has $user_count user(s) assigned. Please reassign or delete users first.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Company deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting company: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Fetch all companies
$companies = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND role = 'admin') as admin_count,
           (SELECT COUNT(*) FROM users WHERE company_id = c.id AND role = 'user') as user_count
    FROM companies c 
    ORDER BY c.company_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Companies | Super Admin</title>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-buildings"></i> Manage Companies</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
            <i class="bi bi-plus-lg"></i> Add Company
        </button>
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
                            <th>Company Name</th>
                            <th>Code</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Admins</th>
                            <th>Users</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($companies && $companies->num_rows > 0): ?>
                            <?php $i = 1; while ($company = $companies->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($company['company_name']); ?></strong></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($company['company_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($company['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($company['phone'] ?? '-'); ?></td>
                                <td><span class="badge bg-primary"><?php echo $company['admin_count']; ?></span></td>
                                <td><span class="badge bg-info"><?php echo $company['user_count']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" 
                                            data-id="<?php echo $company['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($company['company_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($company['company_code']); ?>"
                                            data-address="<?php echo htmlspecialchars($company['address'] ?? ''); ?>"
                                            data-phone="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="<?php echo $company['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($company['company_name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No companies found. Add your first company!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-building-add"></i> Add New Company</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Code *</label>
                        <input type="text" name="company_code" class="form-control" required placeholder="e.g., VA001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Company</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Code *</label>
                        <input type="text" name="company_code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Update Company</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Company Modal -->
<div class="modal fade" id="deleteCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete Company</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
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
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_code').value = this.dataset.code;
        document.getElementById('edit_address').value = this.dataset.address;
        document.getElementById('edit_phone').value = this.dataset.phone;
        document.getElementById('edit_email').value = this.dataset.email;
        new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
    });
});

// Delete button click
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_id').value = this.dataset.id;
        document.getElementById('delete_name').textContent = this.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteCompanyModal')).show();
    });
});
</script>
</body>
</html>
