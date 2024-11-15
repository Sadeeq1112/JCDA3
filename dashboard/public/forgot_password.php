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
        // Enable verbose debug output
        $mail->SMTPDebug = 0; // Set to 0 in production
        $mail->Debugoutput = function($str, $level) {
            log_error("PHPMailer debug level $level; message: $str");
        };

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
        $mail->Body    = "Hello,<br><br>We received a request to reset your password. Click the link below to reset your password:<br><a href='https://jcda.com.ng/dashboard/public/reset_password.php?token=$token'>Reset Password</a><br><br>This link will expire in 1 hour.<br><br>If you did not request a password reset, please ignore this email.<br><br>Thank you,<br>JCDA Team";
        $mail->AltBody = "Hello,\n\nWe received a request to reset your password. Click the link below to reset your password:\nhttps://jcda.com.ng/dashboard/public/reset_password.php?token=$token\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nJCDA Team";

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
        .success {
            color: green;
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
            <h2>Forgot Password</h2>
            <p>Enter your email address to receive a password reset link. Make sure to check your spam or junk folder if you don't see the email in your inbox.</p>
            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success" role="alert"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form action="forgot_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required aria-required="true">
                <button type="submit">Send Password Reset Link</button>
            </form>
            <div class="links">
                <p>Remembered your password? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>