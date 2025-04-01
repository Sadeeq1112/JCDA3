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

echo $user_id;
// Fetch user information
$stmt = $pdo->prepare("SELECT u.email, p.full_name, p.phone, p.profile_picture FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default profile picture if none is set
$profile_picture = $user['profile_picture'] ?? '../assets/images/useravatar.jpg';

// Set annual dues amount
$annual_dues = 5000; // ₦5,000


// Fetch payment records for this user
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = :user_id ORDER BY payment_date DESC");
    $stmt->execute([':user_id' => $user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alert = 'Error fetching payment history: ' . $e->getMessage();
    $alert_class = 'danger';
}

// Initialize variables
$alert = '';
$alert_class = '';
$paystackSecretKey = PAYSTACK_SECRET_KEY;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user_id = $_POST['user_id'];
    $email = $_POST['email'];
    $amount = $_POST['amount'];

    // Generate a unique reference for this transaction
    $reference = 'JCDA_CARD_' . uniqid() . time();

    try {
        // Insert payment record with "cancelled" status
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_date, payment_status, gateway_trn_reference) 
                             VALUES (:user_id, :amount, NOW(), 'cancelled', :reference)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':amount' => $amount,
            ':reference' => $reference
        ]);

        // Store reference in session for verification later
        $_SESSION['payment_reference'] = $reference;

        // Initialize Paystack payment
        $url = "https://api.paystack.co/transaction/initialize";

        $fields = [
            'email' => $email,
            'amount' => $amount * 100, // Paystack uses amount in kobo
            'reference' => $reference,
            'callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] // Redirect back to this page
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $paystackSecretKey,
            "Content-Type: application/json",
            "Cache-Control: no-cache"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Payment initialization failed: " . $err);
        }

        $result = json_decode($response);
        if ($result->status) {
            // Redirect to Paystack payment page
            header('Location: ' . $result->data->authorization_url);
            exit();
        } else {
            throw new Exception("Paystack Error: " . $result->message);
        }
    } catch (Exception $e) {
        $alert = 'Payment Failed. Please try again. Error: ' . $e->getMessage();
        $alert_class = 'danger';
    }
}

// Check for Paystack callback
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "authorization: Bearer " . $paystackSecretKey,
            "cache-control: no-cache"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $alert = 'Payment verification failed. Please try again.';
        $alert_class = 'danger';
    } else {
        $result = json_decode($response);

        if ($result->status && $result->data->status == 'success') {
            try {
                // Update payment record
                $stmt = $pdo->prepare("UPDATE payments 
                      SET payment_status = 'success', 
                          gateway_trn_reference = :gateway_ref,
                          expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR)
                      WHERE gateway_trn_reference = :reference");
                $stmt->execute([
                    ':gateway_ref' => $result->data->reference,
                    ':reference' => $reference
                ]);

                $alert = 'Payment Successful.';
                $alert_class = 'success';
            } catch (PDOException $e) {
                $alert = 'Payment record update failed. Please contact support.';
                $alert_class = 'danger';
            }
        } else {
            $alert = 'Payment Failed. Please try again.';
            $alert_class = 'danger';
        }
    }
    if ($alert) {
        $_SESSION['alert'] = $alert;
        $_SESSION['alert_class'] = $alert_class;
        // Redirect to clear the ?reference from URL
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    }
}

// Check for stored alert in session (display once)
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    $alert_class = $_SESSION['alert_class'];
    unset($_SESSION['alert']); // Clear after showing
    unset($_SESSION['alert_class']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Pay Annual Dues</title>
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

        .badge.bg-success {
            color: white;
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
                <li><a href="card.php"><i class="fas fa-id-card sidebar-icon"></i> <span class="sidebar-text">Membership
                            Card</span></a></li>
                <li><a href="payment.php" class="active"><i class="fas fa-money-bill sidebar-icon"></i> <span
                            class="sidebar-text">Pay Dues</span></a></li>
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
            <section class="profile-summary">
                <h2>Pay JCDA Annual Dues</h2>
                <?php if ($alert): ?>
                    <div class="alert alert-<?php echo $alert_class; ?>"><?php echo $alert; ?></div>
                <?php endif; ?>


                <?php
                // Check for active payment and get expiry date
                $activePayment = null;
                try {
                    $stmt = $pdo->prepare("SELECT expiry_date FROM payments 
                          WHERE user_id = :user_id 
                          AND payment_status = 'success'
                          AND NOW() BETWEEN payment_date AND expiry_date
                          ORDER BY payment_date DESC 
                          LIMIT 1");
                    $stmt->execute([':user_id' => $user_id]);
                    $activePayment = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Error checking payment status: " . $e->getMessage());
                }
                ?>

                <?php if ($activePayment): ?>
                    <!-- Show payment confirmation with expiry date -->
                    <div
                        style="background: #d4edda; padding: 15px; border-radius: 5px; padding-bottom: 5px; margin-bottom: 10px;">
                        <h5>Dues Fully Paid.</h5>
                        <p>You have no cancelled dues to be paid.</p>
                        <p> <strong>Membership Expiry:
                                <?php echo date('F j, Y', strtotime($activePayment['expiry_date'])); ?></strong></p>
                    </div>
                <?php else: ?>
                    <!-- Show payment form -->
                    <form action="payment.php" method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <input type="hidden" name="email" value="<?php echo $user['email']; ?>">
                        <input type="hidden" name="amount" value="<?php echo $annual_dues; ?>">
                        <p>Annual Dues Amount: ₦<?php echo number_format($annual_dues, 2); ?></p>
                        <button type="submit" class="btn btn-primary">Pay Now</button>
                    </form>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
            </section>


            <section class="profile-summary">
                <h2>Transaction History</h2>
                <table class="table table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Reference Number</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Payment Date/Time</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No payment records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $index => $payment): ?>
                                <tr>
                                    <th scope="row"><?php echo $index + 1; ?></th>
                                    <td><?php echo htmlspecialchars($payment['gateway_trn_reference'] ?? 'N/A'); ?></td>
                                    <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                        echo $payment['payment_status'] === 'success' ? 'success' :
                                            ($payment['payment_status'] === 'cancelled' ? 'warning' : 'danger');
                                        ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            // Get the submit button
            const submitBtn = this.querySelector('button[type="submit"]');

            // Disable the button and change text
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Redirecting...';
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.remove();
                }, 5000); // 5000ms = 5 seconds
            }
        });
    </script>
</body>

</html>