<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user information
$stmt = $pdo->prepare("SELECT u.email, p.full_name, p.phone FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set annual dues amount
$annual_dues = 5000; // ₦5,000

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate a unique transaction reference
    $tx_ref = 'JCDA-' . uniqid();

    // Store payment information in the database
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_status) VALUES (?, ?, 'pending')");
    $stmt->execute([$user_id, $annual_dues]);
    $payment_id = $pdo->lastInsertId();

    // Prepare Flutterwave API request
    $flutterwave_url = 'https://api.flutterwave.com/v3/payments';
    $flutterwave_secret_key = 'YOUR_FLUTTERWAVE_SECRET_KEY'; // Replace with your actual secret key

    $data = [
        'tx_ref' => $tx_ref,
        'amount' => $annual_dues,
        'currency' => 'NGN',
        'redirect_url' => 'https://jcda.com.ng/dashboard/public/payment-callback.php',
        'payment_options' => 'card,banktransfer',
        'meta' => [
            'payment_id' => $payment_id
        ],
        'customer' => [
            'email' => $user['email'],
            'name' => $user['full_name'],
            'phone_number' => $user['phone']
        ],
        'customizations' => [
            'title' => 'JCDA Annual Dues',
            'description' => 'Payment for JCDA annual membership dues',
            'logo' => 'https://yourdomain.com/assets/images/logo.png'
        ]
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $flutterwave_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $flutterwave_secret_key,
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response, true);

    if ($result['status'] === 'success') {
        // Redirect to Flutterwave payment page
        header('Location: ' . $result['data']['link']);
        exit;
    } else {
        $error = 'Failed to initialize payment. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Pay Annual Dues</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
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
            width: 80px; /* Default to collapsed width */
            background-color: #378349;
            color: white;
            padding: 20px;
            transition: width 0.3s, transform 0.3s;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar.expanded {
            width: 80px; /* Show only icons */
        }
        .sidebar h2 {
            margin-bottom: 30px;
        }
        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }
        .sidebar li {
            margin-bottom: 20px; /* Increased margin */
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            position: relative;
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 5px;
            position: relative;
            left: -6px;
        }
        .sidebar-icon {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            text-align: center; /* Align icons */
        }
        .sidebar .sidebar-text {
            display: none;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            margin: 20px;
            margin-left: 100px; /* Adjusted for sidebar */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: margin-left 0.3s;
        }
        .main-content.expanded {
            margin-left: 100px; /* Adjusted for expanded sidebar */
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 2rem; /* Default font size */
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
            .sidebar {
                transform: translateX(-100%); /* Hide sidebar by default */
            }
            .sidebar.expanded {
                transform: translateX(0); /* Show sidebar when expanded */
            }
            .main-content {
                margin-left: 20px; /* Adjusted for hidden sidebar */
            }
            .main-content.expanded {
                margin-left: 100px; /* Adjusted for expanded sidebar */
            }
            .header h1 {
                font-size: 1.5rem; /* Reduced font size for mobile */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar hidden" id="sidebar">
            <h2>JCDA</h2>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home sidebar-icon"></i> <span class="sidebar-text">Home</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user sidebar-icon"></i> <span class="sidebar-text">Edit Profile</span></a></li>
                <li><a href="card.php"><i class="fas fa-id-card sidebar-icon"></i> <span class="sidebar-text">Membership Card</span></a></li>
                <li><a href="payment.php" class="active"><i class="fas fa-money-bill sidebar-icon"></i> <span class="sidebar-text">Pay Dues</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt sidebar-icon"></i> <span class="sidebar-text">Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content" id="mainContent">
            <div class="header">
                <button class="btn btn-primary" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="user-profile">
                    <button type="button" class="btn btn-secondary">
                        <i class="fas fa-bell"></i>
                    </button>
                    <img src="assets/images/user-avatar.jpg" class="rounded-circle" alt="User profile">
                </div>
            </div>
            <section class="profile-summary">
                <h2>Pay JCDA Annual Dues</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="payment.php" method="POST">
                    <p>Annual Dues Amount: ₦<?php echo number_format($annual_dues, 2); ?></p>
                    <button type="submit" class="btn btn-primary">Pay Now</button>
                </form>
                <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
            </section>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('hidden');
            document.getElementById('sidebar').classList.toggle('expanded');
            document.getElementById('mainContent').classList.toggle('expanded');
        });
    </script>
</body>
</html>