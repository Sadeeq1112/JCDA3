<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com');

// CSRF token generation - use existing or generate new
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $token = sanitize_input($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($token) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!validate_password_strength($password)) {
            $error = "Password does not meet security requirements.";
        } else {
            try {
                // Use prepared statements with parameter binding
                $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token AND expiry > NOW() LIMIT 1");
                $stmt->execute(['token' => $token]);
                $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($reset) {
                    // Hash the new password with appropriate cost
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

                    // Begin transaction for atomic operations
                    $pdo->beginTransaction();
                    
                    try {
                        // Update the user's password
                        $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email");
                        $stmt->execute(['password' => $hashed_password, 'email' => $reset['email']]);
                        
                        // Delete the reset token
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                        $stmt->execute(['token' => $token]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Clear session data related to password reset
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        
                        $success = "Your password has been reset successfully. You can now <a href='login.php'>login</a>.";
                    } catch (PDOException $e) {
                        // Roll back transaction on error
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    $error = "Invalid or expired token.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred while processing your request. Please try again later.";
                log_error($e->getMessage()); // Log the error message for debugging
            }
        }
    }
}

/**
 * Validate password strength
 * @param string $password
 * @return bool
 */
function validate_password_strength($password) {
    // Password must be at least 8 characters long and include:
    // - Uppercase letter
    // - Lowercase letter
    // - Number
    // - Special character
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           preg_match('/[^A-Za-z0-9]/', $password);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Reset your JCDA account password">
    <title>JCDA - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* CSS optimized for better readability and maintainability */
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
            margin-bottom: 1.5rem;
            font-weight: 600;
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
        
        input[type="password"],
        input[type="text"] {
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
        
        button:disabled {
            background-color: var(--border-color);
            cursor: not-allowed;
            opacity: 0.7;
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
        
        .password-hints {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .password-strength {
            margin-bottom: 1rem;
            height: 6px;
            background-color: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: var(--transition);
        }
        
        .password-strength span.weak {
            background-color: var(--error-color);
            width: 33%;
        }
        
        .password-strength span.medium {
            background-color: #ffc107;
            width: 66%;
        }
        
        .password-strength span.strong {
            background-color: var(--success-color);
            width: 100%;
        }
        
        .toggle-password {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .toggle-password input {
            margin-right: 0.5rem;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="left-side">
            <div class="logo">
                <img src="/assets/images/logo.png" alt="JCDA Logo">
            </div>
            <img src="/assets/images/reset-password.svg" alt="Reset Password Illustration" style="max-width: 80%;">
        </div>
        <div class="right-side">
            <h2>Reset Your Password</h2>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="reset-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required 
                           aria-describedby="password-hints">
                    
                    <div class="password-hints" id="password-hints">
                        Password must be at least 8 characters long and include uppercase letters, 
                        lowercase letters, numbers, and special characters.
                    </div>
                    
                    <div class="password-strength">
                        <span id="password-strength-meter"></span>
                    </div>
                    
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           autocomplete="new-password" required>
                    
                    <div class="toggle-password">
                        <input type="checkbox" id="toggle-password-visibility">
                        <label for="toggle-password-visibility">Show Password</label>
                    </div>
                    
                    <button type="submit" id="reset-button" disabled>Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cache DOM elements
        const form = document.getElementById('reset-form');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrengthMeter = document.getElementById('password-strength-meter');
        const resetButton = document.getElementById('reset-button');
        const togglePasswordVisibility = document.getElementById('toggle-password-visibility');
        
        // Password validation criteria
        const criteria = {
            length: { regex: /.{8,}/, weight: 1 },
            uppercase: { regex: /[A-Z]/, weight: 1 },
            lowercase: { regex: /[a-z]/, weight: 1 },
            numbers: { regex: /[0-9]/, weight: 1 },
            special: { regex: /[^A-Za-z0-9]/, weight: 1 }
        };
        
        // Functions
        function validatePassword() {
            const value = passwordInput.value;
            let score = 0;
            let maxScore = 0;
            
            // Calculate password strength
            Object.values(criteria).forEach(criterion => {
                maxScore += criterion.weight;
                if (criterion.regex.test(value)) {
                    score += criterion.weight;
                }
            });
            
            const percentScore = (score / maxScore) * 100;
            
            // Update UI according to strength
            passwordStrengthMeter.style.width = percentScore + '%';
            passwordStrengthMeter.className = '';
            
            if (percentScore < 40) {
                passwordStrengthMeter.classList.add('weak');
                return false;
            } else if (percentScore < 80) {
                passwordStrengthMeter.classList.add('medium');
                return percentScore >= 60; // Only medium-strong is acceptable
            } else {
                passwordStrengthMeter.classList.add('strong');
                return true;
            }
        }
        
        function validateForm() {
            const isPasswordValid = validatePassword();
            const doPasswordsMatch = passwordInput.value === confirmPasswordInput.value;
            
            resetButton.disabled = !(isPasswordValid && doPasswordsMatch && 
                                   passwordInput.value.length > 0 && 
                                   confirmPasswordInput.value.length > 0);
        }
        
        // Event listeners
        passwordInput.addEventListener('input', validateForm);
        confirmPasswordInput.addEventListener('input', validateForm);
        
        togglePasswordVisibility.addEventListener('change', function() {
            const type = this.checked ? 'text' : 'password';
            passwordInput.type = type;
            confirmPasswordInput.type = type;
        });
        
        form.addEventListener('submit', function(e) {
            if (!validatePassword()) {
                e.preventDefault();
                alert('Please choose a stronger password.');
            }
        });
        
        // Initial validation
        validateForm();
    });
    </script>
</body>
</html>