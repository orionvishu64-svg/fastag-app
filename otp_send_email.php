<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/mail_config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($in['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'error'=>'Invalid email']); exit; }
$otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
$_SESSION['email_otp'] = ['email'=>$email,'code'=>$otp,'exp'=>time()+300];
try {
  $mail = mailer(); $mail->addAddress($email);
  $mail->Subject='Your Email OTP'; $mail->Body="Your OTP is: {$otp}. It expires in 5 minutes."; $mail->AltBody=$mail->Body;
  $mail->send(); echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  error_log('Email OTP send failed: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>'Failed to send email OTP']);
}
