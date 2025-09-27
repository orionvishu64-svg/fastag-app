<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms_config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');

    // ✅ FIX 1: proper phone validation
    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone']);
        exit;
    }

    // Find user by phone
    $st = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $st->execute([$phone]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo json_encode(['success' => false, 'error' => 'Phone not found']);
        exit;
    }

    // Generate OTP and expiry
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $exp = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

    // ✅ FIX 2: use placeholders, not "= 2"
    $upd = $pdo->prepare('UPDATE users SET phone_otp_code = ?, phone_otp_expires_at = ? WHERE id = ?');
    $upd->execute([$otp, $exp, $u['id']]);

    // Send SMS
    $text = "Your OTP is {$otp}. Valid 5 min.";
    if (!send_sms($phone, $text)) {
        echo json_encode(['success' => false, 'error' => 'SMS failed']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('forgot_send_sms error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
