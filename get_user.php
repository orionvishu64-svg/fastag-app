<?php
session_start();
require_once __DIR__ . '/db.php';
require_once 'common_start.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Split full name into first + last (basic split on space)
    $nameParts = explode(" ", $user['name'], 2);
    $firstName = $nameParts[0];
    $lastName  = isset($nameParts[1]) ? $nameParts[1] : "";

    echo json_encode([
        'success' => true,
        'user' => [
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $user['email'],
            'phone'     => $user['phone']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>