<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance | VisionAngles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/vision.ico">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    color: #fff;
    text-align: center;
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

/* Maintenance card */
.maintenance-card {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 3rem 2.5rem;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    position: relative;
    z-index: 10;
    transition: transform 0.3s;
}
.maintenance-card:hover {
    transform: translateY(-5px);
}

.maintenance-card img {
    width: 80px;
    margin-bottom: 20px;
}

.maintenance-card h2 {
    margin-bottom: 15px;
    font-weight: 600;
    color: #fff;
}

.maintenance-card p {
    font-size: 1.1rem;
    margin-bottom: 25px;
}

.maintenance-card .btn-back {
    background: linear-gradient(90deg, #4f46e5, #ec4899);
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
}
.maintenance-card .btn-back:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="maintenance-card">
                <img src="assets/visionnew.png" alt="VisionAngles Logo">
                <h2>We'll Be Back Soon!</h2>
                <p>Our system is currently undergoing scheduled maintenance.<br>
                Thank you for your patience.</p>
                <a href="index.php" class="btn-back">Return to Login</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
