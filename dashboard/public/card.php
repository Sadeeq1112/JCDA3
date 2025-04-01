<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$user_id = $result ? (int) $result['id'] : null;

// Fetch user information
$stmt = $pdo->prepare("SELECT u.email, p.surname, p.firstname, p.other_names, p.phone, p.occupation, p.profile_picture, p.membership_id_no, p.card_issue_date, p.card_expiry_date, p.card_issued FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default profile picture if none is set
$profile_picture = $user['profile_picture'] ?? '../assets/images/useravatar.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['membership_no'])) {
    $currentDate = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime('+1 year'));

    $stmt = $pdo->prepare("
        UPDATE profiles 
        SET 
            card_issue_date = ?,
            card_expiry_date = ?,
            card_issued = 1 
        WHERE membership_id_no = ?
    ");
    $stmt->execute([$currentDate, $expiryDate, $_POST['membership_no']]);

    // Return success (or fetch updated user data if needed)
    echo json_encode(['status' => 'success']);
    exit;
}

// Check if membership is paid and not expired
$isPaid = false;
$expiryDate = null;

try {
    $stmt = $pdo->prepare("SELECT expiry_date FROM payments 
                          WHERE user_id = :user_id 
                          AND payment_status = 'success'
                          AND NOW() BETWEEN payment_date AND expiry_date
                          ORDER BY payment_date DESC 
                          LIMIT 1");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $isPaid = true;
        $expiryDate = $result['expiry_date'];
    }
} catch (PDOException $e) {
    error_log("Error checking payment status: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Membership Card</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">


    <!-- Add some CSS for loading and error messages -->
    <style>
        .loading,
        .error {
            padding: 20px;
            text-align: center;
            font-size: 18px;
            background: #f5f5f5;
            border-radius: 5px;
            margin: 20px 0;
        }

        .error {
            color: #d32f2f;
            background: #ffebee;
        }

        .download-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
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
            width: 200px;
            /* Default to expanded width */
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
            margin-bottom: 20px;
            /* Increased margin */
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
            text-align: center;
            /* Align icons */
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
            margin-left: 220px;
            /* Adjusted for sidebar */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: margin-left 0.3s;
        }

        .main-content.expanded {
            margin-left: 220px;
            /* Adjusted for expanded sidebar */
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 2rem;
            /* Default font size */
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

        .profile-summary,
        .payment-status {
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
                transform: translateX(-100%);
                /* Hide sidebar by default */
            }

            .sidebar.expanded {
                transform: translateX(0);
                /* Show sidebar when expanded */
            }

            .main-content {
                margin-left: 20px;
                /* Adjusted for hidden sidebar */
            }

            .main-content.expanded {
                margin-left: 220px;
                /* Adjusted for expanded sidebar */
            }

            .header h1 {
                font-size: 1.5rem;
                /* Reduced font size for mobile */
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
            document.getElementById('toggleSidebar').addEventListener('click', function () {
                document.getElementById('sidebar').classList.toggle('hidden');
                document.getElementById('sidebar').classList.toggle('expanded');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            // Redirect to profile update page on profile image click
            document.querySelector('.user-profile').addEventListener('click', function () {
                window.location.href = 'profile.php';
            });
        });
    </script>
</head>

<body>
    <div class="dashboard">
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <img src="jcdawhite.png" alt="JCDA Logo">
            </div>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home sidebar-icon"></i> <span
                            class="sidebar-text">Home</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user sidebar-icon"></i> <span
                            class="sidebar-text">Profile</span></a></li>
                <li><a href="card.php" class="active"><i class="fas fa-id-card sidebar-icon"></i> <span
                            class="sidebar-text">Membership Card</span></a></li>
                <li><a href="payment.php"><i class="fas fa-money-bill sidebar-icon"></i> <span class="sidebar-text">Pay
                            Dues</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt sidebar-icon"></i> <span
                            class="sidebar-text">Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content" id="mainContent">
            <div class="header">
                <button class="btn btn-primary" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="user-profile">
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User profile">
                </div>
            </div>

            <?php
            // Safest check for NULL/0/"0" with VARCHAR field
            $cardNotIssued = !isset($user['card_issued']) ||
                $user['card_issued'] === null ||
                $user['card_issued'] === '0' ||
                $user['card_issued'] === 0 ||
                $user['card_issued'] === '';

            if ($cardNotIssued): ?>
                <section class="profile-summary">
                    <h2>Generate your membership card</h2>
                    <p>Make sure the following requirements are fulfilled before you can generate your ID card
                    </p>

                    <?php if (!empty($user['surname'])): ?>
                        <p><img src="../assets/images/success.svg" style="max-width: 20px;margin-right: 10px;" alt="">Profile
                            Exists!</p>
                    <?php else: ?>
                        <p>
                            <img src="../assets/images/pending.svg" style="max-width: 20px;margin-right: 10px;" alt="">No
                            Profile Found. Your
                            profile information must be complete
                            <a href="profile.php" class="btn btn-sm btn-outline-secondary"
                                style="margin-left: 10px;font-size: 12px;background: #7b8271;color: white;border: none;">Edit
                                profile</a>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($user['profile_picture'])): ?>
                        <p><img src="../assets/images/success.svg" style="max-width: 20px;margin-right: 10px;" alt="">Profile
                            Picture Available.</p>
                    <?php else: ?>
                        <p>
                            <img src="../assets/images/pending.svg" style="max-width: 20px;margin-right: 10px;" alt="">Profile
                            Picture must be set
                            <a href="profile.php" class="btn btn-sm btn-outline-secondary"
                                style="margin-left: 10px;font-size: 12px;background: #7b8271;color: white;border: none;">Upload
                                profile picture</a>
                        </p>
                    <?php endif; ?>

                    <?php if ($isPaid): ?>
                        <!-- Show success message -->
                        <p>
                            <img src="../assets/images/success.svg" style="max-width: 20px;margin-right: 10px;" alt="">Membership dues successfully paid
                        </p>
                    <?php else: ?>
                        <!-- Show payment prompt -->
                        <p>
                            <img src="../assets/images/pending.svg" style="max-width: 20px;margin-right: 10px;" alt="">
                            Membership dues not paid
                            <a href="payment.php" class="btn btn-sm btn-outline-secondary"
                                style="margin-left: 10px;font-size: 12px;background: #7b8271;color: white;border: none;">
                                Pay Dues
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($user['surname']) && !empty($user['profile_picture'])  && !empty($isPaid)): ?>
                        <button type="button" id="generate-btn" class="btn btn-primary" style="margin-top: 20px;">Generate your card</button><br>
                    <?php endif; ?>

                    <a href="dashboard.php" class="btn btn-outline-secondary mt-3">Back to Dashboard</a>

                    <div id="card-data" style="display: none;">
                        <span
                            id="fullname"><?php echo htmlspecialchars($user['firstname'] . (!empty($user['other_names']) ? ' ' . $user['other_names'] : '') . ' ' . $user['surname']); ?></span>
                        <span id="profile_picture"><?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?></span>
                        <span id="occupation"><?php echo htmlspecialchars($user['occupation'] ?? ''); ?></span>
                        <span id="membership_no"><?php echo htmlspecialchars($user['membership_id_no'] ?? ''); ?></span>
                        <span id="card_issue_date"><?php echo htmlspecialchars($user['card_issue_date'] ?? ''); ?></span>
                        <span id="card_expiry_date"><?php echo htmlspecialchars($user['card_expiry_date'] ?? ''); ?></span>
                    </div>

                    <script>
                        document.getElementById('generate-btn').addEventListener('click', function () {
                            const membershipNo = document.getElementById('membership_no').textContent;

                            // 1. Update database via AJAX
                            fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    'membership_no': membershipNo
                                })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        // 2. Collect data for card-template.html
                                        const cardData = {
                                            fullname: document.getElementById('fullname').textContent,
                                            profile_picture: document.getElementById('profile_picture').textContent,
                                            membership_no: membershipNo,
                                            occupation: document.getElementById('occupation').textContent,
                                            card_issue_date: new Date().toISOString().split('T')[0], // Today's date
                                            card_expiry_date: new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0] // +1 year
                                        };

                                        // 3. Redirect to card-template.html with data
                                        const form = document.createElement('form');
                                        form.method = 'POST';
                                        form.action = 'card-template.php';

                                        Object.keys(cardData).forEach(key => {
                                            const input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.name = key;
                                            input.value = cardData[key];
                                            form.appendChild(input);
                                        });

                                        document.body.appendChild(form);
                                        form.submit();
                                    }
                                })
                                .catch(error => console.error('Error:', error));
                        });
                    </script>
                </section>
            <?php else: ?>
                <section class="profile-summary">
                    <h3>View Membership Card</h3>
                    <p>Your card has been successfully generated. Click below to preview and download it.</p>
                    <div style="margin-bottom: 40px;">
                        <button type="button" id="view-card" class="btn btn-primary">View card</button>
                    </div>
                </section>

                <div id="card-data" style="display: none;">
                    <span
                        id="fullname"><?php echo htmlspecialchars($user['firstname'] . (!empty($user['other_names']) ? ' ' . $user['other_names'] : '') . ' ' . $user['surname']); ?></span>
                    <span id="profile_picture"><?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?></span>
                    <span id="occupation"><?php echo htmlspecialchars($user['occupation'] ?? ''); ?></span>
                    <span id="membership_no"><?php echo htmlspecialchars($user['membership_id_no'] ?? ''); ?></span>
                    <span id="card_issue_date"><?php echo htmlspecialchars($user['card_issue_date'] ?? ''); ?></span>
                    <span id="card_expiry_date"><?php echo htmlspecialchars($user['card_expiry_date'] ?? ''); ?></span>
                </div>

                <script>
                    document.getElementById('view-card').addEventListener('click', function () {
                        // Collect all data from hidden spans
                        const cardData = {
                            fullname: document.getElementById('fullname').textContent,
                            profile_picture: document.getElementById('profile_picture').textContent,
                            membership_no: document.getElementById('membership_no').textContent,
                            occupation: document.getElementById('occupation').textContent,
                            card_issue_date: document.getElementById('card_issue_date').textContent,
                            card_expiry_date: document.getElementById('card_expiry_date').textContent
                        };

                        // Create a dynamic form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'card-template.php';
                        form.style.display = 'none';

                        // Add all data as hidden inputs
                        Object.keys(cardData).forEach(key => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = cardData[key];
                            form.appendChild(input);
                        });

                        // Submit the form
                        document.body.appendChild(form);
                        form.submit();
                    });
                </script>

            <?php endif; ?>

        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>