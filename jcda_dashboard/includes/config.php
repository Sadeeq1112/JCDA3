<?php
// File: config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bfsllkdc_membership');
define('DB_USER', 'bfsllkdc_member');
define('DB_PASS', 'd6C(YdA62yWm9-');
define('JWT_SECRET', 'yH!8xP#mK$qW2zL&nF5vC7bE9jT3sA6d');

define('SITE_NAME', 'JCDA Member Dashboard');
define('SITE_URL', 'https://yourdomain.com');

// Flutterwave API keys
define('FLUTTERWAVE_PUBLIC_KEY', 'your_flutterwave_public_key');
define('FLUTTERWAVE_SECRET_KEY', 'your_flutterwave_secret_key');

// Email configuration (if using email functionality)
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_smtp_username');
define('SMTP_PASSWORD', 'your_smtp_password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'JCDA Admin');

// Session configuration
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 7); // 1 week
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 7); // 1 week
session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone setting
date_default_timezone_set('Africa/Lagos');
?>