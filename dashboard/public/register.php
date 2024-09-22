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
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            flex-direction: column;
        }
        .left-side {
            background-color: #e6ffe6;;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
        }
        .right-side {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
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
        .password-strength {
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        .password-strength span {
            display: inline-block;
            width: 100px;
            height: 10px;
            background-color: #ddd;
            border-radius: 5px;
        }
        .password-strength span.weak {
            background-color: red;
        }
        .password-strength span.medium {
            background-color: orange;
        }
        .password-strength span.strong {
            background-color: green;
        }
        .password-hints {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
        }
        .toggle-password {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .toggle-password input {
            margin-right: 5px;
        }
        @media (min-width: 769px) {
            .container {
                flex-direction: row;
            }
            .left-side, .right-side {
                width: 50%;
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
            <h2>Register for JCDA</h2>
            <p>Welcome! Please fill in the form to create an account.</p>
            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required aria-required="true">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required aria-required="true">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required aria-required="true">
                <div class="password-hints">
                    Password must be at least 8 characters long and include a mix of uppercase letters, lowercase letters, numbers, and special characters.
                </div>
                <div class="password-strength" id="password-strength">
                    <span></span>
                </div>
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required aria-required="true">
                <div class="toggle-password">
                    <input type="checkbox" id="toggle-password-visibility">
                    <label for="toggle-password-visibility">Show Password</label>
                </div>
                <button type="submit" id="register-button" disabled>Register</button>
            </form>
            <div class="links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('password-strength');
        const registerButton = document.getElementById('register-button');
        const togglePasswordVisibility = document.getElementById('toggle-password-visibility');

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

        togglePasswordVisibility.addEventListener('change', function() {
            const type = togglePasswordVisibility.checked ? 'text' : 'password';
            passwordInput.type = type;
            confirmPasswordInput.type = type;
        });
    </script>
</body>
</html>