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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
                $stmt->execute(['username' => $username, 'email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $error = "Username or email already exists.";
                } else {
                    // Generate OTP
                    $otp = rand(100000, 999999);
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert pending registration
                    $stmt = $pdo->prepare("INSERT INTO pending_registrations (username, email, password, otp, otp_expiry) VALUES (:username, :email, :password, :otp, :otp_expiry)");
                    if ($stmt->execute(['username' => $username, 'email' => $email, 'password' => $hashed_password, 'otp' => $otp, 'otp_expiry' => $otp_expiry])) {
                        // Send OTP to user's email
                        if (send_otp_email($email, $otp)) {
                            $_SESSION['pending_email'] = $email;
                            header("Location: verify_otp.php");
                            exit;
                        } else {
                            $error = "Failed to send verification email. Please try again.";
                        }
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                $error = "An error occurred while processing your request. Please try again later.";
                log_error($e->getMessage()); // Log the error message for debugging
            }
        }
    }
}

// Function to send OTP email using PHPMailer
function send_otp_email($email, $otp) {
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
        $mail->Subject = 'Email Verification for JCDA';
        $mail->Body    = "Your OTP for email verification is: $otp<br>This OTP will expire in 15 minutes.";
        $mail->AltBody = "Your OTP for email verification is: $otp\nThis OTP will expire in 15 minutes.";

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
    <title>JCDA - Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: flex;
            flex-direction: column;
        }
        .left-side, .right-side {
            padding: 40px;
        }
        .left-side {
            background-color: #e6ffe6;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .logo img {
            max-width: 120px;
            margin-bottom: 20px;
        }
        .left-side img {
            max-width: 80%;
            height: auto;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        form {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #34495e;
        }
        .input-container {
            position: relative;
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        input:focus {
            border-color: #00a86b;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 168, 107, 0.2);
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
        }
        button {
            background-color: #00a86b;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #008c59;
        }
        button:disabled {
            background-color: #a0a0a0;
            cursor: not-allowed;
        }
        .links {
            margin-top: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .links a {
            color: #00a86b;
            text-decoration: none;
            font-weight: 600;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            font-weight: 600;
            text-align: center;
            background-color: #fde8e8;
            padding: 10px;
            border-radius: 6px;
        }
        .password-strength {
            margin-bottom: 20px;
        }
        .password-strength span {
            display: block;
            height: 4px;
            background-color: #ddd;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        .password-strength span.weak { width: 33.33%; background-color: #e74c3c; }
        .password-strength span.medium { width: 66.66%; background-color: #f39c12; }
        .password-strength span.strong { width: 100%; background-color: #27ae60; }
        .password-hints {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        @media (min-width: 768px) {
            .container {
                flex-direction: row;
            }
            .left-side, .right-side {
                width: 50%;
            }
            .left-side {
                padding: 60px 40px;
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
            <h2>Join Our Community</h2>
            <p>Create an account to access exclusive features and connect with fellow members.</p>
        </div>
        <div class="right-side">
            <form action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <?php if (!empty($error)): ?>
                    <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a unique username" required aria-required="true">
                
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required aria-required="true">
                
                <label for="password">Password</label>
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Create a strong password" required aria-required="true">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fa fa-eye-slash"></i></span>
                </div>
                
                <div class="password-strength" id="password-strength">
                    <span></span>
                </div>
                
                <div class="password-hints">
                    Password must be at least 8 characters long with a mix of uppercase, lowercase, numbers, and special characters.
                </div>
                
                <label for="confirm_password">Confirm Password</label>
                <div class="input-container">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required aria-required="true">
                    <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fa fa-eye-slash"></i></span>
                </div>
                
                <button type="submit" id="register-button" disabled>Create Account</button>
            </form>
            <div class="links">
                <p>Already have an account? <a href="login.php">Log in here</a></p>
            </div>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('password-strength');
        const registerButton = document.getElementById('register-button');

        passwordInput.addEventListener('input', function() {
            const value = passwordInput.value;
            let strength = 0;

            if (value.length >= 8) strength++;
            if (/[A-Z]/.test(value)) strength++;
            if (/[a-z]/.test(value)) strength++;
            if (/[0-9]/.test(value)) strength++;
            if (/[^A-Za-z0-9]/.test(value)) strength++;

            passwordStrength.innerHTML = '<span></span>';
            const span = passwordStrength.querySelector('span');

            if (strength < 3) {
                span.className = 'weak';
                registerButton.disabled = true;
            } else if (strength < 5) {
                span.className = 'medium';
                registerButton.disabled = false;
            } else {
                span.className = 'strong';
                registerButton.disabled = false;
            }
        });

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }
    </script>
</body>
</html>