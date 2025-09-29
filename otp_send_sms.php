<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/sms_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
  if (!preg_match('/^\d{10}$/', $phone)) { echo json_encode(['success'=>false,'error'=>'Invalid phone']); exit; }

  $now = time();
  $rec = $_SESSION['sms_otp'] ?? null;
  if ($rec && ($rec['phone'] ?? '') === $phone && ($rec['last'] ?? 0) > ($now - 60)) {
    echo json_encode(['success'=>false,'error'=>'Please wait 60s before requesting a new OTP']); exit;
  }

  $pepper = getenv('OTP_PEPPER') ?: 'rotate-this';
  $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
  $hash = hash_hmac('sha256', $code, $pepper);
  $exp  = $now + 300;

  $msg = "Your OTP is {$code}. It expires in 5 minutes. Do not share it.";
  if (!send_sms($phone, $msg)) { http_response_code(502); echo json_encode(['success'=>false,'error'=>'SMS provider failed']); exit; }

  $_SESSION['sms_otp'] = ['phone'=>$phone,'hash'=>$hash,'exp'=>$exp,'tries'=>0,'last'=>$now];
  echo json_encode(['success'=>true]); exit;

} catch (Throwable $e) {
  error_log('otp_send_sms error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']); exit;
}
