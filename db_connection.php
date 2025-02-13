<?php

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'telemedicine';
require_once 'vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();
try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Error connecting to the database. Please try again later.");
}
?>
