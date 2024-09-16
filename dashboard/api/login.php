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

$user->email = $data->email;
$email_exists = $user->emailExists();

if($email_exists && password_verify($data->password, $user->password)) {
    $token = array(
       "iss" => "http://example.org",
       "aud" => "http://example.com",
       "iat" => time(),
       "nbf" => time(),
       "exp" => time() + 3600,
       "data" => array(
           "id" => $user->id,
           "email" => $user->email
       )
    );

    http_response_code(200);

    $jwt = JWT::encode($token, JWT_SECRET, 'HS256');
    echo json_encode(
        array(
            "message" => "Successful login.",
            "jwt" => $jwt
        )
    );
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Login failed."));
}