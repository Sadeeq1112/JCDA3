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
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            display: flex;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            width: 80%;
            max-width: 1000px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .left-side {
            background-color: #ffe6e6;
            padding: 40px;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .right-side {
            padding: 40px;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .logo {
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 100px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }
        input:focus {
            border-color: #00a86b;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 168, 107, 0.5);
        }
        button {
            background-color: #00a86b;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        button:focus {
            outline: none;
            box-shadow: 0 0 5px rgba(0, 168, 107, 0.5);
        }
        .links {
            margin-top: 20px;
            font-size: 0.9em;
        }
        .links a {
            color: #00a86b;
            text-decoration: none;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 90%;
            }
            .left-side, .right-side {
                width: 100%;
                padding: 20px;
            }
            .left-side {
                display: none; /* Hide the left side on mobile */
            }
        }
        @media (max-width: 480px) {
            .container {
                width: 100%;
                border-radius: 0;
            }
            .right-side {
                padding: 20px;
            }
            input, button {
                padding: 15px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-side">
            <div class="logo">
                <img src="/JCDA.png" alt="JCDA Logo">
            </div>
            <img src="/api/placeholder/400/300" alt="Illustration" style="max-width: 100%;">
        </div>
        <div class="right-side">
            <h2>Verify Your Email</h2>
            <p>Please enter the OTP sent to your email.</p>
            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
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
</body>
</html>