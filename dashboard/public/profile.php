<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Add the 'updated' column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE profiles ADD COLUMN updated TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {
    // Ignore the error if the column already exists
    if ($e->getCode() != '42S21') {
        throw $e;
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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
    $profile_picture = $_FILES['profile_picture'];

    if (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        // Handle profile picture upload
        if ($profile_picture['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $upload_file = $upload_dir . basename($profile_picture['name']);
            if (move_uploaded_file($profile_picture['tmp_name'], $upload_file)) {
                $profile_picture_path = $upload_file;
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $profile_picture_path = $profile['profile_picture'] ?? null;
        }

        if ($profile) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE profiles SET full_name = ?, address = ?, phone = ?, date_of_birth = ?, occupation = ?, profile_picture = ?, updated = 1 WHERE user_id = ?");
            $params = [$full_name, $address, $phone, $date_of_birth, $occupation, $profile_picture_path, $user_id];
        } else {
            // Insert new profile
            $stmt = $pdo->prepare("INSERT INTO profiles (full_name, address, phone, date_of_birth, occupation, profile_picture, user_id, updated) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $params = [$full_name, $address, $phone, $date_of_birth, $occupation, $profile_picture_path, $user_id];
        }

        if ($stmt->execute($params)) {
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

// Determine if the fields should be read-only
$readonly = $profile && $profile['updated'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Profile</title>
    <link rel="icon" href="public/JCDA White.png" type="image/png">
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
            width: 200px; /* Default to expanded width */
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
        .sidebar .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .sidebar .logo img {
            max-width: 100px;
            height: auto;
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
            display: inline;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            margin: 20px;
            margin-left: 220px; /* Adjusted for sidebar */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: margin-left 0.3s;
        }
        .main-content.expanded {
            margin-left: 220px; /* Adjusted for expanded sidebar */
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
            cursor: pointer;
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
        .readonly {
            background-color: #e9ecef;
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
                margin-left: 220px; /* Adjusted for expanded sidebar */
            }
            .header h1 {
                font-size: 1.2rem; /* Reduced font size for mobile */
            }
        }
    </style>
    <script>
        // Function to show tooltips
        function showTooltip(element, message) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.innerText = message;
            document.body.appendChild(tooltip);
            const rect = element.getBoundingClientRect();
            tooltip.style.left = `${rect.left + window.scrollX + element.offsetWidth / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight - 5}px`;
            element.addEventListener('mouseleave', () => {
                tooltip.remove();
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('mouseenter', () => {
                    if (!document.querySelector('.sidebar').classList.contains('expanded')) {
                        showTooltip(link, link.querySelector('.sidebar-text').innerText);
                    }
                });
            });

            // Toggle sidebar on button click
            document.getElementById('toggleSidebar').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('hidden');
                document.getElementById('sidebar').classList.toggle('expanded');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            // Redirect to profile update page on profile image click
            document.querySelector('.user-profile').addEventListener('click', function() {
                window.location.href = 'profile.php';
            });
        });
    </script>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <img src="public/JCDA White.png" alt="JCDA Logo">
            </div>
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
                    <img src="<?php echo $profile ? htmlspecialchars($profile['profile_picture']) : '../assets/images/useravatar.jpg'; ?>" alt="User profile">
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
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="full_name">Full Name:</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo $profile ? htmlspecialchars($profile['full_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" class="form-control"><?php echo $profile ? htmlspecialchars($profile['address']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" class="form-control <?php echo $readonly ? 'readonly' : ''; ?>" value="<?php echo $profile ? htmlspecialchars($profile['phone']) : ''; ?>" <?php echo $readonly ? 'readonly' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth:</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control <?php echo $readonly ? 'readonly' : ''; ?>" value="<?php echo $profile ? $profile['date_of_birth'] : ''; ?>" <?php echo $readonly ? 'readonly' : ''; ?> onfocus="this.blur()">
                    </div>
                    <div class="form-group">
                        <label for="occupation">Occupation:</label>
                        <input type="text" id="occupation" name="occupation" class="form-control" value="<?php echo $profile ? htmlspecialchars($profile['occupation']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture:</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control-file">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
                <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
            </section>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>