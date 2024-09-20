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

if (isset($_SESSION['user_id'])) {
    $error = "You are already logged in.";
} else {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // CSRF token validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid CSRF token.";
        } else {
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];

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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Login</title>
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
        .toggle-password {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .toggle-password input {
            margin-right: 5px;
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
            <h2>Sign in</h2>
            <p>Welcome back! Please log in using the details you entered during registration.</p>
            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <form action="login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username or email" required aria-required="true">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required aria-required="true">
                    <div class="toggle-password">
                        <input type="checkbox" id="toggle-password-visibility">
                        <label for="toggle-password-visibility">Show Password</label>
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
            <?php endif; ?>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordVisibility = document.getElementById('toggle-password-visibility');

        togglePasswordVisibility.addEventListener('change', function() {
            const type = togglePasswordVisibility.checked ? 'text' : 'password';
            passwordInput.type = type;
        });
    </script>
</body>
</html>