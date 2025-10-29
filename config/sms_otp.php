<?php
// config/sms_otp.php

$DIGIMATE_SECRET_KEY = getenv('DIGIMATE_SECRET_KEY') ?: 'GV@Secure9044';
$DIGIMATE_ENDPOINT   = getenv('DIGIMATE_SEND_ENDPOINT') ?: 'https://www.apnapayment.com/api/agent/otp/sendOtp';
$LOG_FILE            = __DIR__ . '/../logs/sms_otp.log';
$SIMULATE_MODE       = getenv('SIMULATE_DIGIMATE') === '1';

// Utility: Log writer
function write_log($msg) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// Utility: JSON response helper
function json_response($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Parse input
$raw = file_get_contents('php://input');
if (!$raw) json_response(400, ['success'=>false,'error'=>'empty request']);
$input = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) json_response(400, ['success'=>false,'error'=>'invalid JSON']);

$action = $input['action'] ?? 'send_otp';
$mobile = $input['mobile'] ?? null;
if (!$mobile) json_response(400, ['success'=>false,'error'=>'missing mobile']);

$mobile = preg_replace('/\D+/', '', $mobile);
if (strlen($mobile) < 9) json_response(400, ['success'=>false,'error'=>'invalid mobile number']);

session_start();

// === SEND OTP ===
if ($action === 'send_otp') {
    $otp = random_int(100000, 999999);
    $_SESSION['otp_' . $mobile] = password_hash((string)$otp, PASSWORD_DEFAULT);
    $_SESSION['otp_exp_' . $mobile] = time() + 300; // 5 min validity

    write_log("Generated OTP for $mobile");

    if ($SIMULATE_MODE) {
        write_log("Simulated OTP $otp for $mobile");
        json_response(200, ['success'=>true,'message'=>'simulated otp sent']);
    }

    $payload = json_encode(['mobile'=>$mobile, 'otp'=>$otp]);
    $headers = [
        'Content-Type: application/json',
        'X-Secret-Key: '.$DIGIMATE_SECRET_KEY
    ];

    $ch = curl_init($DIGIMATE_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        write_log("CURL error: $err");
        json_response(500, ['success'=>false,'error'=>'sms_api_error']);
    }

    write_log("Sent OTP to $mobile, HTTP $code, response: ".substr($res,0,200));
    json_response(200, ['success'=>true,'message'=>'otp_sent','provider_response'=>$res]);
}

// === VERIFY OTP ===
elseif ($action === 'verify_otp') {
    $otp_input = $input['otp'] ?? null;
    if (!$otp_input) json_response(400, ['success'=>false,'error'=>'missing otp']);

    $hash = $_SESSION['otp_'.$mobile] ?? null;
    $exp  = $_SESSION['otp_exp_'.$mobile] ?? 0;

    if (!$hash || time() > $exp) json_response(400, ['success'=>false,'error'=>'otp_expired']);

    if (password_verify((string)$otp_input, $hash)) {
        unset($_SESSION['otp_'.$mobile], $_SESSION['otp_exp_'.$mobile]);
        write_log("OTP verified for $mobile");
        json_response(200, ['success'=>true,'message'=>'otp_verified']);
    } else {
        json_response(400, ['success'=>false,'error'=>'invalid_otp']);
    }
}

// Unknown action
else {
    json_response(400, ['success'=>false,'error'=>'invalid_action']);
}
?>
