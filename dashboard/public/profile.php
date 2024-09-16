<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; // Ensure $username is defined

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
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f0f2f5;
        }
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 80px; /* Default to collapsed width */
            background-color: #378349;
            color: white;
            padding: 20px;
            transition: width 0.3s, transform 0.3s;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar.expanded {
            width: 80px; /* Show only icons */
        }
        .sidebar h2 {
            margin-bottom: 30px;
        }
        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }
        .sidebar li {
            margin-bottom: 20px; /* Increased margin */
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            position: relative;
        }
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 5px;
            position: relative;
            left: -6px;
        }
        .sidebar-icon {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            text-align: center; /* Align icons */
        }
        .sidebar .sidebar-text {
            display: none;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            margin: 20px;
            margin-left: 100px; /* Adjusted for sidebar */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: margin-left 0.3s;
        }
        .main-content.expanded {
            margin-left: 100px; /* Adjusted for expanded sidebar */
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 2rem; /* Default font size */
        }
        .user-profile {
            display: flex;
            align-items: center;
        }
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
        }
        .profile-summary, .payment-status {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        h2 {
            margin-bottom: 15px;
            color: #333;
        }
        p {
            margin-bottom: 10px;
            color: #666;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%); /* Hide sidebar by default */
            }
            .sidebar.expanded {
                transform: translateX(0); /* Show sidebar when expanded */
            }
            .main-content {
                margin-left: 20px; /* Adjusted for hidden sidebar */
            }
            .main-content.expanded {
                margin-left: 100px; /* Adjusted for expanded sidebar */
            }
            .header h1 {
                font-size: 1.5rem; /* Reduced font size for mobile */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar hidden" id="sidebar">
            <h2>JCDA</h2>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home sidebar-icon"></i> <span class="sidebar-text">Home</span></a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user sidebar-icon"></i> <span class="sidebar-text">Edit Profile</span></a></li>
                <li><a href="card.php"><i class="fas fa-id-card sidebar-icon"></i> <span class="sidebar-text">Membership Card</span></a></li>
                <li><a href="payment.php"><i class="fas fa-money-bill sidebar-icon"></i> <span class="sidebar-text">Pay Dues</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt sidebar-icon"></i> <span class="sidebar-text">Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content" id="mainContent">
            <div class="header">
                <button class="btn btn-primary" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="user-profile">
                    <button type="button" class="btn btn-secondary">
                        <i class="fas fa-bell"></i>
                    </button>
                    <img src="assets/images/user-avatar.jpg" class="rounded-circle" alt="User profile">
                </div>
            </div>
            <section class="profile-summary">
                <h2>Your Profile</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form id="profile-form" action="profile.php" method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name:</label>
                        <input type="text" id="full_name" name="full_name" class="form-control profile-field" value="<?php echo $profile ? htmlspecialchars($profile['full_name']) : ''; ?>" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" class="form-control profile-field" readonly><?php echo $profile ? htmlspecialchars($profile['address']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" class="form-control profile-field" value="<?php echo $profile ? htmlspecialchars($profile['phone']) : ''; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth:</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control profile-field" value="<?php echo $profile ? $profile['date_of_birth'] : ''; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="occupation">Occupation:</label>
                        <input type="text" id="occupation" name="occupation" class="form-control profile-field" value="<?php echo $profile ? htmlspecialchars($profile['occupation']) : ''; ?>" readonly>
                    </div>
                    <button type="submit" id="save-profile" class="btn btn-primary" style="display: none;">Save Profile</button>
                </form>
                <button id="edit-profile" class="btn btn-secondary mt-3">