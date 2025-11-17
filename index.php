<?php
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT * FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] == 'admin') {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: dashboard_user.php");
            }
            exit();
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
<link rel="icon" type="image/png" href="assets\vision.ico">
</head>
<body>

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
</script>

</body>
</html>
