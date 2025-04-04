<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Session timeout check (15 minutes)
$session_timeout = 15 * 60; // 15 minutes in seconds
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $session_timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit;
}
$_SESSION['admin_last_activity'] = time(); // Update last activity time

// Define Nigerian states if the $states_lgas variable isn't available
$states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 
    'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 
    'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 
    'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 
    'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
];

// Handle search and filters
$searchQuery = "WHERE 1=1";
$params = [];
$search = '';
$stateFilter = '';
$sortOrder = 'ASC';

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $searchTerm = '%' . $search . '%';
    $searchQuery .= " AND (p.firstname LIKE ? OR p.surname LIKE ? OR u.email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['state'])) {
    $stateFilter = $_GET['state'];
    $searchQuery .= " AND LOWER(p.state) = LOWER(?)";
    $params[] = $stateFilter;
}

if (!empty($_GET['sort']) && in_array($_GET['sort'], ['ASC', 'DESC'])) {
    $sortOrder = $_GET['sort'];
}

// Check if profiles and users tables exist and have the expected columns
$tablesExist = false;
$profilesColumns = [];
$usersColumns = [];

try {
    // Check for profiles table
    $checkProfilesTable = $pdo->query("SHOW TABLES LIKE 'profiles'");
    $profilesTableExists = ($checkProfilesTable && $checkProfilesTable->rowCount() > 0);
    
    // Check for users table
    $checkUsersTable = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = ($checkUsersTable && $checkUsersTable->rowCount() > 0);
    
    $tablesExist = ($profilesTableExists && $usersTableExists);
    
    if ($profilesTableExists) {
        $columnsQuery = $pdo->query("DESCRIBE profiles");
        while ($col = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $profilesColumns[] = $col['Field'];
        }
    }
    
    if ($usersTableExists) {
        $columnsQuery = $pdo->query("DESCRIBE users");
        while ($col = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $usersColumns[] = $col['Field'];
        }
    }
} catch (PDOException $e) {
    error_log("Error checking table structure: " . $e->getMessage());
}

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Initialize variables
$totalRecords = 0;
$totalPages = 0;
$profiles = [];

// Only attempt to fetch data if both tables exist
if ($tablesExist) {
    try {
        // Get total records with JOIN
        $totalQuery = "SELECT COUNT(*) FROM profiles p JOIN users u ON p.user_id = u.id $searchQuery";
        $totalStmt = $pdo->prepare($totalQuery);
        $totalStmt->execute($params);
        $totalRecords = $totalStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);
        
        // Create a new array for all parameters
        $queryParams = $params;
        
        // Fetch profiles with JOIN and proper parameter binding
        $query = "SELECT p.*, u.email 
                FROM profiles p 
                JOIN users u ON p.user_id = u.id 
                $searchQuery 
                ORDER BY p.surname $sortOrder 
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        
        // Add limit and offset parameters
        $queryParams[] = $limit;
        $queryParams[] = $offset;
        
        $stmt->execute($queryParams);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching profiles: " . $e->getMessage());
        // Initialize empty array to prevent errors
        $profiles = [];
    }
}

// Set up statistics
$stats = [
    'total_users' => 0,
    'new_today' => 0,
    'states_distribution' => [],
    'gender_distribution' => ['Male' => 0, 'Female' => 0, 'Other' => 0]
];

if ($tablesExist) {
    try {
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM profiles p JOIN users u ON p.user_id = u.id");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // New users today (using registration_date from users table if available)
        if (in_array('registration_date', $usersColumns) || in_array('created_at', $usersColumns)) {
            $dateColumn = in_array('registration_date', $usersColumns) ? 'registration_date' : 'created_at';
            $stmt = $pdo->query("SELECT COUNT(*) FROM profiles p JOIN users u ON p.user_id = u.id WHERE DATE(u.$dateColumn) = CURDATE()");
            $stats['new_today'] = $stmt->fetchColumn();
        }
        
        // Users by state
        if (in_array('state', $profilesColumns)) {
            $stmt = $pdo->query("SELECT state, COUNT(*) as count FROM profiles GROUP BY state ORDER BY count DESC LIMIT 5");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['state'])) {
                    $stats['states_distribution'][$row['state']] = $row['count'];
                }
            }
        }
        
        // Gender distribution
        if (in_array('gender', $profilesColumns)) {
            $stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM profiles GROUP BY gender");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $gender = !empty($row['gender']) ? $row['gender'] : 'Other';
                $stats['gender_distribution'][$gender] = $row['count'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching statistics: " . $e->getMessage());
    }
}

// Admin actions handler
$actionMessage = '';
$actionStatus = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $userId = intval($_GET['id']);
    
    try {
        switch ($action) {
            case 'delete':
                if (isset($_GET['confirmed']) && $_GET['confirmed'] == 1) {
                    // First fetch the user_id from profiles
                    $userIdStmt = $pdo->prepare("SELECT user_id FROM profiles WHERE id = ?");
                    $userIdStmt->execute([$userId]);
                    $profileUserId = $userIdStmt->fetchColumn();
                    
                    if ($profileUserId) {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Delete profile
                        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        // Delete user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$profileUserId]);
                        
                        $pdo->commit();
                        $actionMessage = "User successfully deleted";
                        $actionStatus = "success";
                    } else {
                        $actionMessage = "User not found";
                        $actionStatus = "danger";
                    }
                } else {
                    // Just show confirmation message in the UI
                    $actionMessage = "Are you sure you want to delete this user? This action cannot be undone.";
                    $actionStatus = "warning";
                }
                break;
                
            case 'verify':
                if (in_array('verified', $profilesColumns)) {
                    $stmt = $pdo->prepare("UPDATE profiles SET verified = 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                    $actionMessage = "User successfully verified";
                    $actionStatus = "success";
                } else {
                    $actionMessage = "Verification column not found in database";
                    $actionStatus = "danger";
                }
                break;
                
            case 'block':
                if (in_array('status', $profilesColumns)) {
                    $stmt = $pdo->prepare("UPDATE profiles SET status = 'blocked' WHERE id = ?");
                    $stmt->execute([$userId]);
                    $actionMessage = "User has been blocked";
                    $actionStatus = "warning";
                } else {
                    $actionMessage = "Status column not found in database";
                    $actionStatus = "danger";
                }
                break;
                
            case 'unblock':
                if (in_array('status', $profilesColumns)) {
                    $stmt = $pdo->prepare("UPDATE profiles SET status = 'active' WHERE id = ?");
                    $stmt->execute([$userId]);
                    $actionMessage = "User has been unblocked";
                    $actionStatus = "success";
                } else {
                    $actionMessage = "Status column not found in database";
                    $actionStatus = "danger";
                }
                break;
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $actionMessage = "Error: " . $e->getMessage();
        $actionStatus = "danger";
        error_log("Admin action error: " . $e->getMessage());
    }
}

// Process bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_users'])) {
    $bulkAction = $_POST['bulk_action'];
    $selectedUsers = $_POST['selected_users'];
    $validUserIds = [];
    
    // Validate user IDs
    foreach ($selectedUsers as $userId) {
        if (is_numeric($userId)) {
            $validUserIds[] = intval($userId);
        }
    }
    
    if (!empty($validUserIds)) {
        try {
            $placeholders = implode(',', array_fill(0, count($validUserIds), '?'));
            
            switch ($bulkAction) {
                case 'delete':
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // First get all user_ids from profiles
                    $userIdsStmt = $pdo->prepare("SELECT user_id FROM profiles WHERE id IN ($placeholders)");
                    $userIdsStmt->execute($validUserIds);
                    $profileUserIds = $userIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($profileUserIds)) {
                        // Delete profiles
                        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id IN ($placeholders)");
                        $stmt->execute($validUserIds);
                        
                        // Delete users
                        $userPlaceholders = implode(',', array_fill(0, count($profileUserIds), '?'));
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($userPlaceholders)");
                        $stmt->execute($profileUserIds);
                    }
                    
                    $pdo->commit();
                    $actionMessage = count($validUserIds) . " users have been deleted";
                    $actionStatus = "success";
                    break;
                    
                case 'verify':
                    if (in_array('verified', $profilesColumns)) {
                        $stmt = $pdo->prepare("UPDATE profiles SET verified = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($validUserIds);
                        $actionMessage = count($validUserIds) . " users have been verified";
                        $actionStatus = "success";
                    } else {
                        $actionMessage = "Verification column not found in database";
                        $actionStatus = "danger";
                    }
                    break;
                    
                case 'block':
                    if (in_array('status', $profilesColumns)) {
                        $stmt = $pdo->prepare("UPDATE profiles SET status = 'blocked' WHERE id IN ($placeholders)");
                        $stmt->execute($validUserIds);
                        $actionMessage = count($validUserIds) . " users have been blocked";
                        $actionStatus = "warning";
                    } else {
                        $actionMessage = "Status column not found in database";
                        $actionStatus = "danger";
                    }
                    break;
                    
                case 'unblock':
                    if (in_array('status', $profilesColumns)) {
                        $stmt = $pdo->prepare("UPDATE profiles SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($validUserIds);
                        $actionMessage = count($validUserIds) . " users have been unblocked";
                        $actionStatus = "success";
                    } else {
                        $actionMessage = "Status column not found in database";
                        $actionStatus = "danger";
                    }
                    break;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $actionMessage = "Error processing bulk action: " . $e->getMessage();
            $actionStatus = "danger";
            error_log("Admin bulk action error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA Admin Dashboard</title>
    <link rel="icon" href="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" type="image/png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #378349;
            --primary-light: #4ca669;
            --primary-dark: #2c6b3c;
            --secondary: #34495e;
            --light: #f8f9fa;
            --dark: #212529;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .admin-sidebar {
            background-color: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            left: 0;
            top: 0;
            padding-top: 20px;
            transition: all 0.3s;
            overflow-y: auto;
        }
        
        .admin-sidebar .navbar-brand {
            color: white;
            font-size: 1.5rem;
            padding: 15px 20px;
            display: block;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .admin-sidebar .navbar-brand img {
            margin-right: 10px;
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .admin-sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .nav-link.active {
            color: white;
            background-color: var(--primary);
            border-left: 4px solid var(--primary-light);
        }
        
        .admin-sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .content-wrapper {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .admin-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .stat-card h2 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 100%;
        }
        
        .filter-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .data-table {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .pagination .page-link {
            color: var(--primary);
        }
        
        .toggle-sidebar {
            display: none;
        }
        
        @media (max-width: 991.98px) {
            .admin-sidebar {
                margin-left: -250px;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
            
            .admin-sidebar.active {
                margin-left: 0;
            }
            
            .toggle-sidebar {
                display: inline-block;
                margin-right: 15px;
                font-size: 1.5rem;
                cursor: pointer;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <a class="navbar-brand" href="#">
            <img src="https://res.cloudinary.com/dtqzcsq0i/image/upload/v1730661861/JCDA_WHite_ngd8co.png" alt="JCDA Logo" height="30">
            JCDA Admin
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">
                    <i class="fas fa-users"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item mt-5">
                <a class="nav-link text-danger" href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="content-wrapper">
        <!-- Header -->
        <div class="admin-header d-flex align-items-center justify-content-between">
            <div>
                <span class="toggle-sidebar"><i class="fas fa-bars"></i></span>
                <span class="h4 mb-0">Dashboard</span>
            </div>
            <div class="d-flex align-items-center">
                <span class="mr-3"><i class="fas fa-user-shield mr-2"></i><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-cog"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user-circle mr-2"></i>My Profile</a>
                        <a class="dropdown-item" href="admin_logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h2><?php echo number_format($stats['total_users']); ?></h2>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-plus"></i></div>
                    <h2><?php echo number_format($stats['new_today']); ?></h2>
                    <p>New Today</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-male"></i>/<i class="fas fa-female"></i></div>
                    <h2><?php echo number_format($stats['gender_distribution']['Male'] ?? 0); ?>/<span><?php echo number_format($stats['gender_distribution']['Female'] ?? 0); ?></span></h2>
                    <p>Male/Female Ratio</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                    <h2><?php echo count($stats['states_distribution']); ?></h2>
                    <p>States Represented</p>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="filter-form mb-4">
            <form method="get" action="admin_dashboard.php" class="row">
                <div class="col-md-5 mb-2">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="state" class="form-control">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo htmlspecialchars($state); ?>" <?php if ($stateFilter == $state) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="sort" class="form-control">
                        <option value="ASC" <?php if ($sortOrder == 'ASC') echo 'selected'; ?>>A-Z</option>
                        <option value="DESC" <?php if ($sortOrder == 'DESC') echo 'selected'; ?>>Z-A</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
                <div class="col-12 mt-2">
                    <div class="d-flex justify-content-end">
                        <a href="export_csv.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-success">
                            <i class="fas fa-file-export mr-1"></i> Export to CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Data Table -->
        <div class="data-table">
            <?php if (empty($profiles)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No users found</h4>
                    <p class="text-muted">Try adjusting your search criteria or create new users.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>State</th>
                                <th>Gender</th>
                                <th>Date of Birth</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profiles as $profile): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($profile['firstname'] ?? '') . ' ' . htmlspecialchars($profile['surname'] ?? ''); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($profile['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($profile['state'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($profile['gender'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-info view-user" data-id="<?php echo htmlspecialchars($profile['id'] ?? $profile['user_id'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning edit-user" data-id="<?php echo htmlspecialchars($profile['id'] ?? $profile['user_id'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger delete-user" data-id="<?php echo htmlspecialchars($profile['id'] ?? $profile['user_id'] ?? ''); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/(\?|&)page=[^&]*/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                <i class="fas fa-angle-left"></i> Previous
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/(\?|&)page=[^&]*/', '', $_SERVER['QUERY_STRING']) : '') . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++) {
                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . (!empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/(\?|&)page=[^&]*/', '', $_SERVER['QUERY_STRING']) : '') . '">' . $i . '</a></li>';
                        }
                        
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . (!empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/(\?|&)page=[^&]*/', '', $_SERVER['QUERY_STRING']) : '') . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/(\?|&)page=[^&]*/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                Next <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript for functionality -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        $(document).ready(function() {
            $('.toggle-sidebar').on('click', function() {
                $('.admin-sidebar').toggleClass('active');
            });
            
            // Handle user action buttons
            $('.view-user').on('click', function() {
                const userId = $(this).data('id');
                // Implement view user modal or redirect to user profile page
                alert('View user with ID: ' + userId);
            });
            
            $('.edit-user').on('click', function() {
                const userId = $(this).data('id');
                // Implement edit user functionality
                window.location.href = 'edit_user.php?id=' + userId;
            });
            
            $('.delete-user').on('click', function() {
                const userId = $(this).data('id');
                if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                    // Implement delete user functionality
                    window.location.href = 'delete_user.php?id=' + userId + '&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
                }
            });
        });
    </script>
</body>
</html>