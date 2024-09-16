<?php
require_once '../vendor/autoload.php';
require_once '../config.php';
require_once '../database.php';
require_once '../user.php';

use \Firebase\JWT\JWT;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$database = new Database();
$db = $database->connect();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

$user->name = $data->name;
$user->email = $data->email;
$user->password = $data->password;
$user->membershipId = 'JCDA' . rand(10000, 99999); // Generate a random membership ID
$user->phone = $data->phone;

// Check if email or phone already exists
if ($user->emailExists() || $user->phoneExists()) {
    http_response_code(400);
    echo json_encode(array("message" => "Email or phone number already exists."));
    exit();
}

if($user->create()) {
    http_response_code(201);
    echo json_encode(array("message" => "User was created."));
} else {
    http_response_code(503);
    echo json_encode(array("message" => "Unable to create user."));
}
?>