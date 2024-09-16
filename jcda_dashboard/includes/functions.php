<?php
require_once 'config.php';
require_once 'db.php';

/**
 * Sanitize user input
 * @param string $input
 * @return string
 */
function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Generate a random string
 * @param int $length
 * @return string
 */
function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect user to a specific page
 * @param string $location
 */
function redirect($location) {
    header("Location: $location");
    exit;
}

/**
 * Get user information by ID
 * @param int $user_id
 * @return array|false
 */
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Log an error
 * @param string $message
 */
function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'logs/error.log');
}

/**
 * Send an email
 * @param string $to
 * @param string $subject
 * @param string $message
 * @return bool
 */
function send_email($to, $subject, $message) {
    // Implement email sending logic here (e.g., using PHPMailer)
    // Return true if email sent successfully, false otherwise
}

/**
 * Check if a payment is valid
 * @param int $payment_id
 * @return bool
 */
function is_payment_valid($payment_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT payment_status FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    return $payment && $payment['payment_status'] === 'completed';
}