<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}
include 'config.php';

$username = $_SESSION['username'];

// Expense tables
$tables = [
    'fuel_expense',
    'food_expense',
    'room_expense',
    'other_expense',
    'tools_expense',
    'labour_expense',
    'accessories_expense'
];

// Total Expenses
$total_amount = 0;
$category_totals = [];
foreach ($tables as $table) {
    $sql = "SELECT SUM(amount) AS total FROM $table WHERE username=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL prepare failed for $table: " . $conn->error);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $category_totals[$table] = $row['total'] ?? 0;
    $total_amount += $category_totals[$table];
}

// Total Advance
$sql = "SELECT SUM(adv_amt) AS total_adv FROM adv_amt WHERE username=?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL prepare failed for adv_amt: " . $conn->error);
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$total_adv = $row['total_adv'] ?? 0;

// Latest Carrydown
$cd_query = $conn->prepare("SELECT amount FROM carry_down WHERE username=? ORDER BY created_at DESC LIMIT 1");
$cd_query->bind_param("s", $username);
$cd_query->execute();
$cd_result = $cd_query->get_result();
$total_carry = $cd_result->fetch_assoc()['amount'] ?? 0;

// Balance including carrydown
$balance = ($total_adv + $total_carry) - $total_amount;
$balance_class = $balance >= 0 ? 'positive' : 'negative';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<style>
body {
    background: linear-gradient(120deg, #f0f4ff, #f9f9f9);
    font-family: Arial, sans-serif;
    min-height: 100vh;
}
body::before {
    content: "";
    position: fixed;
    top: 50%; left: 50%;
    width: 400px; height: 400px;
    background: url('assets/vision1.png') no-repeat center;
    background-size: contain;
    opacity: 0.05;
    transform: translate(-50%, -50%);
    z-index: 0;
}
.container, .navbar, footer { position: relative; z-index: 1; }

/* Navbar */
.navbar { background: linear-gradient(90deg, #4f46e5, #ec4899); }
.navbar-brand span { color: #fff; font-weight: bold; font-size: 1.2rem; }
.text-white { color: #fff !important; }

/* Hero */
.hero {
    background: linear-gradient(135deg, #4f46e5, #ec4899);
    color: #fff; border-radius: 12px;
    padding: 30px 20px; margin-bottom: 30px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.hero h1 { font-size: 2rem; }
.hero p { font-size: 1rem; }

/* Cards */
.card-summary {
    background: #fff; border-radius: 12px;
    padding: 20px; color: #333;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform .3s;
}
.card-summary:hover { transform: translateY(-5px); }
.card-expense {
    border-radius: 12px; color: #fff;
    padding: 25px 20px; cursor: pointer;
    transition: transform .3s, box-shadow .3s;
    text-align: center;
}
.card-expense i { font-size: 2rem; margin-bottom: 10px; }
.card-expense:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,.2); }

/* Expense colors */
.fuel { background: linear-gradient(135deg, #f97316, #fcd34d); }
.food { background: linear-gradient(135deg, #10b981, #6ee7b7); }
.room { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.other { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
.tools { background: linear-gradient(135deg, #f43f5e, #fb7185); }
.labour { background: linear-gradient(135deg, #f59e0b, #fcd34d); }
.accessories { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }

/* Footer */
footer { background: #ecf0f1; color: #333; padding: 15px 0; text-align: center; }
.positive { color: green; }
.negative { color: red; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    <div class="d-flex ms-auto align-items-center">
      <span class="text-white me-3">👤 <?php echo ucfirst($username); ?></span>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>
</nav>

<div class="container my-4">
    <!-- Hero -->
    <div class="hero text-center">
        <h1>Welcome</h1>
        <p>Quick overview of your advances and expenses</p>
        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <a href="adv_report_user.php" class="text-decoration-none">
                    <div class="card-summary">
                        <h5>💰 Advance</h5>
                        <p>SAR <?php echo number_format($total_adv, 2); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="report.php" class="text-decoration-none">
                    <div class="card-summary">
                        <h5>💸 Expenses</h5>
                        <p>SAR <?php echo number_format($total_amount, 2); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-summary">
                    <h5>🧾 Balance</h5>
                    <p class="<?php echo $balance_class; ?>">
                        SAR <?php echo number_format($balance, 2); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Cards -->
    <div class="row g-4 mt-4">
        <div class="col-md-3">
            <div class="card-expense fuel" onclick="openPopup('fuelPopup')">
                <i class="bi bi-fuel-pump"></i>
                <h5>⛽ Fuel</h5>
                <p>SAR <?php echo number_format($category_totals['fuel_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense food" onclick="openPopup('foodPopup')">
                <i class="bi bi-egg-fried"></i>
                <h5>🍽️ Food</h5>
                <p>SAR <?php echo number_format($category_totals['food_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense room" onclick="openPopup('roomPopup')">
                <i class="bi bi-house-door"></i>
                <h5>🏨 Hotel</h5>
                <p>SAR <?php echo number_format($category_totals['room_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense other" onclick="openPopup('otherPopup')">
                <i class="bi bi-lightbulb"></i>
                <h5>💡 Other</h5>
                <p>SAR <?php echo number_format($category_totals['other_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense tools" onclick="openPopup('toolsPopup')">
                <i class="bi bi-tools"></i>
                <h5>🛠️ Tools</h5>
                <p>SAR <?php echo number_format($category_totals['tools_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense labour" onclick="openPopup('labourPopup')">
                <i class="bi bi-person-workspace"></i>
                <h5>👷 Labour</h5>
                <p>SAR <?php echo number_format($category_totals['labour_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-expense accessories" onclick="openPopup('accessoriesPopup')">
                <i class="bi bi-bag"></i>
                <h5>🎒 Accessories</h5>
                <p>SAR <?php echo number_format($category_totals['accessories_expense'], 2); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Popups -->
<?php include 'popup_forms.php'; ?>

<footer>
  <p>© 2025 VisionAngles | User Dashboard</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#fuelDate", { dateFormat: "d-m-Y" });
flatpickr("#foodDate", { dateFormat: "d-m-Y" });
flatpickr("#roomDate", { dateFormat: "d-m-Y" });
flatpickr("#otherDate", { dateFormat: "d-m-Y" });
flatpickr("#toolsDate", { dateFormat: "d-m-Y" });
flatpickr("#accessoriesDate",{ dateFormat:"d-m-Y"});
function openPopup(id){ document.getElementById(id).classList.add('active'); }
function closePopup(id){ document.getElementById(id).classList.remove('active'); }
window.onclick = function(e){ document.querySelectorAll('.popup').forEach(p=>{ if(e.target==p) p.classList.remove('active'); }); }
</script>
</body>
</html>
