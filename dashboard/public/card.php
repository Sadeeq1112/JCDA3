<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$card = null;

// Check if profile is complete
$stmt = $pdo->prepare("SELECT u.username, u.email, p.* FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify profile completion
if (!$user['firstname'] || !$user['surname'] || !$user['date_of_birth'] || 
    !$user['occupation'] || !$user['state'] || !$user['profile_picture']) {
    header("Location: profile.php?message=incomplete");
    exit;
}

// Check existing card or generate new one
$stmt = $pdo->prepare("SELECT * FROM membership_cards WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    try {
        // Generate new card
        $card_number = sprintf('JCDA-%06d-%s', $user_id, strtoupper(substr(uniqid(), -4)));
        $issue_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime('+1 year'));
        
        $stmt = $pdo->prepare("INSERT INTO membership_cards (user_id, card_number, issue_date, expiry_date) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $card_number, $issue_date, $expiry_date]);
        
        // Fetch newly created card
        $stmt = $pdo->prepare("SELECT * FROM membership_cards WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error generating membership card.";
        error_log($e->getMessage());
    }
}

// Generate QR code with member details
$qr_data = json_encode([
    'card_number' => $card['card_number'],
    'name' => $user['firstname'] . ' ' . $user['surname'],
    'membership_status' => $card['status'],
    'expiry' => $card['expiry_date']
]);

require_once '../vendor/autoload.php';
$qr = new QRCode($qr_data);
$qr_image = $qr->png(false, QR_ECLEVEL_L, 4);
$qr_base64 = base64_encode($qr_image);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Membership Card</title>
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .membership-card {
            width: 86mm;
            height: 54mm;
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin: 20px auto;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .logo {
            width: 60px;
            height: auto;
            margin-right: 10px;
        }

        .org-name {
            font-size: 14px;
            font-weight: bold;
            color: #378349;
            margin: 0;
        }

        .member-photo {
            width: 80px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
            position: absolute;
            right: 15px;
            top: 15px;
            border: 2px solid #378349;
        }

        .card-details {
            font-size: 12px;
            line-height: 1.4;
        }

        .card-number {
            font-family: monospace;
            font-size: 14px;
            color: #378349;
            font-weight: bold;
        }

        .qr-code {
            position: absolute;
            right: 15px;
            bottom: 15px;
            width: 50px;
            height: 50px;
        }

        .watermark {
            position: absolute;
            bottom: 10px;
            left: 10px;
            opacity: 0.1;
            width: 100px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .membership-card, .membership-card * {
                visibility: visible;
            }
            .membership-card {
                position: absolute;
                left: 0;
                top: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="membership-card">
            <div class="card-header">
                <img src="../assets/images/logo.png" alt="JCDA Logo" class="logo">
                <div>
                    <h1 class="org-name">Jos Community Development Association</h1>
                    <small>Member ID Card</small>
                </div>
            </div>

            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                 alt="Member Photo" class="member-photo">

            <div class="card-details">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['surname']); ?></p>
                <p><strong>Card No:</strong> <span class="card-number"><?php echo htmlspecialchars($card['card_number']); ?></span></p>
                <p><strong>Issue Date:</strong> <?php echo date('d/m/Y', strtotime($card['issue_date'])); ?></p>
                <p><strong>Expires:</strong> <?php echo date('d/m/Y', strtotime($card['expiry_date'])); ?></p>
            </div>

            <img src="data:image/png;base64,<?php echo $qr_base64; ?>" alt="QR Code" class="qr-code">
            <img src="../assets/images/watermark.png" alt="" class="watermark">
        </div>

        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print()">Print Card</button>
            <a href="dashboard.php" class="btn btn-secondary ml-2">Back to Dashboard</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>