<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(["success" => false, "message" => "Invalid phone format"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
$ok = $stmt->execute([$phone, $_SESSION['user_id']]);

echo json_encode(["success" => (bool)$ok]);
