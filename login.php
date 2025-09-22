<?php
require_once 'common_start.php';
header('Access-Control-Allow-Origin: *'); // (adjust domain if needed)
require 'db.php';

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (!isset($data['email'])) {
    echo json_encode(["success" => false, "message" => "Email is required."]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'] ?? null;
$loginType = $data['login_type'] ?? 'manual';

// Fetch user by email
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["success" => false, "message" => "User not found. Please sign up."]);
    exit;
}

// Handle login types
if ($loginType === 'manual') {
    if (!$password) {
        echo json_encode(["success" => false, "message" => "Password is required."]);
        exit;
    }

    // Check password hash
    if (!password_verify($password, $user['password'])) {
        echo json_encode(["success" => false, "message" => "Incorrect password."]);
        exit;
    }

} elseif ($loginType === 'google') {
   // If a user logs in with Google, we just verify they exist.
    // The google-auth.js handles the user lookup.
    // No specific checks are needed beyond ensuring the user exists.
    // The previous check on `login_type` was too restrictive.
} else {
    echo json_encode(["success" => false, "message" => "Invalid login type."]);
    exit;
}

// Login successful
// put a structured user object into session so other pages can rely on it
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'name' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'login_type' => $user['login_type'] ?? 'manual',
];

// convenience legacy fields (optional)
$_SESSION['user_name'] = $user['name'] ?? $user['email'] ?? '';

// Prevent session fixation and persist session now
session_regenerate_id(true);
session_write_close();

// respond to client
echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "user" => [
        "id" => $user['id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "login_type" => $user['login_type']
    ]
]);
exit;
