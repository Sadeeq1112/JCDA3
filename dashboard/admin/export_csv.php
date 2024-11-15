<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Fetch all user profiles
$stmt = $pdo->query("SELECT * FROM profiles");
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=user_profiles.csv');

// Output CSV data
$output = fopen('php://output', 'w');
fputcsv($output, array('User ID', 'First Name', 'Surname', 'Email', 'Date of Birth', 'Gender', 'State'));
foreach ($profiles as $profile) {
    fputcsv($output, [
        $profile['user_id'],
        $profile['firstname'],
        $profile['surname'],
        $profile['email'],
        $profile['date_of_birth'],
        $profile['gender'],
        $profile['state']
    ]);
}
fclose($output);
exit;
?>