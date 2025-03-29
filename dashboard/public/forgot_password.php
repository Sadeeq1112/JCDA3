<?php
// filepath: /Users/user/Desktop/JCDA3/dashboard/public/forgot_password.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize session securely
if (session_status() == PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    // Protocol-relative URL to maintain HTTP/HTTPS as appropriate
    $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url");
    exit;
}

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: same-origin');

// CSRF token generation/validation
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

$error = '';
$success = '';
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token using constant-time comparison
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

        if (empty($email)) {
            $error = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email LIMIT 1");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Delete any existing reset tokens for this email
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    
                    // Generate secure token and set expiration
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store token in database
                    $stmt = $pdo->prepare(
                        "INSERT INTO password_resets (email, token, expiry, created_at) 
                         VALUES (:email, :token, :expiry, NOW())"
                    );
                    
                    if ($stmt->execute([
                        'email' => $email, 
                        'token' => $reset_token, 
                        'expiry' => $reset_expiry
                    ])) {
                        if (send_reset_email($email, $reset_token)) {
                            $success = "Password reset instructions have been sent to your email address.";
                            $email_sent = true;
                            
                            // Generate new CSRF token after successful submission
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $error = "Failed to send email. Please try again or contact support.";
                        }
                    } else {
                        $error = "An error occurred. Please try again later.";
                    }
                } else {
                    // Show same message even if email not found (security best practice)
                    // This prevents user enumeration attacks
                    $success = "If an account exists with this email, password reset instructions will be sent.";
                    $email_sent = true;
                    
                    // Add artificial delay to prevent timing attacks
                    usleep(rand(300000, 600000)); // 300-600ms delay
                }
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
                log_error('Password reset error: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Send password reset email
 * 
 * @param string $email Recipient email address
 * @param string $token Reset token
 * @return bool Success status
 */
function send_reset_email($email, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = SMTP_PORT;
        
        // Rate limiting for security
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Generate message
        $reset_url = 'https://' . $_SERVER['HTTP_HOST'] . '/dashboard/public/reset_password.php?token=' . urlencode($token);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'JCDA Password Reset Request';
        $mail->Body = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="https://jcda.com.ng/assets/images/logo.png" alt="JCDA Logo" style="max-width: 150px;">
            </div>
            <h2 style="color: #00a86b;">Password Reset Request</h2>
            <p>Hello,</p>
            <p>We received a request to reset your password for your JCDA account. Click the button below to reset your password:</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$reset_url}" style="background-color: #00a86b; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Reset Your Password</a>
            </div>
            <p>This link will expire in 1 hour for security reasons.</p>
            <p>If you did not request a password reset, you can safely ignore this email. Someone may have entered your email address by mistake.</p>
            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
            <p style="font-size: 12px; color: #777; text-align: center;">This is an automated email from JCDA. Please do not reply to this email.</p>
        </div>
HTML;

        $mail->AltBody = <<<TEXT
        Password Reset Request
        
        Hello,
        
        We received a request to reset your password for your JCDA account. Please click the link below to reset your password:
        
        {$reset_url}
        
        This link will expire in 1 hour for security reasons.
        
        If you did not request a password reset, you can safely ignore this email. Someone may have entered your email address by mistake.
        
        This is an automated email from JCDA. Please do not reply to this email.
TEXT;

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_error("Failed to send password reset email: {$mail->ErrorInfo}");
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Reset your JCDA account password">
    <title>JCDA - Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00a86b;
            --primary-hover: #008f5b;
            --error-color: #dc3545;
            --success-color: #28a745;
            --light-bg: #f8f9fa;
            --border-color: #ddd;
            --text-color: #333;
            --text-muted: #6c757d;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            --border-radius: 0.5rem;
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .container {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            width: 90%;
            max-width: 1000px;
            box-shadow: var(--box-shadow);
        }
        
        .left-side {
            background-color: #e6f7f0;
            padding: 2.5rem;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .right-side {
            padding: 2.5rem;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo img {
            max-width: 120px;
            height: auto;
            margin-bottom: 1.5rem;
        }
        
        h2 {
            color: var(--text-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        p {
            margin-bottom: 1.5rem;
            color: var(--text-muted);
        }
        
        form {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        
        label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        input[type="email"] {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: calc(var(--border-radius) / 2);
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
        }
        
        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(0, 168, 107, 0.25);
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: calc(var(--border-radius) / 2);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        button:hover {
            background-color: var(--primary-hover);
        }
        
        .error {
            color: var(--error-color);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: calc(var(--border-radius) / 2);
        }
        
        .success {
            color: var(--success-color);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: calc(var(--border-radius) / 2);
        }
        
        .links {
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .links a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 95%;
                max-width: 500px;
            }
            
            .left-side, .right-side {
                width: 100%;
                padding: 1.5rem;
            }
            
            .left-side {
                display: none;
            }
        }
        
        /* Accessibility improvements */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        /* Focus visibility for accessibility */
        a:focus, button:focus, input:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-side">
            <div class="logo">
                <img src="/assets/images/logo.png" alt="JCDA Logo">
            </div>
            <img src="/assets/images/forgot-password.svg" alt="Password Reset Illustration" style="max-width: 80%;">
        </div>
        <div class="right-side">
            <h2>Forgot Your Password?</h2>
            <p>Enter your email address below and we'll send you instructions to reset your password.</p>
            
            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success" role="alert"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$email_sent): ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your registered email address"
                        autocomplete="email" 
                        required
                        aria-required="true"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                    
                    <button type="submit">Send Reset Instructions</button>
                </form>
            <?php endif; ?>
            
            <div class="links">
                <a href="login.php">Back to Login</a>
                <span class="mx-2">•</span>
                <a href="../index.php">Return to Homepage</a>
            </div>
        </div>
    </div>

    <?php if (!$email_sent): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Simple email validation
            if (!emailInput.value.trim()) {
                isValid = false;
                showError(emailInput, 'Email address is required');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
                isValid = false;
                showError(emailInput, 'Please enter a valid email address');
            } else {
                removeError(emailInput);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Show error message
        function showError(input, message) {
            removeError(input);
            input.classList.add('is-invalid');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            errorDiv.style.color = 'var(--error-color)';
            errorDiv.style.fontSize = '0.875rem';
            errorDiv.style.marginTop = '-0.5rem';
            errorDiv.style.marginBottom = '0.75rem';
            
            input.parentNode.insertBefore(errorDiv, input.nextSibling);
        }
        
        // Remove error message
        function removeError(input) {
            input.classList.remove('is-invalid');
            const errorMessage = input.nextElementSibling;
            if (errorMessage && errorMessage.className === 'error-message') {
                errorMessage.remove();
            }
        }
        
        // Validate on input
        emailInput.addEventListener('input', function() {
            if (this.value.trim()) {
                removeError(this);
            }
        });
        
        // Focus the email input on page load
        emailInput.focus();
    });
    </script>
    <?php endif; ?>
</body>
</html>