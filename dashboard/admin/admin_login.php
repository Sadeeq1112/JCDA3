<?php
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

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Make sure this file exists

// Define the log_activity function if it doesn't exist
if (!function_exists('log_activity')) {
    /**
     * Log admin activities to the database
     * 
     * @param string $admin_username The username of the admin
     * @param string $action The action performed
     * @param string $details Additional details about the action
     * @return bool Success or failure
     */
    function log_activity($admin_username, $action, $details = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $admin_username,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            return true;
        } catch (PDOException $e) {
            // Just log the error, don't stop execution
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}

// Force HTTPS in production
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Add protection against brute force attacks
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes in seconds
$ip_address = $_SERVER['REMOTE_ADDR'];

// Create or load the rate limiting data
$rate_limit_file = '../logs/admin_login_attempts.json';
if (!file_exists(dirname($rate_limit_file))) {
    mkdir(dirname($rate_limit_file), 0755, true);
}

if (!file_exists($rate_limit_file)) {
    @file_put_contents($rate_limit_file, json_encode([]));
    @chmod($rate_limit_file, 0600); // Secure permissions
}

$attempts = json_decode(@file_get_contents($rate_limit_file), true) ?: [];
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

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';

// Generate CSRF token with expiration
if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
    (time() - $_SESSION['csrf_token_time']) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_locked) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
        
        // Log potential CSRF attack
        error_log("Potential CSRF attack on admin login. IP: $ip_address, User Agent: {$_SERVER['HTTP_USER_AGENT']}");
    } else {
        // Sanitize inputs
        $username = filter_input(INPUT_POST, 'username'); 
        if ($username !== null && $username !== false) {
            $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        } else {
            $username = '';
        }
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            try {
                // Track login attempt for rate limiting
                if (!isset($attempts[$ip_address])) {
                    $attempts[$ip_address] = ['count' => 0, 'timestamp' => time()];
                }
                $attempts[$ip_address]['count']++;
                $attempts[$ip_address]['timestamp'] = time();
                file_put_contents($rate_limit_file, json_encode($attempts));
                
                // Fetch admin user securely
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    // Successful login - reset rate limiting
                    unset($attempts[$ip_address]);
                    file_put_contents($rate_limit_file, json_encode($attempts));
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_role'] = $admin['role']; // Assuming you have roles
                    $_SESSION['admin_last_activity'] = time();

                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_time'] = time();

                    // Log successful login
                    log_activity($admin['username'], 'Login', 'Successful login to admin panel');
                    
                    // Update last login time
                    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$admin['id']]);

                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    // Log failed attempt with additional details
                    $log_message = "Failed admin login attempt for username: $username from IP: $ip_address";
                    error_log($log_message);
                    
                    if ($admin) {
                        // If username exists but password is wrong
                        log_activity('system', 'Failed Login', "Invalid password for admin: $username");
                    }
                    
                    $error = "Invalid username or password.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
                error_log("Admin login error: " . $e->getMessage());
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
    <title>JCDA Admin Panel | Login</title>
    <link rel="icon" href="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" type="image/png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #378349;
            --primary-dark: #2c6b3c;
            --secondary: #34495e;
            --light: #f8f9fa;
            --dark: #212529;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --border-radius: 8px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Admin background patterns */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23378349' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.8;
            z-index: -1;
        }

        .admin-login-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .admin-card {
            background-color: #fff;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .admin-header {
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            padding: 1.5rem;
            text-align: center;
            color: white;
        }

        .admin-header img {
            width: 80px;
            height: auto;
            margin-bottom: 15px;
        }

        .admin-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .admin-header p {
            margin-top: 10px;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .admin-body {
            padding: 2rem;
        }

        .admin-alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .admin-alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .admin-alert-danger {
            background-color: #fff5f5;
            border-left: 4px solid var(--danger);
            color: #c53030;
        }

        .admin-alert-warning {
            background-color: #fffbeb;
            border-left: 4px solid var(--warning);
            color: #b45309;
        }

        .form-control {
            height: auto;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(55, 131, 73, 0.15);
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-right: none;
            color: #64748b;
        }

        .input-group-text + .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .btn-admin {
            background-color: var(--primary);
            border: none;
            color: white;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: block;
            width: 100%;
        }

        .btn-admin:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(55, 131, 73, 0.3);
        }

        .admin-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            padding: 1rem;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
        }

        .admin-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .admin-footer a:hover {
            text-decoration: underline;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            cursor: pointer;
            z-index: 10;
        }

        /* Lockout countdown timer */
        .countdown-timer {
            background-color: #fff5f5;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .countdown-timer .time {
            font-size: 2rem;
            color: var(--danger);
            font-weight: 700;
            margin: 10px 0;
        }

        .countdown-timer p {
            margin: 0;
            color: #64748b;
        }

        /* Accessibility enhancements */
        .visually-hidden {
            clip: rect(0 0 0 0);
            clip-path: inset(50%);
            height: 1px;
            overflow: hidden;
            position: absolute;
            white-space: nowrap;
            width: 1px;
        }

        /* Responsive media queries */
        @media (max-width: 576px) {
            .admin-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-card">
            <div class="admin-header">
                <img src="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" alt="JCDA Logo">
                <h2>Administrator Login</h2>
                <p>Secure access to JCDA management system</p>
            </div>
            
            <div class="admin-body">
                <?php if ($is_locked): ?>
                <div class="countdown-timer" id="lockout-timer">
                    <i class="fas fa-lock fa-2x" style="color: #dc3545;"></i>
                    <p>Too many failed attempts</p>
                    <div class="time" id="countdown-time">
                        <?php echo floor($wait_time / 60) . ':' . str_pad($wait_time % 60, 2, '0', STR_PAD_LEFT); ?>
                    </div>
                    <p>Please try again later</p>
                </div>
                <script>
                    // Countdown for lockout timer
                    let waitTime = <?php echo $wait_time; ?>;
                    const updateTimer = () => {
                        if (waitTime <= 0) {
                            location.reload();
                            return;
                        }
                        
                        const minutes = Math.floor(waitTime / 60);
                        const seconds = waitTime % 60;
                        document.getElementById('countdown-time').textContent = 
                            minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                        
                        waitTime--;
                    };
                    updateTimer();
                    setInterval(updateTimer, 1000);
                </script>
                <?php else: ?>
                
                <?php if ($error): ?>
                <div class="admin-alert admin-alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form action="admin_login.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Administrator Username" 
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                            required 
                            autocomplete="username"
                            aria-label="Username"
                            autofocus
                        >
                    </div>
                    
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Password" 
                            required 
                            autocomplete="current-password"
                            aria-label="Password"
                        >
                        <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="far fa-eye"></i>
                            <span class="visually-hidden">Show password</span>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn-admin">
                        <i class="fas fa-sign-in-alt mr-2"></i> Secure Login
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="admin-footer">
                <p>JCDA Administrator Panel &copy; <?php echo date('Y'); ?></p>
                <p>If you need assistance, please <a href="mailto:support@jcda.org">contact support</a>.</p>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                document.querySelector('.visually-hidden').textContent = 'Hide password';
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                document.querySelector('.visually-hidden').textContent = 'Show password';
            }
        }
        
        // Add form validation
        document.querySelector('form').addEventListener('submit', function(event) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                event.preventDefault();
                
                // Create alert if it doesn't exist
                if (!document.querySelector('.admin-alert')) {
                    const alert = document.createElement('div');
                    alert.className = 'admin-alert admin-alert-danger';
                    alert.role = 'alert';
                    alert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please enter both username and password.';
                    
                    const form = document.querySelector('form');
                    form.parentNode.insertBefore(alert, form);
                }
            }
        });
        
        // Add loading state to button on submission
        document.querySelector('form').addEventListener('submit', function(event) {
            if (this.checkValidity()) {
                document.querySelector('.btn-admin').innerHTML = 
                    '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span> Authenticating...';
                document.querySelector('.btn-admin').disabled = true;
            }
        });
    </script>
</body>
</html>