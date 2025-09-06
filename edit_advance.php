<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id'])) {
    die("Advance ID not specified!");
}

$id = $_GET['id'];

// Fetch advance details
$stmt = $conn->prepare("SELECT * FROM adv_amt WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    die("Advance not found!");
}

$advance = $result->fetch_assoc();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adv_date = $_POST['adv_date'];
    $adv_amount = $_POST['adv_amount'];

    $update = $conn->prepare("UPDATE adv_amt SET date = ?, adv_amt = ? WHERE id = ?");
    $update->bind_param("sdi", $adv_date, $adv_amount, $id);
    $update->execute();

    $return_url = isset($_GET['return']) ? $_GET['return'] : "user_report.php?username=".$advance['username'];
    header("Location: " . $return_url);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Advance</title>
<link href="assets/user_report.css" rel="stylesheet">
<style>
    /* Responsive form container */
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background-color: #f5f5f5;
    }

    .form-container {
        background-color: #fff;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        max-width: 400px;
        width: 90%;
        box-sizing: border-box;
    }

    h2 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 1.5rem;
    }

    label {
        display: block;
        margin-bottom: 15px;
        font-weight: bold;
    }

    input[type="date"],
    input[type="number"] {
        width: 100%;
        padding: 8px 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
        font-size: 1rem;
    }

    .button-group {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    button {
        flex: 1;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
    }

    button[type="submit"] {
        background-color: #4CAF50;
        color: white;
    }

    button.cancel-btn {
        background-color: #f44336;
        color: white;
    }

    @media (max-width: 480px) {
        h2 {
            font-size: 1.25rem;
        }

        button {
            font-size: 0.9rem;
            padding: 8px 10px;
        }
    }
</style>
</head>
<body>
<div class="form-container">
    <h2>Edit Advance for <?php echo ucfirst($advance['username']); ?></h2>
    <form method="POST">
        <label>Date:
            <input type="date" name="adv_date" value="<?php echo $advance['date']; ?>" required>
        </label>
        <label>Amount (SAR):
            <input type="number" step="0.01" name="adv_amount" value="<?php echo $advance['adv_amt']; ?>" required>
        </label>
        <div class="button-group">
            <button type="submit">Update</button>
            <button type="button" class="cancel-btn" onclick="window.history.back()">Back</button>
        </div>
    </form>
</div>
</body>
</html>
