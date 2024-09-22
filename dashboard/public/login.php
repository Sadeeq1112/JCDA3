<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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

// Check if user is already logged in via session or cookie
if (isset($_SESSION['user_id'])) {
    $error = "You are already logged in.";
} elseif (isset($_COOKIE['remember_me'])) {
    // Validate the remember me cookie
    list($selector, $authenticator) = explode(':', $_COOKIE['remember_me']);
    $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = :selector");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token && hash_equals($token['token'], hash('sha256', $authenticator)) && $token['expires'] >= date('Y-m-d H:i:s')) {
        // Log the user in
        $_SESSION['user_id'] = $token['user_id'];
        header("Location: dashboard.php");
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            $error = "Both username and password are required.";
        } else {
            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
                $stmt->execute(['username' => $username, 'email' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    if ($remember) {
                        // Generate a new remember me token
                        $selector = bin2hex(random_bytes(8));
                        $authenticator = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                        // Store the token in the database
                        $stmt = $pdo->prepare("INSERT INTO auth_tokens (selector, token, user_id, expires) VALUES (:selector, :token, :user_id, :expires)");
                        $stmt->execute([
                            'selector' => $selector,
                            'token' => hash('sha256', $authenticator),
                            'user_id' => $user['id'],
                            'expires' => $expires
                        ]);

                        // Set the cookie
                        setcookie(
                            'remember_me',
                            $selector . ':' . $authenticator,
                            time() + 86400 * 30, // 30 days
                            '/',
                            $_SERVER['HTTP_HOST'],
                            isset($_SERVER['HTTPS']),
                            true // HTTP only
                        );
                    }

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred while processing your request. Please try again later.";
                log_error($e->getMessage()); // Log the error message for debugging
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
            <h2>Welcome Back!</h2>
            <p>Log in to access your account and continue where you left off.</p>
        </div>
        <div class="right-side">
            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <?php if (!empty($error)): ?>
                    <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <label for="username">Username or Email</label>
                <div class="input-container">
                    <input type="text" id="username" name="username" placeholder="Enter your username or email" required aria-required="true">
                </div>
                
                <label for="password">Password</label>
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required aria-required="true">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fa fa-eye-slash"></i></span>
                </div>
                
                <label>
                    <input type="checkbox" name="remember"> Remember Me
                </label>
                
                <button type="submit">Login</button>
            </form>
            <div class="links">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p>Forgot your password? <a href="forgot_password.php">Reset Password</a></p>
            </div>
        </div>
    </div>
    <script>
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