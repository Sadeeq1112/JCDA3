<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Define states array for the filter dropdown
$states = array_keys($states_lgas);

// Handle search and filters
$searchQuery = "WHERE 1=1";
$params = [];
$search = '';
$stateFilter = '';
$sortOrder = 'ASC';

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $searchTerm = '%' . $search . '%';
    $searchQuery .= " AND (firstname LIKE ? OR surname LIKE ? OR email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['state'])) {
    $stateFilter = $_GET['state'];
    $searchQuery .= " AND state = ?";
    $params[] = $stateFilter;
}

if (!empty($_GET['sort']) && in_array($_GET['sort'], ['ASC', 'DESC'])) {
    $sortOrder = $_GET['sort'];
}

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Get total records
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM profiles $searchQuery");
$totalStmt->execute($params);
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch profiles
$profilesStmt = $pdo->prepare("SELECT * FROM profiles $searchQuery ORDER BY surname $sortOrder LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$profilesStmt->execute($params);
$profiles = $profilesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <!-- Include Bootstrap CSS or any other stylesheets you prefer -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dashboard-container {
            margin: 20px;
        }
        .logout-btn {
            margin-left: auto;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .btn-primary {
            background-color: #378349;
            border: none;
        }
        .btn-primary:hover {
            background-color: #2c6b3c;
        }
        .pagination {
            justify-content: center;
        }
        .filter-form .form-group {
            margin-right: 15px;
        }
        .filter-form {
            flex-wrap: wrap;
        }
        .export-btn {
            margin-left: 15px;
        }
        .no-data {
            text-align: center;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand" href="#">Admin Dashboard</a>
            <div class="ml-auto">
                <span class="navbar-text mr-3">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="admin_logout.php" class="btn btn-outline-dark">Logout</a>
            </div>
        </nav>

        <!-- Search and Filter Form -->
        <form method="get" action="admin_dashboard.php" class="form-inline mt-4 filter-form">
            <div class="form-group">
                <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <select name="state" class="form-control">
                    <option value="">All States</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo htmlspecialchars($state); ?>" <?php if ($stateFilter == $state) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($state); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="sort" class="form-control">
                    <option value="ASC" <?php if ($sortOrder == 'ASC') echo 'selected'; ?>>Sort Ascending</option>
                    <option value="DESC" <?php if ($sortOrder == 'DESC') echo 'selected'; ?>>Sort Descending</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="export_csv.php" class="btn btn-success export-btn">Export to CSV</a>
        </form>

        <!-- Display Profiles -->
        <?php if ($profiles): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mt-3">
                    <thead class="thead-dark">
                        <tr>
                            <th>User ID</th>
                            <th>First Name</th>
                            <th>Surname</th>
                            <th>Email</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>State</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $profile): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($profile['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($profile['firstname']); ?></td>
                                <td><?php echo htmlspecialchars($profile['surname']); ?></td>
                                <td><?php echo htmlspecialchars($profile['email']); ?></td>
                                <td><?php echo htmlspecialchars($profile['date_of_birth']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($profile['gender'])); ?></td>
                                <td><?php echo htmlspecialchars($profile['state']); ?></td>
                                <td>
                                    <a href="edit_user.php?user_id=<?php echo $profile['user_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="delete_user.php?user_id=<?php echo $profile['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php else: ?>
            <div class="no-data">
                <h3>No profiles found.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>