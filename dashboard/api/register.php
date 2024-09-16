<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Function to send JSON response
function sendJsonResponse($status, $message, $data = null) {
    http_response_code($status);
    echo json_encode(array(
        "status" => $status,
        "message" => $message,
        "data" => $data
    ));
    exit();
}

try {
    // Include required files
    require_once '../vendor/autoload.php';
    require_once '../config.php';
    require_once '../database.php';
    require_once '../user.php';

    // Initialize database connection
    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // Initialize user object
    $user = new User($db);

    // Get posted data
    $data = json_decode(file_get_contents("php://input"));

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }

    // Validate required fields
    $requiredFields = ['name', 'email', 'password', 'phone'];
    foreach ($requiredFields as $field) {
        if (!isset($data->$field) || empty($data->$field)) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Set user properties
    $user->name = $data->name;
    $user->email = $data->email;
    $user->password = $data->password;
    $user->membershipId = 'JCDA' . rand(10000, 99999); // Generate a random membership ID
    $user->phone = $data->phone;

    // Check if email or phone already exists
    if ($user->emailExists()) {
        sendJsonResponse(400, "Email already exists.");
    }

    if ($user->phoneExists()) {
        sendJsonResponse(400, "Phone number already exists.");
    }

    // Create the user
    if ($user->create()) {
        sendJsonResponse(201, "User was created successfully.", [
            "membershipId" => $user->membershipId
        ]);
    } else {
        throw new Exception("Unable to create user.");
    }

} catch (Exception $e) {
    sendJsonResponse(500, "An error occurred: " . $e->getMessage());
}
?>