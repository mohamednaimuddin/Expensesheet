<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}
include 'config.php';

$username = $_SESSION['username'];
// Define current month date range
$first_day = date('Y-m-01');
$last_day  = date('Y-m-t');


// Expense tables except vehicle_expense
$tables = [
    'fuel_expense',
    'food_expense',
    'room_expense',
    'other_expense',
    'tools_expense',
    'labour_expense',
    'accessories_expense',
    'tv_expense'
];

// Total Expenses
$total_amount = 0;
$category_totals = [];
foreach ($tables as $table) {
    $sql = "SELECT SUM(amount) AS total FROM $table WHERE username=? AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);


    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL prepare failed for $table: " . $conn->error);
    $stmt->bind_param("sss", $username, $first_day, $last_day);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $category_totals[$table] = $row['total'] ?? 0;
    $total_amount += $category_totals[$table];
}

// Vehicle Expenses (separate query)
$sql_vehicle = "SELECT SUM(amount) AS total FROM vehicle_expense WHERE username=? AND date BETWEEN ? AND ?";

$stmt_vehicle = $conn->prepare($sql_vehicle);


if (!$stmt_vehicle) die("SQL prepare failed for vehicle_expense: " . $conn->error);
$stmt_vehicle->bind_param("sss", $username, $first_day, $last_day);
$stmt_vehicle->execute();
$row_vehicle = $stmt_vehicle->get_result()->fetch_assoc();
$category_totals['vehicle_expense'] = $row_vehicle['total'] ?? 0;
$total_amount += $category_totals['vehicle_expense'];

// Total Advance
$sql = "SELECT SUM(adv_amt) AS total_adv FROM adv_amt WHERE username=? AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);


if (!$stmt) die("SQL prepare failed for adv_amt: " . $conn->error);
$stmt->bind_param("sss", $username, $first_day, $last_day);
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
<link href="assets/dashboard_user.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/visionangles.png" alt="Logo" style="height:40px; margin-right:10px;">
      <span>Vision Angles Security EST.</span>
    </a>
    
    <!-- Mobile hamburger button -->
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="bi bi-list text-white" style="font-size: 1.5rem;"></i>
    </button>
    
    <!-- Collapsible navigation content -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <div class="navbar-nav ms-auto d-flex align-items-center">
        <span class="navbar-text text-white me-3">👤 <?php echo ucfirst($username); ?></span>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="container my-3">
    <div class="hero text-center">
        <h1>Welcome Back, <?php echo ucfirst($username); ?>!</h1>
        <p>Your financial overview for the current month.</p>
        <div class="row mt-3">
            <div class="col-lg-4 col-md-4 col-sm-12 mb-2">
                <a href="adv_report_user.php" class="text-decoration-none">
                    <div class="card-summary advance">
                        <h5>💰 Total Advance</h5>
                        <p class="amount-lg">SAR <?php echo number_format($total_adv, 2); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12 mb-2">
                <a href="report.php" class="text-decoration-none">
                    <div class="card-summary expense">
                        <h5>💸 Total Expenses</h5>
                        <p class="amount-lg">SAR <?php echo number_format($total_amount, 2); ?></p>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12 mb-2">
                <div class="card-summary balance">
                    <h5>🧾 Current Balance</h5>
                    <p class="amount-lg <?php echo $balance_class; ?>">SAR <?php echo number_format($balance, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="text-center my-3 expense-title">Monthly Expense Breakdown</h2>

    <div class="row g-3 mb-3 expense-grid">
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense fuel" onclick="openPopup('fuelPopup')">
                <i class="bi bi-fuel-pump"></i>
                <h5>Fuel</h5>
                <p>SAR <?php echo number_format($category_totals['fuel_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense food" onclick="openPopup('foodPopup')">
                <i class="bi bi-egg-fried"></i>
                <h5>Food</h5>
                <p>SAR <?php echo number_format($category_totals['food_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense room" onclick="openPopup('roomPopup')">
                <i class="bi bi-house-door"></i>
                <h5>Hotel</h5>
                <p>SAR <?php echo number_format($category_totals['room_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense other" onclick="openPopup('otherPopup')">
                <i class="bi bi-lightbulb"></i>
                <h5>Other</h5>
                <p>SAR <?php echo number_format($category_totals['other_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense tools" onclick="openPopup('toolsPopup')">
                <i class="bi bi-tools"></i>
                <h5>Tools</h5>
                <p>SAR <?php echo number_format($category_totals['tools_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense labour" onclick="openPopup('labourPopup')">
                <i class="bi bi-person-workspace"></i>
                <h5>Labour</h5>
                <p>SAR <?php echo number_format($category_totals['labour_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense accessories" onclick="openPopup('accessoriesPopup')">
                <i class="bi bi-bag"></i>
                <h5>Accessories</h5>
                <p>SAR <?php echo number_format($category_totals['accessories_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense tv" onclick="openPopup('tvPopup')">
                <i class="bi bi-tv"></i>
                <h5>TV</h5>
                <p>SAR <?php echo number_format($category_totals['tv_expense'], 2); ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-2">
            <div class="card-expense vehicle" onclick="openPopup('vehiclePopup')">
                <i class="bi bi-car-front"></i>
                <h5>Vehicle</h5>
                <p>SAR <?php echo number_format($category_totals['vehicle_expense'], 2); ?></p>
            </div>
        </div>
    </div>
</div>

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
flatpickr("#accessoriesDate", { dateFormat: "d-m-Y" });
flatpickr("#tvDate", { dateFormat: "d-m-Y" })

function openPopup(id){ document.getElementById(id).classList.add('active'); }
function closePopup(id){ document.getElementById(id).classList.remove('active'); }
window.onclick = function(e){ document.querySelectorAll('.popup').forEach(p=>{ if(e.target==p) p.classList.remove('active'); }); }
</script>
</body>
</html>