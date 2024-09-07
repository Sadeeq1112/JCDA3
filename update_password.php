<?php
require_once 'config.php';

$username = 'admin'; // Replace with the actual username
$password = '123'; // Replace with the actual password

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed_password, $username);

if ($stmt->execute()) {
    echo "Password updated successfully.";
} else {
    echo "Error updating password: " . $conn->error;
}

$conn->close();
?>