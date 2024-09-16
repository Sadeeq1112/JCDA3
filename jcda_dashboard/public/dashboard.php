<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user profile information
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch latest payment information
$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$user_id]);
$latest_payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f0f2f5;
        }
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #0052cc;
            color: white;
            padding: 20px;
        }
        .sidebar h2 {
            margin-bottom: 30px;
        }
        .sidebar ul {
            list-style-type: none;
        }
        .sidebar li {
            margin-bottom: 15px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 5px;
        }
        .sidebar-icon {
            margin-right: 10px;
            width: 20px;
            height: 20px;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            margin: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .user-profile {
            display: flex;
            align-items: center;
        }
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
        }
        .profile-summary, .payment-status {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        h2 {
            margin-bottom: 15px;
            color: #333;
        }
        p {
            margin-bottom: 10px;
            color: #666;
        }
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                padding: 10px;
            }
            .main-content {
                margin: 10px;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-profile {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <h2>JCDA</h2>
            <ul>
                <li><a href="#" class="active"><span class="sidebar-icon">📊</span> Dashboard</a></li>
                <li><a href="profile.php"><span class="sidebar-icon">👤</span> Edit Profile</a></li>
                <li><a href="card.php"><span class="sidebar-icon">💳</span> Membership Card</a></li>
                <li><a href="payment.php"><span class="sidebar-icon">💰</span> Pay Dues</a></li>
                <li><a href="logout.php"><span class="sidebar-icon">🚪</span> Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="user-profile">
                    <span>🔔</span>
                    <img src="../assets/images/user-avatar.jpg" alt="User profile">
                </div>
            </div>
            <section class="profile-summary">
                <h2>Profile Summary</h2>
                <?php if ($profile): ?>
                    <p>Name: <?php echo htmlspecialchars($profile['full_name']); ?></p>
                    <p>Occupation: <?php echo htmlspecialchars($profile['occupation']); ?></p>
                <?php else: ?>
                    <p>Please complete your profile.</p>
                <?php endif; ?>
            </section>
            
            <section class="payment-status">
                <h2>Payment Status</h2>
                <?php if ($latest_payment): ?>
                    <p>Last Payment: <?php echo date("F j, Y", strtotime($latest_payment['payment_date'])); ?></p>
                    <p>Amount: $<?php echo number_format($latest_payment['amount'], 2); ?></p>
                    <p>Status: <?php echo ucfirst($latest_payment['payment_status']); ?></p>
                <?php else: ?>
                    <p>No payment records found.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>