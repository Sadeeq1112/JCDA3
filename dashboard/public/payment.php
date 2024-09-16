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
        'redirect_url' => 'https://yourdomain.com/payment-callback.php',
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Pay JCDA Annual Dues</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="payment.php" method="POST">
            <p>Annual Dues Amount: ₦<?php echo number_format($annual_dues, 2); ?></p>
            <button type="submit">Pay Now</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>