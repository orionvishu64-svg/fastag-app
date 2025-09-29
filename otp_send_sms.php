<?php
require_once __DIR__ . '/common_start.php';
if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
  $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
  if (!preg_match('/^\d{10}$/', $phone)) { echo json_encode(['success'=>false,'error'=>'Invalid phone']); exit; }

  // throttle: 60s
  $now = time();
  $rec = $_SESSION['sms_otp'] ?? null;
  if ($rec && ($rec['phone'] ?? '') === $phone && ($rec['last'] ?? 0) > ($now - 60)) {
    echo json_encode(['success'=>false,'error'=>'Please wait 60s before requesting a new OTP']); exit;
  }

  $apiKey = getenv('TWO_FACTOR_API_KEY');
  if (!$apiKey) { echo json_encode(['success'=>false,'error'=>'SMS provider not configured']); exit; }

  // *** FORCE SMS (not voice) ***
  $url = "https://2factor.in/API/V1/{$apiKey}/SMS/+91{$phone}/AUTOGEN";
  error_log("2F SEND URL: ".$url);

  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err || $code !== 200) {
    error_log("2F AUTOGEN(SMS) failed http={$code} err={$err} resp={$resp}");
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'SMS provider failed']); exit;
  }

  $j = json_decode($resp, true);
  if (!is_array($j) || ($j['Status'] ?? '') !== 'Success' || empty($j['Details'])) {
    error_log("2F AUTOGEN(SMS) non-success resp={$resp}");
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'SMS provider failed']); exit;
  }

  $_SESSION['sms_otp'] = [
    'phone'      => $phone,
    'session_id' => $j['Details'], // save provider SessionId
    'exp'        => $now + 300,
    'tries'      => 0,
    'last'       => $now,
  ];

  echo json_encode(['success'=>true]); exit;

} catch (Throwable $e) {
  error_log('otp_send_sms error: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']); exit;
}
