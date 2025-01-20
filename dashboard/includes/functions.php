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
    $log_file = __DIR__ . '/../logs/error.log';
    if (!file_exists($log_file)) {
        mkdir(dirname($log_file), 0755, true);
        touch($log_file);
    }
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $log_file);
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

/**
 * Log an activity
 * @param string $admin_username
 * @param string $action
 * @param string $details
 */
function log_activity($admin_username, $action, $details = '') {
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_username, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $admin_username,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR']
    ]);
}

/**
 * Validates a date string
 * @param string $date Date string in Y-m-d format
 * @return bool Returns true if date is valid and user is at least 18 years old
 */
function validate_date($date) {
    // Check if the date is in valid format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        return false;
    }
    
    // Convert date string to timestamp
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    // Check if date is valid (e.g., not 2023-02-30)
    $date_parts = explode('-', $date);
    if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        return false;
    }
    
    // Calculate age
    $birth_date = new DateTime($date);
    $today = new DateTime('today');
    $age = $birth_date->diff($today)->y;
    
    // Check if user is at least 18 years old
    if ($age < 18) {
        return false;
    }
    
    return true;
}
?>