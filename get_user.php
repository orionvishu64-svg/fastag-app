<?php
// get_user.php
require_once 'common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/socket_auth.php'; // for verify_socket_token()

header('Content-Type: application/json');

// ---------------- Try Token Auth ----------------
$userId = 0;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    $uid = verify_socket_token($token);
    if ($uid > 0) {
        $userId = $uid;
        // Optionally sync into session so rest of app sees the user
        if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
        if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
    }
}

// ---------------- Fallback: Session Auth ----------------
if ($userId <= 0) {
    $userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
}

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
