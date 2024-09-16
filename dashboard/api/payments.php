<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payments = getUserPayments($_SESSION['user_id']);
    echo json_encode($payments);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentInfo = initializePayment($_SESSION['user_id']);
    echo json_encode($paymentInfo);
}