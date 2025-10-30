<?php
// config/reset_mpin.php — update user's mPIN after OTP verification
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone = preg_replace('/\D/','', $in['phone'] ?? '');
    $mpin  = trim($in['mpin'] ?? '');

    if (!preg_match('/^\d{10}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'Invalid phone']);
        exit;
    }
    if (!preg_match('/^\d{4,6}$/', $mpin)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'Invalid mPIN format']);
        exit;
    }

    // ensure the phone was verified in this session for reset
    if (($_SESSION['reset_phone_verified'] ?? '') !== $phone) {
        http_response_code(403);
        echo json_encode(['success'=>false, 'message'=>'Phone not verified']);
        exit;
    }

    // fetch user by phone
    $stmt = $pdo->prepare("SELECT id, mpin_hash FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'User not found']);
        exit;
    }

    // if existing mpin_hash matches the new mpin -> refuse
    if (!empty($user['mpin_hash']) && password_verify($mpin, $user['mpin_hash'])) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'New mPIN cannot be the same as your previous mPIN.']);
        exit;
    }

    // update to new mPIN
    $mpin_hash = password_hash($mpin, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE users SET mpin_hash = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$mpin_hash, $user['id']]);

    // cleanup session flag so reset can't be reused
    unset($_SESSION['reset_phone_verified']);

    echo json_encode(['success'=>true, 'message'=>'mPIN reset successfully']);
    exit;

} catch (PDOException $e) {
    error_log('reset_mpin.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Server database error']);
    exit;
} catch (Throwable $e) {
    error_log('reset_mpin.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Server error']);
    exit;
}
