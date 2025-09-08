<?php
require_once 'common_start.php';
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email'], $data['otp'])) {
    echo json_encode(["success" => false, "message" => "Email and OTP are required."]);
    exit;
}

$email = trim($data['email']);
$otp = trim($data['otp']);
$current_time = new DateTime();

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND otp_code = ?");
    $stmt->execute([$email, $otp]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Invalid OTP."]);
        exit;
    }

    $otp_expiry = new DateTime($user['otp_expires_at']);
    if ($current_time > $otp_expiry) {
        echo json_encode(["success" => false, "message" => "OTP has expired."]);
        exit;
    }

    $clear_otp = $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
    $clear_otp->execute([$user['id']]);

    $_SESSION['reset_email'] = $email;

    echo json_encode(["success" => true, "message" => "OTP verified successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
?>