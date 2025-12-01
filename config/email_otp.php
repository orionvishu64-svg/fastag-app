<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if ($origin && $origin === $serverOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_readable($autoload)) {
    error_log('email_otp: vendor autoload not found: ' . $autoload);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server misconfiguration']);
    exit;
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$envFile = __DIR__ . '/../.env.php';
if (!is_readable($envFile)) {
    error_log('email_otp: env file missing: ' . $envFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mail configuration missing']);
    exit;
}
$config = require $envFile;
if (!is_array($config)) {
    error_log('email_otp: env file did not return array');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mail configuration invalid']);
    exit;
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function mailer(array $c): PHPMailer {
    foreach (['SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USERNAME','SMTP_PASSWORD','FROM_EMAIL','FROM_NAME'] as $k) {
        if (!isset($c[$k]) || $c[$k] === '') {
            throw new RuntimeException("Missing mail config key: $k");
        }
    }

    $m = new PHPMailer(true);
    $enableDebug = !empty($c['SMTP_DEBUG']);
    if ($enableDebug) {
        $m->SMTPDebug = 2;
        $m->Debugoutput = function($str, $level) {
            error_log('[PHPMailer] ' . trim((string)$str));
        };
    }

    $m->isSMTP();
    $m->Host       = $c['SMTP_HOST'];
    $m->SMTPAuth   = true;
    $m->Username   = $c['SMTP_USERNAME'];
    $m->Password   = $c['SMTP_PASSWORD'];
    if (!empty($c['SMTP_SECURE'])) {
        $m->SMTPSecure = $c['SMTP_SECURE'];
    }
    $m->Port       = (int)$c['SMTP_PORT'];
    $m->setFrom($c['FROM_EMAIL'], $c['FROM_NAME']);
    if (!empty($c['REPLY_TO'])) {
        $m->addReplyTo($c['REPLY_TO'], $c['FROM_NAME']);
    }
    $m->isHTML(true);
    return $m;
}

$raw = file_get_contents('php://input');
$in = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $in = $decoded;
    } else {
        json_response(['success' => false, 'error' => 'Invalid JSON input'], 400);
    }
} else {
    $in = $_POST ?: [];
}

$action = trim((string)($in['action'] ?? ''));

if ($action === 'send') {
    try {
        $email = trim((string)($in['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'error' => 'Invalid email'], 400);
        }

        $now = time();
        $rec = $_SESSION['email_otp'] ?? null;
        if ($rec && ($rec['email'] ?? '') === $email && ($rec['last'] ?? 0) > ($now - 60)) {
            json_response(['success' => false, 'error' => 'Please wait before requesting a new OTP'], 429);
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pepper = getenv('OTP_PEPPER') ?: ($config['OTP_PEPPER'] ?? 'rotate-this');
        $hash = hash_hmac('sha256', $code, $pepper);
        $exp = $now + 300;

        $mail = mailer($config);
        $mail->addAddress($email);
        $mail->Subject = $config['OTP_SUBJECT'] ?? 'Your Email OTP';
        $mail->Body = ($config['OTP_HTML_TEMPLATE'] ?? "Your OTP is <b>{$code}</b>. It expires in 5 minutes. Do not share it.");
        $mail->AltBody = ($config['OTP_TEXT_TEMPLATE'] ?? "Your OTP is {$code}. It expires in 5 minutes. Do not share it.");

        try {
            $mail->send();
        } catch (Exception $e) {
            error_log('email_otp send error: ' . $e->getMessage());
            json_response(['success' => false, 'error' => 'Failed to send OTP email'], 500);
        }

        $_SESSION['email_otp'] = [
            'email' => $email,
            'hash'  => $hash,
            'exp'   => $exp,
            'tries' => 0,
            'last'  => $now,
        ];

        json_response(['success' => true]);

    } catch (Throwable $e) {
        error_log('email_otp send exception: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Server error'], 500);
    }
}
if ($action === 'verify') {
    try {
        $email = trim((string)($in['email'] ?? ''));
        $code  = trim((string)($in['code'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'error' => 'Invalid email'], 400);
        }
        if (!preg_match('/^\d{6}$/', $code)) {
            json_response(['success' => false, 'error' => 'Invalid OTP format'], 400);
        }
        $rec = $_SESSION['email_otp'] ?? null;
        if (!$rec || ($rec['email'] ?? '') !== $email) {
            json_response(['success' => false, 'error' => 'No OTP request found for this email'], 400);
        }
        if (time() > ($rec['exp'] ?? 0)) {
            unset($_SESSION['email_otp']);
            json_response(['success' => false, 'error' => 'OTP expired'], 400);
        }
        if (($rec['tries'] ?? 0) >= 5) {
            unset($_SESSION['email_otp']);
            json_response(['success' => false, 'error' => 'Too many attempts'], 429);
        }

        $_SESSION['email_otp']['tries'] = (int)($rec['tries'] ?? 0) + 1;

        $pepper = getenv('OTP_PEPPER') ?: ($config['OTP_PEPPER'] ?? 'rotate-this');
        $calc = hash_hmac('sha256', $code, $pepper);
        if (!hash_equals((string)($rec['hash'] ?? ''), $calc)) {
            json_response(['success' => false, 'error' => 'Invalid OTP'], 400);
        }

        unset($_SESSION['email_otp']);
        $_SESSION['email_verified'] = $email;

        json_response(['success' => true]);

    } catch (Throwable $e) {
        error_log('email_otp verify exception: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Server error'], 500);
    }
}
json_response(['success' => false, 'error' => 'Invalid or missing action (use send or verify)'], 400);
