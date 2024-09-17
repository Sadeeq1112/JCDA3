<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = '';

if (!isset($_SESSION['pending_email'])) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = sanitize_input($_POST['otp']);
    $email = $_SESSION['pending_email'];

    if (empty($otp)) {
        $error = "Please enter the OTP.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE email = ? AND otp = ? AND otp_expiry > NOW()");
            $stmt->execute([$email, $otp]);
            $user = $stmt->fetch();

            if ($user) {
                // OTP is valid, complete registration
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                if ($stmt->execute([$user['username'], $user['email'], $user['password']])) {
                    // Delete from pending_registrations
                    $stmt = $pdo->prepare("DELETE FROM pending_registrations WHERE email = ?");
                    $stmt->execute([$email]);

                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['username'] = $user['username'];
                    unset($_SESSION['pending_email']);
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Registration failed. Please try again.";
                }
            } else {
                $error = "Invalid or expired OTP. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred while processing your request. Please try again later.";
            log_error($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Verify OTP</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">Verify Your Email</h2>
                <p class="text-center">Please enter the OTP sent to your email.</p>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form action="verify_otp.php" method="POST">
                    <div class="form-group">
                        <label for="otp">One-Time Password (OTP)</label>
                        <input type="text" id="otp" name="otp" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>