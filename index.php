<?php
session_start();
include "config.php"; // Assuming this includes your database connection ($conn)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Prepared statement is good practice and kept.
    $sql = "SELECT * FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Success: Set session variables and redirect
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Role-based redirection is kept.
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
    
    // It's good practice to close the statement after use, though PHP cleans up.
    $stmt->close();
}
// You might also close the connection here if your config.php doesn't manage it (e.g., $conn->close();)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets\vision.ico">
<style>
/* Global Styling */
body {
    /* Using Inter now, but keeping the original Poppins fallback */
    font-family: 'Inter', 'Poppins', sans-serif;
    /* Updated gradient for a deeper, more sophisticated look */
    background: linear-gradient(135deg, #4c1d95 0%, #a78bfa 100%);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
}

/* Floating gradient circles - slightly restyled */
.circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.15;
    filter: blur(5px); /* Added a subtle blur for a softer effect */
    pointer-events: none;
    animation: float 8s ease-in-out infinite alternate; /* Slower animation */
}
.circle1 { 
    width: 250px; height: 250px; 
    background: #fca5a5; /* Light Red/Pink */
    top: -60px; left: -60px; 
}
.circle2 { 
    width: 300px; height: 300px; 
    background: #6ee7b7; /* Mint Green */
    bottom: -80px; right: -80px; 
    animation-delay: 2s; /* Staggered animation */
}
.circle3 { 
    width: 180px; height: 180px; 
    background: #93c5fd; /* Light Blue */
    top: 30%; left: 75%; 
    animation-delay: 4s;
}

@keyframes float {
    0% { transform: translateY(0px) translateX(0px) scale(1); }
    100% { transform: translateY(25px) translateX(20px) scale(1.05); } /* Increased movement */
}

/* Login card - The main focus */
.login-card {
    background: #ffffff;
    /* Slightly rounded corners */
    border-radius: 16px; 
    padding: 2.5rem 2rem; /* Slightly reduced padding for compactness */
    width: 100%;
    max-width: 400px; /* Slightly narrower card */
    text-align: center;
    /* Modern, soft, and deep box-shadow */
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 20px 50px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 10;
    /* Removed hover transform for a less "bouncy" feel */
}

.login-card .logo {
    width: 90px;
    margin-bottom: 20px;
}

.login-card h2 {
    margin-bottom: 8px;
    color: #4f46e5; /* Primary Indigo */
    font-weight: 700; /* Bolder title */
}

.login-card p {
    margin-bottom: 30px; /* Increased margin */
    color: #6b7280;
    font-size: 0.95rem;
}

/* Form controls */
.login-card .form-control {
    border-radius: 8px; /* Slightly less rounded inputs */
    margin-bottom: 18px; /* Increased spacing between inputs */
    padding: 14px 15px; /* Taller inputs */
    border: 1px solid #e5e7eb;
    transition: all 0.3s;
    font-size: 1rem;
}
.login-card .form-control:focus {
    border-color: #4f46e5;
    /* Sharper focus shadow */
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); 
    outline: none;
}

/* Login Button */
.login-card .btn-login {
    /* Revised gradient for better contrast and depth */
    background: linear-gradient(90deg, #4f46e5 0%, #8b5cf6 100%); 
    color: #fff;
    border: none;
    padding: 14px;
    width: 100%;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    letter-spacing: 0.5px;
    transition: all 0.2s;
}
.login-card .btn-login:hover {
    /* Subtle color shift and lift on hover */
    background: linear-gradient(90deg, #4338ca 0%, #7c3aed 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
}

.login-card .error {
    color: #ef4444;
    margin-bottom: 15px;
    font-weight: 600; /* Slightly bolder error message */
}
</style>
</head>
<body>

<div class="circle circle1"></div>
<div class="circle circle2"></div>
<div class="circle circle3"></div>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
            <div class="login-card">
                <img src="assets/visionnew.png" alt="VisionAngles Logo" class="logo">
                <h2>VisionAngles Security</h2>
                <p>Please log in to your account to continue</p>
                <?php if(!empty($error)) echo "<p class='error'>$error</p>"; ?>
                <form method="POST">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <button type="submit" class="btn-login btn">Secure Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>