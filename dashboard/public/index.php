<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$basePath = '/'; // Adjust this if your app is in a subdirectory

switch ($request) {
    case $basePath:
        require 'home.php';
        break;
    case $basePath . 'register':
        require 'register.php';
        break;
    case $basePath . 'login':
        require 'login.php';
        break;
    case $basePath . 'dashboard':
        require 'dashboard.php';
        break;
    case $basePath . 'profile':
        require 'profile.php';
        break;
    case $basePath . 'card':
        require 'card.php';
        break;
    case $basePath . 'payment':
        require 'payment.php';
        break;
    default:
        http_response_code(404);
        require 'error404.php';
        break;
}