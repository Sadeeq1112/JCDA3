<?php
// filepath: /Users/user/Desktop/JCDA3/dashboard/public/login.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Secure session initialization
if (session_status() == PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Force HTTPS in production
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect");
    exit;
}

// IP-based rate limiting
$ip_address = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = '../logs/login_attempts.json';
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes in seconds

// Create or load the rate limiting data
if (!file_exists($rate_limit_file)) {
    @file_put_contents($rate_limit_file, json_encode([]));
    @chmod($rate_limit_file, 0600); // Secure permissions
}

$attempts = json_decode(file_get_contents($rate_limit_file), true) ?: [];
$is_locked = false;
$wait_time = 0;

// Check if IP is locked out
if (isset($attempts[$ip_address])) {
    $ip_data = $attempts[$ip_address];
    $time_passed = time() - $ip_data['timestamp'];
    
    if ($ip_data['count'] >= $max_attempts && $time_passed < $lockout_time) {
        $is_locked = true;
        $wait_time = $lockout_time - $time_passed;
    } elseif ($time_passed >= $lockout_time) {
        // Reset attempts after lockout period
        unset($attempts[$ip_address]);
    }
}

// CSRF token generation with expiration
if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
    (time() - $_SESSION['csrf_token_time']) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Initialize variables
$error = '';
$username = '';
$remember_me = false;

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
} 

// Check for remember_me cookie
if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
    try {
        // Validate the remember me cookie
        list($selector, $authenticator) = explode(':', $_COOKIE['remember_me']);
        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = :selector AND expires > NOW()");
        $stmt->execute(['selector' => $selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token && hash_equals($token['token'], hash('sha256', base64_decode($authenticator)))) {
            // Get user info
            $stmt = $pdo->prepare("SELECT id, username, email, status FROM users WHERE id = :user_id");
            $stmt->execute(['user_id' => $token['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['status'] === 'active') {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                
                // Log the successful login
                $log_message = "User {$user['username']} (ID: {$user['id']}) logged in via remember_me cookie";
                error_log($log_message);
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                // Invalid user or inactive account, delete the token
                $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = :selector");
                $stmt->execute(['selector' => $selector]);
                setcookie('remember_me', '', time() - 3600, '/', '', true, true);
            }
        } else {
            // Invalid token, delete cookie
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) {
        // Log the error but don't expose details
        error_log("Remember me cookie validation failed: " . $e->getMessage());
    }
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_locked) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Input validation
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            try {
                // Get user by username or email
                $stmt = $pdo->prepare("SELECT id, username, email, password, status, failed_attempts, 
                                     last_failed_login FROM users WHERE username = :username OR email = :email");
                $stmt->execute(['username' => $username, 'email' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Track login attempt for rate limiting
                if (!isset($attempts[$ip_address])) {
                    $attempts[$ip_address] = ['count' => 0, 'timestamp' => time()];
                }
                $attempts[$ip_address]['count']++;
                $attempts[$ip_address]['timestamp'] = time();
                file_put_contents($rate_limit_file, json_encode($attempts));
                
                if (!$user) {
                    $error = "Invalid username or password.";
                    // Log failed attempt with username
                    error_log("Failed login attempt for username: $username from IP: $ip_address");
                } elseif ($user['status'] !== 'active') {
                    $error = "This account is not active. Please contact support.";
                } elseif (password_verify($password, $user['password'])) {
                    // Valid login - reset rate limiting for this IP
                    unset($attempts[$ip_address]);
                    file_put_contents($rate_limit_file, json_encode($attempts));
                    
                    // Reset failed attempts counter
                    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, last_failed_login = NULL 
                                           WHERE id = :user_id");
                    $stmt->execute(['user_id' => $user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    // Handle "Remember Me" functionality
                    if ($remember_me) {
                        // Generate secure tokens
                        $selector = bin2hex(random_bytes(16));
                        $authenticator = random_bytes(32);
                        $authenticator_hash = hash('sha256', $authenticator);
                        
                        // Store token in database (expires in 14 days)
                        $expiry = date('Y-m-d H:i:s', time() + 14 * 24 * 60 * 60);
                        $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) 
                                              VALUES (:user_id, :selector, :token, :expires)");
                        $stmt->execute([
                            'user_id' => $user['id'],
                            'selector' => $selector,
                            'token' => $authenticator_hash,
                            'expires' => $expiry
                        ]);
                        
                        // Set cookie with token (secure, httponly)
                        setcookie(
                            'remember_me',
                            $selector . ':' . base64_encode($authenticator),
                            time() + 14 * 24 * 60 * 60, // 14 days
                            '/',
                            '',
                            true,    // Secure
                            true     // HttpOnly
                        );
                    }
                    
                    // Log successful login
                    $log_message = "User {$user['username']} (ID: {$user['id']}) logged in successfully";
                    error_log($log_message);
                    
                    // Check if user needs to complete profile
                    $stmt = $pdo->prepare("SELECT updated FROM profiles WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$profile || $profile['updated'] != 1) {
                        header("Location: profile.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                } else {
                    $error = "Invalid username or password.";
                    
                    // Update failed login attempts
                    $failed_attempts = ($user['failed_attempts'] ?? 0) + 1;
                    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = :attempts, 
                                         last_failed_login = NOW() WHERE id = :user_id");
                    $stmt->execute([
                        'attempts' => $failed_attempts,
                        'user_id' => $user['id']
                    ]);
                    
                    // Log failed attempt
                    error_log("Failed login attempt #{$failed_attempts} for user: {$user['username']} from IP: {$ip_address}");
                    
                    // After 10 failed attempts, lock the account
                    if ($failed_attempts >= 10) {
                        $stmt = $pdo->prepare("UPDATE users SET status = 'locked', failed_attempts = 0 
                                               WHERE id = :user_id");
                        $stmt->execute(['user_id' => $user['id']]);
                        $error = "This account has been locked due to too many failed login attempts. Please contact support.";
                        error_log("Account locked for user: {$user['username']} after {$failed_attempts} failed attempts.");
                    }
                }
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Login</title>
    <link rel="icon" href="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" type="image/png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #378349;
            --secondary-color: #2c6b3c;
            --background-color: #f8f9fa;
            --text-color: #333;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition-speed: 0.3s;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Background gradient shapes */
        body::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(55, 131, 73, 0.3), rgba(44, 107, 60, 0.1));
            z-index: -1;
            filter: blur(30px);
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(55, 131, 73, 0.2), rgba(44, 107, 60, 0.05));
            z-index: -1;
            filter: blur(20px);
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
            position: relative;
            z-index: 1;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            background: linear-gradient(145deg, var(--primary-color), var(--secondary-color));
            padding: 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: linear-gradient(rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            z-index: 1;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .login-form {
            padding: 2rem;
        }

        .form-control {
            border-radius: var(--border-radius);
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            transition: all var(--transition-speed);
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(55, 131, 73, 0.25);
            transform: translateY(-2px);
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group-prepend {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            padding-left: 1rem;
            color: #6c757d;
        }

        .input-group .form-control {
            padding-left: 2.8rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all var(--transition-speed);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-block {
            width: 100%;
        }

        .login-footer {
            padding: 1rem 2rem;
            background-color: #f8f9fa;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }

        .toggle-password:focus {
            outline: none;
        }

        .alert {
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background-color: #fff2f2;
            border-color: #ffdddd;
            color: #d9534f;
        }

        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .custom-control-label {
            cursor: pointer;
            user-select: none;
        }

        .countdown {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: #dc3545;
            color: white;
            border-radius: 3px;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            body {
                background: white;
                padding: 1rem;
            }
            
            .login-container {
                box-shadow: none;
            }
            
            .login-header {
                border-radius: var(--border-radius) var(--border-radius) 0 0;
            }
            
            .login-form {
                padding: 1.5rem;
            }
        }

        /* Accessibility focus indicators */
        a:focus, button:focus, input:focus, .btn:focus {
            outline: 3px solid rgba(55, 131, 73, 0.3);
            outline-offset: 3px;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 5px;
            margin-top: 5px;
            margin-bottom: 15px;
            border-radius: 2px;
            transition: all var(--transition-speed);
        }
        
        .password-strength.weak { background-color: #ff4d4d; width: 25%; }
        .password-strength.medium { background-color: #ffaa00; width: 50%; }
        .password-strength.strong { background-color: #73c973; width: 75%; }
        .password-strength.very-strong { background-color: #00b300; width: 100%; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" alt="JCDA Logo" class="logo">
            <h1>Member Login</h1>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_locked): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-lock mr-2"></i>Too many login attempts. Please wait 
                    <span class="countdown" id="lockout-countdown"><?php echo ceil($wait_time / 60); ?></span> 
                    minutes before trying again.
                </div>
                <script>
                    // Countdown timer for lockout
                    let waitTime = <?php echo $wait_time; ?>;
                    const countdownEl = document.getElementById('lockout-countdown');
                    
                    const updateCountdown = () => {
                        const minutes = Math.ceil(waitTime / 60);
                        countdownEl.textContent = minutes;
                        waitTime--;
                        
                        if (waitTime < 0) {
                            location.reload();
                        }
                    };
                    
                    setInterval(updateCountdown, 1000);
                </script>
            <?php else: ?>
                <form action="login.php" method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Username or Email" 
                            value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        <div class="invalid-feedback">Please enter your username or email.</div>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                        <button type="button" class="toggle-password" aria-label="Show/Hide Password">
                            <i class="far fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>
                    
                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="remember_me" id="remember_me" class="custom-control-input"
                                <?php echo $remember_me ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="remember_me">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="text-primary">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="login-footer">
            <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary font-weight-bold">Register here</a></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            const togglePassword = document.querySelector('.toggle-password');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const passwordInput = document.getElementById('password');
                    const icon = this.querySelector('i');
                    
                    // Toggle password visibility
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
            
            // Form validation
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(event) {
                    if (!this.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    const username = document.getElementById('username');
                    const password = document.getElementById('password');
                    
                    // Add visual feedback
                    if (username.value.trim() === '') {
                        username.classList.add('is-invalid');
                    } else {
                        username.classList.remove('is-invalid');
                    }
                    
                    if (password.value.trim() === '') {
                        password.classList.add('is-invalid');
                    } else {
                        password.classList.remove('is-invalid');
                    }
                    
                    this.classList.add('was-validated');
                });
                
                // Remove invalid feedback on input
                const inputs = loginForm.querySelectorAll('input');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim() !== '') {
                            this.classList.remove('is-invalid');
                        }
                    });
                });
            }
            
            // Add login button animation
            const loginButton = document.querySelector('.btn-primary');
            if (loginButton) {
                loginButton.addEventListener('click', function() {
                    if (loginForm && loginForm.checkValidity()) {
                        this.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Signing in...';
                        this.disabled = true;
                    }
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(:has(#lockout-countdown))');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>