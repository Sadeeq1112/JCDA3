<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$flutterwave_secret_key = 'YOUR_FLUTTERWAVE_SECRET_KEY'; // Replace with your actual secret key

if (isset($_GET['status']) && isset($_GET['tx_ref']) && isset($_GET['transaction_id'])) {
    $status = $_GET['status'];
    $tx_ref = $_GET['tx_ref'];
    $transaction_id = $_GET['transaction_id'];

    // Verify the transaction
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$transaction_id}/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $flutterwave_secret_key,
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response, true);

    if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
        // Payment was successful
        $payment_id = $result['data']['meta']['payment_id'];

        // Update payment status in the database
        $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'completed', transaction// Update payment status in the database
        $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'completed', transaction_id = ? WHERE id = ?");
        $stmt->execute([$transaction_id, $payment_id]);

        $success_message = "Payment successful! Your annual dues have been recorded.";
    } else {
        // Payment failed
        $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'failed' WHERE id = ?");
        $stmt->execute([$payment_id]);

        $error_message = "Payment failed. Please try again or contact support.";
    }
} else {
    $error_message = "Invalid payment callback. Please contact support.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Payment Result</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Payment Result</h2>
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>