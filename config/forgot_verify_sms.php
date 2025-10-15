<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
    $code  = trim($in['code'] ?? '');

    // Basic validation
    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone']);
        exit;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
        exit;
    }

    // Look up user + OTP
    $st = $pdo->prepare('SELECT id, phone_otp_code, phone_otp_expires_at FROM users WHERE phone = ? LIMIT 1');
    $st->execute([$phone]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo json_encode(['success' => false, 'error' => 'Phone not found']);
        exit;
    }

    // Expiry & presence checks
    if (empty($u['phone_otp_code']) || empty($u['phone_otp_expires_at'])) {
        echo json_encode(['success' => false, 'error' => 'OTP expired']);
        exit;
    }
    if (new DateTime() > new DateTime($u['phone_otp_expires_at'])) {
        echo json_encode(['success' => false, 'error' => 'OTP expired']);
        exit;
    }

    // Constant-time compare
    if (!hash_equals($u['phone_otp_code'], $code)) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
        exit;
    }

    // Mark phone verified for the reset step
    $_SESSION['reset_phone_verified'] = $phone;

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    error_log('forgot_verify_sms error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
