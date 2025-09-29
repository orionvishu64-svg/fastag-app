<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/mail_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $email = trim($in['email'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'error'=>'Invalid email']); exit; }

  $pepper = getenv('OTP_PEPPER') ?: 'rotate-this';
  $now = time();
  $rec = $_SESSION['email_otp'] ?? null;
  if ($rec && ($rec['email'] ?? '') === $email && ($rec['last'] ?? 0) > ($now - 60)) {
    echo json_encode(['success'=>false,'error'=>'Please wait 60s before requesting a new OTP']); exit;
  }

  $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
  $hash = hash_hmac('sha256', $code, $pepper);
  $exp  = $now + 300;

  $mail = mailer();
  $mail->addAddress($email);
  $mail->Subject = 'Your Email OTP';
  $mail->Body    = "Your OTP is <b>{$code}</b>. It expires in 5 minutes. Do not share it.";
  $mail->AltBody = "Your OTP is {$code}. It expires in 5 minutes. Do not share it.";
  $mail->send();

  $_SESSION['email_otp'] = ['email'=>$email,'hash'=>$hash,'exp'=>$exp,'tries'=>0,'last'=>$now];
  echo json_encode(['success'=>true]); exit;

} catch (Throwable $e) {
  error_log('otp_send_email error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']); exit;
}
