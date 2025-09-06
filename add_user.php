<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $number = isset($_POST['number']) ? trim($_POST['number']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($number) || empty($role)) {
        $message = "All fields are required!";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $message = "Username or Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, email, number, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $full_name, $username, $hashed_password, $email, $number, $role);

            if ($stmt->execute()) {
                $message = "User added successfully!";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User | Admin Dashboard</title>
<link rel="stylesheet" href="assets/dashboard.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="icon" type="image/png" href="assets\vision.ico">
<style>
    /* Form container */
    .form-container { 
        max-width: 500px; 
        margin: 50px auto; 
        background: #f5f5f5; 
        padding: 20px; 
        border-radius: 10px; 
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .form-container h2 { 
        text-align: center; 
        margin-bottom: 20px; 
        font-size: 1.8em;
    }
    .form-container label { 
        display: block; 
        margin-top: 10px; 
        font-weight: 500;
    }
    .form-container input, .form-container select { 
        width: 100%; 
        padding: 10px; 
        margin-top: 5px; 
        border-radius: 5px; 
        border: 1px solid #ccc; 
        box-sizing: border-box;
    }
    .form-container .button-group {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        gap: 10px;
        flex-wrap: wrap;
    }
    .form-container button {
        flex: 1;
        padding: 10px 20px; 
        border: none; 
        border-radius: 5px; 
        background: #007BFF; 
        color: #fff; 
        cursor: pointer;
        font-size: 1em;
    }
    .form-container button.back-btn {
        background: #6c757d;
    }
    .form-container button:hover {
        opacity: 0.9;
    }
    .message { 
        text-align: center; 
        margin-bottom: 10px; 
        color: red; 
        font-weight: 500;
    }

    /* Responsive adjustments */
    @media (max-width: 480px) {
        .form-container {
            padding: 15px;
            margin: 20px;
        }
        .form-container h2 {
            font-size: 1.5em;
        }
        .form-container button {
            font-size: 0.9em;
        }
    }
</style>
</head>
<body>

<div class="form-container">
    <h2>Add New User/Admin</h2>
    <?php if ($message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    <form action="add_user.php" method="POST">
        <label>Full Name:</label>
        <input type="text" name="full_name" required>

        <label>Username:</label>
        <input type="text" name="username" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <label>Number:</label>
        <input type="text" name="number" required>

        <label>Role:</label>
        <select name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select>

        <div class="button-group">
            <button type="submit">Add User</button>
            <button type="button" class="back-btn" onclick="window.history.back();">Back</button>
        </div>
    </form>
</div>

</body>
</html>
