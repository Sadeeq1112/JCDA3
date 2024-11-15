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

if ($user_id) {
    // Delete user profile
    $stmt = $pdo->prepare("DELETE FROM profiles WHERE user_id = ?");
    if ($stmt->execute([$user_id])) {
        // Optionally, delete user from users table if applicable
        // $userStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        // $userStmt->execute([$user_id]);
    }
}

header("Location: admin_dashboard.php");
exit;
?>