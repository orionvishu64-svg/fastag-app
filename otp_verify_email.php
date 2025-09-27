<?php
require_once __DIR__ . '/common_start.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($in['email'] ?? '');
    $code  = trim($in['code']  ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email']); exit;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP']); exit;
    }

    $rec = $_SESSION['email_otp'] ?? null;
    if (!$rec || ($rec['email'] ?? '') !== $email) {
        echo json_encode(['success' => false, 'error' => 'No OTP requested']); exit;
    }
    if (time() > ($rec['exp'] ?? 0)) {
        echo json_encode(['success' => false, 'error' => 'OTP expired']); exit;
    }
    if (!hash_equals($rec['code'], $code)) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP']); exit;
    }

    $_SESSION['email_verified'] = $email;
    unset($_SESSION['email_otp']);

    echo json_encode(['success' => true]); exit;
} catch (Throwable $e) {
    error_log('otp_verify_email error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']); exit;
}
