<?php
require_once __DIR__ . '/common_start.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
  $code  = trim($in['code'] ?? '');
  if (!preg_match('/^\d{10}$/', $phone)) { echo json_encode(['success'=>false,'error'=>'Invalid phone']); exit; }
  if (!preg_match('/^\d{6}$/', $code))   { echo json_encode(['success'=>false,'error'=>'Invalid OTP']); exit; }

  $rec = $_SESSION['sms_otp'] ?? null;
  if (!$rec || ($rec['phone'] ?? '') !== $phone) { echo json_encode(['success'=>false,'error'=>'Request a new OTP']); exit; }
  if (time() > ($rec['exp'] ?? 0))               { echo json_encode(['success'=>false,'error'=>'OTP expired']); exit; }
  if (($rec['tries'] ?? 0) >= 5)                 { echo json_encode(['success'=>false,'error'=>'Too many attempts']); exit; }
  $_SESSION['sms_otp']['tries'] = (int)$rec['tries'] + 1;

  $pepper = getenv('OTP_PEPPER') ?: 'rotate-this';
  if (!hash_equals($rec['hash'], hash_hmac('sha256', $code, $pepper))) { echo json_encode(['success'=>false,'error'=>'Invalid OTP']); exit; }

  unset($_SESSION['sms_otp']);
  echo json_encode(['success'=>true]); exit;

} catch (Throwable $e) {
  error_log('otp_verify_sms error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']); exit;
}
