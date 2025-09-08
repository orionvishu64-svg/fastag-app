<?php
require_once 'common_start.php';
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['reset_email'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please verify your OTP again."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['password'], $data['email'])) {
    echo json_encode(["success" => false, "message" => "Email and new password are required."]);
    exit;
}

$email = trim($data['email']);
$new_password = $data['password'];

if ($_SESSION['reset_email'] !== $email) {
    echo json_encode(["success" => false, "message" => "Email mismatch. Please try again."]);
    exit;
}

try {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed_password, $email]);

    unset($_SESSION['reset_email']);

    echo json_encode(["success" => true, "message" => "Password updated successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
?>