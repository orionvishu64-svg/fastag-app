<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__.'/.env.php';
if (!is_readable($env)) { 
    error_log('Mail env missing: '.$env); 
    throw new RuntimeException('Mail config missing'); 
}
function mailer(): PHPMailer {
    $c = require __DIR__.'/.env.php';

    foreach (['SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USERNAME','SMTP_PASSWORD','FROM_EMAIL','FROM_NAME'] as $k) {
        if (!isset($c[$k]) || $c[$k]==='') {
            throw new RuntimeException("Missing mail config key: $k");
        }
    }

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $c['SMTP_HOST'];
    $m->SMTPAuth   = true;
    $m->Username   = $c['SMTP_USERNAME'];
    $m->Password   = $c['SMTP_PASSWORD'];
    $m->SMTPSecure = $c['SMTP_SECURE'];
    $m->Port       = $c['SMTP_PORT'];
    $m->setFrom($c['FROM_EMAIL'], $c['FROM_NAME']);
    if (!empty($c['REPLY_TO'])) $m->addReplyTo($c['REPLY_TO'], $c['FROM_NAME']);
    $m->isHTML(true);
    return $m;
}

require_once __DIR__ . '/common_start.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents("php://input");
$in = json_decode($raw, true) ?: [];
$action = $in['action'] ?? '';
if ($action === "send") {

    try {
        $email = trim($in['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'error'=>'Invalid email']); exit;
        }

        $pepper = getenv('OTP_PEPPER') ?: 'rotate-this';
        $now = time();

        // 60s resend throttle
        $rec = $_SESSION['email_otp'] ?? null;
        if ($rec && ($rec['email'] ?? '') === $email && ($rec['last'] ?? 0) > ($now - 60)) {
            echo json_encode(['success'=>false,'error'=>'Please wait 60s before requesting a new OTP']); exit;
        }

        $code = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT);
        $hash = hash_hmac('sha256', $code, $pepper);
        $exp  = $now + 300; // 5 minutes

        $mail = mailer();
        $mail->addAddress($email);
        $mail->Subject = 'Your Email OTP';
        $mail->Body    = "Your OTP is <b>{$code}</b>. It expires in 5 minutes. Do not share it.";
        $mail->AltBody = "Your OTP is {$code}. It expires in 5 minutes. Do not share it.";
        $mail->send();

        $_SESSION['email_otp'] = [
            'email'=>$email, 
            'hash'=>$hash, 
            'exp'=>$exp, 
            'tries'=>0, 
            'last'=>$now
        ];

        echo json_encode(['success'=>true]); 
        exit;

    } catch (Throwable $e) {
        error_log('otp_send_email error: '.$e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Server error']); 
        exit;
    }
}
if ($action === "verify") {

    try {
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

        echo json_encode(['success'=>true]); 
        exit;

    } catch (Throwable $e) {
        error_log('otp_verify_email error: '.$e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Server error']); 
        exit;
    }
}
echo json_encode([
    "success" => false,
    "error"   => "Invalid or missing action (use send or verify)"
]);
exit;