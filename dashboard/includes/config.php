<?php
// File: config.php
define('DB_HOST', 'localhost');
// define('DB_NAME', 'bfsllkdc_membership');
// define('DB_USER', 'bfsllkdc_member');
// define('DB_PASS', 'd6C(YdA62yWm9-');
define('JWT_SECRET', 'yH!8xP#mK$qW2zL&nF5vC7bE9jT3sA6d');

define('DB_NAME', 'jcda_database');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'JCDA Member Dashboard');
define('SITE_URL', 'https://jcda.com.ng');

// Flutterwave API keys
define('PAYSTACK_SECRET_KEY', 'sk_test_63303bb3d720a38af47c926c08c861eebf6267a8');

// Email configuration (if using email functionality)
define('SMTP_HOST', 'mail.jcda.com.ng');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'noreply@jcda.com.ng');
define('SMTP_PASSWORD', 'zZ[6oxwGFg.F');
define('SMTP_FROM_EMAIL', 'noreply@jcda.com.ng');
define('SMTP_FROM_NAME', 'JCDA');

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