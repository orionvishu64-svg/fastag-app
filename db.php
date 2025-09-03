<?php
// db.php

$lifetime = 60 * 60 * 24; // 1 day
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => false, // true if HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Database Settings
$host = 'fastag-db.cfm8y6ie68r7.ap-south-1.rds.amazonaws.com';
$dbname = 'myappdb';
$user = 'admin';
$password = 'Optimus20050';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
