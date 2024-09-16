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
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <nav>
                <ul>
                    <li><a href="profile.php">Edit Profile</a></li>
                    <li><a href="card.php">Membership Card</a></li>
                    <li><a href="payment.php">Pay Dues</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
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
        </main>
    </div>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>