<?php
session_start();
include "config.php";
include "log_helper.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function redirectLoggedInUser() {
    if (empty($_SESSION['role'])) {
        return;
    }

    if ($_SESSION['role'] === 'superadmin') {
        header("Location: dashboard_superadmin.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: dashboard_user.php");
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectLoggedInUser();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Block disabled users from logging in
            if (array_key_exists('is_active', $row) && intval($row['is_active']) === 0) {
                $error = "Your account has been disabled. Please contact your administrator.";
            } else {
                session_regenerate_id(true);
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['company_id'] = $row['company_id'] ?? 0;
                $_SESSION['company_name'] = $row['company_name'] ?? 'N/A';
            
            // Log login activity
            logActivity($conn, LOG_LOGIN, 'User logged in successfully');

            if ($row['role'] == 'superadmin') {
                header("Location: dashboard_superadmin.php");
            } elseif ($row['role'] == 'admin') {
                header("Location: dashboard_admin.php");
            } else {
                    // Check for pending expenses from previous month if it's the next month
                    
                    $current_month = date('Y-m');
                    $prev_month = date('Y-m', strtotime('-1 month'));
                    $redirect_to_pending = false;
                    if ($current_month === date('Y-m', strtotime($prev_month . ' +1 month'))) {
                        $expense_tables = [
                            'fuel_expense', 'food_expense', 'room_expense', 'other_expense',
                            'tools_expense', 'labour_expense', 'accessories_expense', 'tv_expense', 'vehicle_expense'
                        ];
                        foreach ($expense_tables as $table) {
                            $date_col = ($table === 'vehicle_expense') ? 'date' : 'date';
                            $sql_exp = "SELECT id FROM $table WHERE username=? AND $date_col LIKE ? AND (submitted=0 OR submitted IS NULL) LIMIT 1";
                            $stmt_exp = $conn->prepare($sql_exp);
                            $like_month = $prev_month . '%';
                            $stmt_exp->bind_param('ss', $username, $like_month);
                            $stmt_exp->execute();
                            $result_exp = $stmt_exp->get_result();
                            if ($result_exp->fetch_assoc()) {
                                $redirect_to_pending = true;
                                break;
                            }
                        }
                    }
                    if ($redirect_to_pending) {
                        header("Location: report.php");
                    } else {
                        header("Location: dashboard_user.php");
                    }
            }
            exit();
            } // end is_active check
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No user found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Login | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="assets/index.css">
<link rel="stylesheet" href="assets/loader.css">
<link rel="icon" type="image/png" href="assets\vision.ico">
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

<!-- Floating circles -->
<div class="circle circle1"></div>
<div class="circle circle2"></div>
<div class="circle circle3"></div>

<div class="container-fluid vh-100">
    <div class="row h-100 justify-content-center align-items-center">
        <div class="col-12 col-sm-11 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
            <div class="login-card">
                <div class="text-center">
                    <img src="assets/visionnew.png" alt="VisionAngles Logo" class="logo">
                    <h2>VisionAngles Security</h2>
                    <p>Please login to your account</p>
                    <?php if(!empty($error)) echo "<p class='error'>$error</p>"; ?>
                </div>
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <input type="text" name="username" class="form-control" placeholder="Username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                            <span class="password-toggle" onclick="togglePassword()" role="button" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="btn-login btn">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

// Hide page loader when page is fully loaded
window.addEventListener('load', function() {
    document.getElementById('pageLoader').classList.add('hidden');
});

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

// Show loader on form submit
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            document.getElementById('pageLoader').classList.remove('hidden');
        });
    }
});
</script>

</body>
</html>
