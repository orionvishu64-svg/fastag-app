<?php
require_once __DIR__ . '/common_start.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $email = trim($in['email'] ?? '');
  $code  = trim($in['code'] ?? '');

  if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'error'=>'Invalid email']); exit;
  }
  if (!preg_match('/^\d{6}$/', $code)) {
    echo json_encode(['success'=>false,'error'=>'Invalid OTP']); exit;
  }

  $rec = $_SESSION['email_otp'] ?? null;
  if (!$rec || ($rec['email'] ?? '') !== $email) {
    echo json_encode(['success'=>false,'error'=>'Request a new OTP']); exit;
  }
  if (time() > ($rec['exp'] ?? 0)) {
    echo json_encode(['success'=>false,'error'=>'OTP expired']); exit;
  }
  if (($rec['tries'] ?? 0) >= 5) {
    echo json_encode(['success'=>false,'error'=>'Too many attempts']); exit;
  }
  $_SESSION['email_otp']['tries'] = (int)$rec['tries'] + 1;

  $pepper = getenv('OTP_PEPPER') ?: 'rotate-this';
  $calc   = hash_hmac('sha256', $code, $pepper);

  if (!hash_equals($rec['hash'], $calc)) {
    echo json_encode(['success'=>false,'error'=>'Invalid OTP']); exit;
  }

  unset($_SESSION['email_otp']);
  $_SESSION['email_verified'] = $email;
  echo json_encode(['success'=>true]); exit;

} catch (Throwable $e) {
  error_log('otp_verify_email error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']); exit;
}
