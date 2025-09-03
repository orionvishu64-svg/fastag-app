<?php
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';

if (!$email || !$phone) {
    echo json_encode(["success" => false, "message" => "Missing email or phone."]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE email = ?");
$success = $stmt->execute([$phone, $email]);

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to save phone."]);
}
?>
