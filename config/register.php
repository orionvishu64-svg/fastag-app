<?php
// config/register.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $name  = trim($in['name']  ?? '');
    $email = trim($in['email'] ?? '');
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
    $mpin  = trim($in['mpin']  ?? '');

    if ($name === '') throw new Exception('Name required');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Valid email required');
    if (!preg_match('/^\d{10}$/', $phone)) throw new Exception('Phone must be 10 digits');
    if (!preg_match('/^\d{4,6}$/', $mpin))
        throw new Exception('mPIN must be 4–6 digits');

    if (($_SESSION['phone_verified'] ?? '') !== $phone) {
        throw new Exception('Verify phone OTP first');
    }

    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) throw new Exception('Email already registered');

    $st = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $st->execute([$phone]);
    if ($st->fetch()) throw new Exception('Phone already registered');

    $mpin_hash = password_hash($mpin, PASSWORD_DEFAULT);

    $phone_verified_at = (new DateTime())->format('Y-m-d H:i:s');

    $ins = $pdo->prepare('
        INSERT INTO users
        (name, email, phone, mpin_hash, google_id, login_type,
         email_verified_at, phone_verified_at, is_verified,
         created_at, updated_at, has_filled_partner_form)
        VALUES
        (?, ?, ?, ?, NULL, "manual",
         NULL, ?, 1,
         NOW(), NOW(), 0)
    ');
    $ins->execute([
        $name,
        $email,
        $phone,
        $mpin_hash,
        $phone_verified_at
    ]);

    $uid = (int)$pdo->lastInsertId();

    $payload = ['uid' => $uid, 'exp' => time() + AUTH_COOKIE_TTL];
    setcookie(AUTH_COOKIE_NAME, sign_token($payload), [
        'expires'  => time() + AUTH_COOKIE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_SESSION['phone_verified'], $_SESSION['phone_otp']);

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}