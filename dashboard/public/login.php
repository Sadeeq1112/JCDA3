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
            <h2>Register for JCDA</h2>
            <p>Welcome! Please fill in the form to create an account.</p>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" id="password" name="password" placeholder="Password" required>
                <div class="password-strength" id="password-strength">
                    <span></span>
                </div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit" id="register-button" disabled>Register</button>
            </form>
            <div class="links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
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
    </script>
</body>
</html>