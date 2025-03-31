<?php
/**
 * Admin Panel Index - Entry point for admin section
 * 
 * This file serves as the entry point to the admin panel, checking authentication
 * status and redirecting users to the appropriate page.
 */

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Load required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && 
    isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    
    // Verify the admin session is still valid against the database
    try {
        $stmt = $pdo->prepare("SELECT id, username, last_login FROM admins WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            // Admin is authenticated, redirect to dashboard
            header("Location: admin_dashboard.php");
            exit;
        } else {
            // Admin not found or inactive, destroy session
            session_unset();
            session_destroy();
        }
    } catch (PDOException $e) {
        // Log error but don't expose details
        error_log("Admin session verification error: " . $e->getMessage());
    }
}

// Not logged in or session invalid, redirect to login page
header("Location: admin_login.php");
exit;
?>