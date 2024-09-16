<?php
// functions.php

/**
 * Redirect to a specified URL
 *
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanitize input data
 *
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Check if user is logged in
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get user profile by user ID
 *
 * @param PDO $pdo
 * @param int $user_id
 * @return array|false
 */
function getUserProfile($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get latest payment by user ID
 *
 * @param PDO $pdo
 * @param int $user_id
 * @return array|false
 */
function getLatestPayment($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>