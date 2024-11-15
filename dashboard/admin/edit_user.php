<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
$success = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: admin_dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate input
    $firstname = htmlspecialchars($_POST['firstname']);
    $surname = htmlspecialchars($_POST['surname']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $state = htmlspecialchars($_POST['state']);

    if (!$firstname || !$surname || !$email || !$state) {
        $error = 'Please fill in all required fields.';
    } else {
        // Update user data
        $updateStmt = $pdo->prepare("UPDATE profiles SET firstname = ?, surname = ?, email = ?, state = ? WHERE user_id = ?");
        if ($updateStmt->execute([$firstname, $surname, $email, $state, $user_id])) {
            $success = 'User updated successfully.';
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'An error occurred. Please try again.';
        }
    }
}

// Define states array
$states = array_keys($states_lgas);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <!-- Include Bootstrap CSS or any other stylesheets you prefer -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* Styles similar to admin_login.php */
        body {
            background-color: #f8f9fa;
        }
        .edit-container {
            margin-top: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .edit-form {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
        }
        .btn-primary {
            background-color: #378349;
            border: none;
        }
        .btn-primary:hover {
            background-color: #2c6b3c;
        }
    </style>
</head>
<body>
    <div class="container edit-container">
        <div class="edit-form">
            <h2>Edit User</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form action="edit_user.php?user_id=<?php echo $user_id; ?>" method="POST">
                <div class="form-group">
                    <label for="firstname">First Name:</label>
                    <input type="text" name="firstname" id="firstname" class="form-control" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="surname">Surname:</label>
                    <input type="text" name="surname" id="surname" class="form-control" value="<?php echo htmlspecialchars($user['surname']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="state">State:</label>
                    <select name="state" id="state" class="form-control" required>
                        <option value="">Select State</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo htmlspecialchars($state); ?>" <?php if ($user['state'] == $state) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Add more fields as needed -->
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </form>
        </div>
    </div>
</body>
</html>