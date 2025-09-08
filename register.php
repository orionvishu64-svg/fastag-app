<?php
require_once 'common_start.php';
/* file_put_contents("debug.txt", json_encode($_data));
$raw = file_get_contents("php://input");
file_put_contents("debug.txt", $raw); // Log the raw JSON input
$data = json_decode($raw, true);   */

header('Content-Type: application/json');
require 'db.php';

// Get JSON input
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Validate
if (!isset($data['email'], $data['login_type'])) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

$email = trim($data['email']);
$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$loginType = trim($data['login_type']);
$password = trim($data['password'] ?? '');

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(["success" => false, "message" => "Email already exists. Please login."]);
    exit;
}

// Handle Manual Sign-Up
if ($loginType === 'manual') {
    if (!$name || !$password || !$phone) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }
 try {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, login_type, created_at)
                             VALUES (?, ?, ?, ?, 'manual', NOW())");
    $success = $insert->execute([$name, $email, $phone, $hashedPassword]);
 } catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit;
 }
} elseif ($loginType === 'google') {
    if (!$name) {
        echo json_encode(["success" => false, "message" => "Name required for Google Sign-Up."]);
        exit;
    }
try {
    $insert = $pdo->prepare("INSERT INTO users (name, email, password, phone, login_type, created_at)
                             VALUES (?, ?, NULL, NULL, 'google', NOW())");
    $success = $insert->execute([$name, $email]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit;
 }
} else {
    echo json_encode(["success" => false, "message" => "Invalid login type."]);
    exit;
}

// Final response
if ($success) {
    $user = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $user->execute([$email]);
    $user = $user->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
    }
    echo json_encode(["success" => true, "message" => "Account created successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Something went wrong."]);
}
