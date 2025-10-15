<?php
// get_user.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Use session auth only
$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

if ($user) {
    // Split full name into first + last
    $nameParts = preg_split('/\s+/', trim($user['name'] ?? ''), 2);
    $firstName = $nameParts[0] ?? '';
    $lastName  = $nameParts[1] ?? '';

    echo json_encode([
        'success' => true,
        'user' => [
            'id'        => $userId,
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $user['email'] ?? '',
            'phone'     => $user['phone'] ?? ''
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
