<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user profile information
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $occupation = trim($_POST['occupation']);

    if (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        if ($profile) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE profiles SET full_name = ?, address = ?, phone = ?, date_of_birth = ?, occupation = ? WHERE user_id = ?");
        } else {
            // Insert new profile
            $stmt = $pdo->prepare("INSERT INTO profiles (full_name, address, phone, date_of_birth, occupation, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        }

        if ($stmt->execute([$full_name, $address, $phone, $date_of_birth, $occupation, $user_id])) {
            $success = "Profile updated successfully.";
            // Refresh profile data
            $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Your Profile</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="profile.php" method="POST">
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo $profile ? htmlspecialchars($profile['full_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address"><?php echo $profile ? htmlspecialchars($profile['address']) : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" value="<?php echo $profile ? htmlspecialchars($profile['phone']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $profile ? $profile['date_of_birth'] : ''; ?>">
            </div>
            <div class="form-group">
                <label for="occupation">Occupation:</label>
                <input type="text" id="occupation" name="occupation" value="<?php echo $profile ? htmlspecialchars($profile['occupation']) : ''; ?>">
            </div>
            <button type="submit">Update Profile</button>
        </form>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
    <script src="assets/js/profile-validation.js"></script>
</body>
</html>