<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user profile information
$stmt = $pdo->prepare("SELECT u.username, p.* FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate membership number
$membership_number = sprintf('JCDA-%06d', $user_id);

// Generate QR code
require_once 'vendor/autoload.php'; // Make sure you've installed the phpqrcode library via Composer
$qr = new QRCode($membership_number);
$qr_image = $qr->png(false, QR_ECLEVEL_L, 4);
$qr_base64 = base64_encode($qr_image);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Membership Card</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .membership-card {
            width: 350px;
            height: 200px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 20px;
            margin: 20px auto;
            position: relative;
        }
        .membership-card h3 {
            margin-top: 0;
        }
        .membership-card .qr-code {
            position: absolute;
            right: 20px;
            top: 20px;
            width: 80px;
            height: 80px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Your JCDA Membership Card</h2>
        <div class="membership-card">
            <h3>Jos Community Development Association</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
            <p><strong>Membership No:</strong> <?php echo $membership_number; ?></p>
            <p><strong>Occupation:</strong> <?php echo htmlspecialchars($user['occupation']); ?></p>
            <img class="qr-code" src="data:image/png;base64,<?php echo $qr_base64; ?>" alt="QR Code">
        </div>
        <button onclick="window.print()">Print Membership Card</button>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>