<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require '../vendor/autoload.php'; // Include PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $email = sanitize_input($_POST['email']);

        if (empty($email)) {
            $error = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Insert reset token into database
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (:email, :token, :expiry)");
                    if ($stmt->execute(['email' => $email, 'token' => $reset_token, 'expiry' => $reset_expiry])) {
                        // Send reset email
                        if (send_reset_email($email, $reset_token)) {
                            $success = "A password reset link has been sent to your email.";
                        } else {
                            $error = "Failed to send reset email. Please try again.";
                        }
                    } else {
                        $error = "Failed to generate reset token. Please try again.";
                    }
                } else {
                    $error = "No account found with that email.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred while processing your request. Please try again later.";
                log_error($e->getMessage()); // Log the error message for debugging
            }
        }
    }
}

// Function to send reset email using PHPMailer
function send_reset_email($email, $token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use 'ssl' if PHPMailer version < 6.1
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset for JCDA';
        $mail->Body    = "Click the link below to reset your password:<br><a href='https://jcda.com.ng/reset_password.php?token=$token'>Reset Password</a><br>This link will expire in 1 hour.";
        $mail->AltBody = "Click the link below to reset your password:\nhttps://jcda.com.ng/reset_password.php?token=$token\nThis link will expire in 1 hour.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_error("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Forgot Password</title>
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
        input {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background-color: #00a86b;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
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
        }
        .success {
            color: green;
            margin-bottom: 15px;
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
            <h2>Forgot Password</h2>
            <p>Enter your email address to receive a password reset link.</p>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form action="forgot_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="email" name="email" placeholder="Email" required>
                <button type="submit">Send Reset Link</button>
            </form>
            <div class="links">
                <p>Remembered your password? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>