<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch all user profiles
$stmt = $pdo->prepare("SELECT * FROM profiles");
$stmt->execute();
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $user_id = $_POST['user_id'];
    $surname = trim($_POST['surname']);
    $full_name = trim($_POST['full_name']);
    $lga = trim($_POST['lga']);
    $state = trim($_POST['state']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $contact_address = trim($_POST['contact_address']);
    $qualification = trim($_POST['qualification']);
    $date_of_birth = $_POST['date_of_birth'];
    $occupation = trim($_POST['occupation']);

    $stmt = $pdo->prepare("UPDATE profiles SET surname = ?, full_name = ?, lga = ?, state = ?, phone = ?, email = ?, contact_address = ?, qualification = ?, date_of_birth = ?, occupation = ? WHERE user_id = ?");
    $params = [$surname, $full_name, $lga, $state, $phone, $email, $contact_address, $qualification, $date_of_birth, $occupation, $user_id];

    if ($stmt->execute($params)) {
        $success = "Profile updated successfully.";
    } else {
        $error = "Failed to update profile. Please try again.";
    }

    // Refresh profiles data
    $stmt = $pdo->prepare("SELECT * FROM profiles");
    $stmt->execute();
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle profile query
$query_results = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query_profiles'])) {
    $query = trim($_POST['query']);
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE surname LIKE ? OR full_name LIKE ? OR lga LIKE ? OR state LIKE ? OR phone LIKE ? OR email LIKE ? OR contact_address LIKE ? OR qualification LIKE ? OR date_of_birth LIKE ? OR occupation LIKE ?");
    $params = array_fill(0, 10, "%$query%");
    $stmt->execute($params);
    $query_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JCDA</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .table-responsive {
            margin-top: 20px;
        }
        .form-inline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-inline .form-group {
            margin-right: 10px;
        }
        .form-inline .form-control {
            width: auto;
        }
        .modal .form-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form class="form-inline" method="POST" action="admin_dashboard.php">
            <div class="form-group">
                <input type="text" name="query" class="form-control" placeholder="Search profiles...">
            </div>
            <button type="submit" name="query_profiles" class="btn btn-primary">Search</button>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Surname</th>
                        <th>Full Name</th>
                        <th>L.G.A</th>
                        <th>State</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Contact Address</th>
                        <th>Qualification</th>
                        <th>Date of Birth</th>
                        <th>Occupation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profiles as $profile): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($profile['surname']); ?></td>
                            <td><?php echo htmlspecialchars($profile['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($profile['lga']); ?></td>
                            <td><?php echo htmlspecialchars($profile['state']); ?></td>
                            <td><?php echo htmlspecialchars($profile['phone']); ?></td>
                            <td><?php echo htmlspecialchars($profile['email']); ?></td>
                            <td><?php echo htmlspecialchars($profile['contact_address']); ?></td>
                            <td><?php echo htmlspecialchars($profile['qualification']); ?></td>
                            <td><?php echo htmlspecialchars($profile['date_of_birth']); ?></td>
                            <td><?php echo htmlspecialchars($profile['occupation']); ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#editProfileModal<?php echo $profile['user_id']; ?>">Edit</button>
                            </td>
                        </tr>

                        <!-- Edit Profile Modal -->
                        <div class="modal fade" id="editProfileModal<?php echo $profile['user_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel<?php echo $profile['user_id']; ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editProfileModalLabel<?php echo $profile['user_id']; ?>">Edit Profile</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="admin_dashboard.php">
                                            <input type="hidden" name="user_id" value="<?php echo $profile['user_id']; ?>">
                                            <div class="form-group">
                                                <label for="surname">Surname:</label>
                                                <input type="text" id="surname" name="surname" class="form-control" value="<?php echo htmlspecialchars($profile['surname']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="full_name">Full Name:</label>
                                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="lga">L.G.A:</label>
                                                <input type="text" id="lga" name="lga" class="form-control" value="<?php echo htmlspecialchars($profile['lga']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="state">State:</label>
                                                <input type="text" id="state" name="state" class="form-control" value="<?php echo htmlspecialchars($profile['state']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="phone">Phone Number:</label>
                                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="email">Email Address:</label>
                                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="contact_address">Contact Address:</label>
                                                <textarea id="contact_address" name="contact_address" class="form-control"><?php echo htmlspecialchars($profile['contact_address']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="qualification">Qualification:</label>
                                                <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo htmlspecialchars($profile['qualification']); ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="date_of_birth">Date of Birth:</label>
                                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo $profile['date_of_birth']; ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="occupation">Occupation:</label>
                                                <input type="text" id="occupation" name="occupation" class="form-control" value="<?php echo htmlspecialchars($profile['occupation']); ?>">
                                            </div>
                                            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($query_results)): ?>
            <h2>Query Results</h2>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Surname</th>
                            <th>Full Name</th>
                            <th>L.G.A</th>
                            <th>State</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Contact Address</th>
                            <th>Qualification</th>
                            <th>Date of Birth</th>
                            <th>Occupation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($query_results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['surname']); ?></td>
                                <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['lga']); ?></td>
                                <td><?php echo htmlspecialchars($result['state']); ?></td>
                                <td><?php echo htmlspecialchars($result['phone']); ?></td>
                                <td><?php echo htmlspecialchars($result['email']); ?></td>
                                <td><?php echo htmlspecialchars($result['contact_address']); ?></td>
                                <td><?php echo htmlspecialchars($result['qualification']); ?></td>
                                <td><?php echo htmlspecialchars($result['date_of_birth']); ?></td>
                                <td><?php echo htmlspecialchars($result['occupation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>