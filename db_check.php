<?php
require_once 'config.php';

// Test database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user exists
$username = 'admin'; // Replace with the username you're trying to use
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found in the database.";
} else {
    echo "User found in the database.";
}

$conn->close();
?>