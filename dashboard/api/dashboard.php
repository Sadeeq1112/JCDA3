<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userData = getUserData($_SESSION['user_id']);
$dashboardData = [
    "membershipStatus" => $userData['membership_status'],
    "nextDueDate" => $userData['next_due_date'],
    "recentActivities" => getRecentActivities($_SESSION['user_id'])
];

echo json_encode($dashboardData);