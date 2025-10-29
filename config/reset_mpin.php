<?php
// config/reset_mpin.php — update user's mPIN after OTP verification
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone = preg_replace('/\D/','',$in['phone'] ?? '');
    $mpin  = trim($in['mpin'] ?? '');

    if (!preg_match('/^\d{10}$/',$phone)) throw new Exception('Invalid phone');
    if (!preg_match('/^\d{4,6}$/',$mpin)) throw new Exception('Invalid mPIN format');

    if (($_SESSION['reset_phone_verified'] ?? '') !== $phone)
        throw new Exception('Phone not verified');

    $mpin_hash = password_hash($mpin, PASSWORD_DEFAULT);
    $stmt=$pdo->prepare("UPDATE users SET mpin_hash=?,updated_at=NOW() WHERE phone=?");
    $stmt->execute([$mpin_hash,$phone]);

    unset($_SESSION['reset_phone_verified']);
    echo json_encode(['success'=>true]);
} catch(Throwable $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>