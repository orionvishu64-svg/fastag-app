<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/sms_config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');

    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone']); exit;
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['phone_otp'] = ['phone' => $phone, 'code' => $otp, 'exp' => time() + 300];

    $text = "Your OTP is {$otp}. Valid 5 min.";
    if (!send_sms($phone, $text)) {
        echo json_encode(['success' => false, 'error' => 'SMS send failed']); exit;
    }

    echo json_encode(['success' => true]); exit;
} catch (Throwable $e) {
    error_log('otp_send_sms error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']); exit;
}
