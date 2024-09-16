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
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 1000px;
        }
        .left-side {
            background-color: #ffe6e6;
            padding: 40px;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .right-side {
            padding: 40px;
            width: 50%;
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
    </style>
</head>
<body>
    <?php
    require_once '../includes/config.php';
    require_once '../includes/db.php';
    require_once '../includes/functions.php';

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Both username and password are required.";
        } else {
            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
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
    ?>
    <div class="container">
        <div class="left-side">
            <div class="logo">
                <img src="/api/placeholder/100/50" alt="3MTT Logo">
            </div>
            <img src="/api/placeholder/400/300" alt="Illustration" style="max-width: 100%;">
        </div>
        <div class="right-side">
            <h2>Sign in</h2>
            <p>Welcome back! Please log in using the details you entered during registration.</p>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <input type="text" name="username" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <label>
                    <input type="checkbox" name="remember"> Remember Me
                </label>
                <button type="submit">Login</button>
            </form>
            <div class="links">
                <p>Don't have an account? <a href="#">Register here</a></p>
                <p>Don't have your password? <a href="#">Complete Registration</a></p>
                <p><a href="#">See all selected fellows</a></p>
                <p><a href="#">View 3MTT Jobs</a></p>
            </div>
        </div>
    </div>
</body>
</html>