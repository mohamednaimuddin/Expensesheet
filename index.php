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

            if ($row['role'] == 'superadmin') {
                header("Location: dashboard_super.php");
            } elseif ($row['role'] == 'admin') {
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
}

/* Floating gradient circles */
.circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.15;
    pointer-events: none;
    animation: float 6s ease-in-out infinite alternate;
}
.circle1 { width: 250px; height: 250px; background: #f472b6; top: -60px; left: -60px; }
.circle2 { width: 300px; height: 300px; background: #34d399; bottom: -80px; right: -80px; }
.circle3 { width: 180px; height: 180px; background: #60a5fa; top: 30%; left: 75%; }

@keyframes float {
    0% { transform: translateY(0px) translateX(0px); }
    100% { transform: translateY(20px) translateX(15px); }
}

/* Login card */
.login-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 420px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    position: relative;
    z-index: 10;
    transition: transform 0.3s;
}
.login-card:hover {
    transform: translateY(-5px);
}

.login-card .logo {
    width: 90px;
    margin-bottom: 20px;
}

.login-card h2 {
    margin-bottom: 10px;
    color: #4f46e5;
    font-weight: 600;
}

.login-card p {
    margin-bottom: 25px;
    color: #6b7280;
}

.login-card .form-control {
    border-radius: 12px;
    margin-bottom: 15px;
    padding: 12px 15px;
    border: 1px solid #d1d5db;
    transition: all 0.3s;
}
.login-card .form-control:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 10px rgba(79,70,229,0.2);
    outline: none;
}

.login-card .btn-login {
    background: linear-gradient(90deg, #4f46e5, #ec4899);
    color: #fff;
    border: none;
    padding: 14px;
    width: 100%;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s;
}
.login-card .btn-login:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.login-card .error {
    color: #ef4444;
    margin-bottom: 15px;
    font-weight: 500;
}
</style>
</head>
<body>

<!-- Floating circles -->
<div class="circle circle1"></div>
<div class="circle circle2"></div>
<div class="circle circle3"></div>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="login-card">
                <img src="assets/visionnew.png" alt="VisionAngles Logo" class="logo">
                <h2>VisionAngles Security</h2>
                <p>Please login to your account</p>
                <?php if(!empty($error)) echo "<p class='error'>$error</p>"; ?>
                <form method="POST">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <button type="submit" class="btn-login btn">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
