<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int) ( $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0 );
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(["success" => false, "message" => "Invalid phone format"]);
    exit;
}
// validate and update using $userId
$stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
$ok = $stmt->execute([$phone, $userId]);
echo json_encode(["success" => (bool)$ok]);