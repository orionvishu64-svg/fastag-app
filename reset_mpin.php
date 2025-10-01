<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = preg_replace('/\D/', '', $in['phone'] ?? '');
    $mpin  = trim($in['mpin'] ?? '');

    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone']);
        exit;
    }
    if (!preg_match('/^\d{4,6}$/', $mpin)) {
        echo json_encode(['success' => false, 'error' => 'mPIN must be 4–6 digits']);
        exit;
    }
    if (($_SESSION['reset_phone_verified'] ?? '') !== $phone) {
        echo json_encode(['success' => false, 'error' => 'Verify OTP first']);
        exit;
    }

    $st = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $st->execute([$phone]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $hash = password_hash($mpin, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET mpin_hash = ?, phone_otp_code = NULL, phone_otp_expires_at = NULL, updated_at = NOW() WHERE id = ?')
        ->execute([$hash, $u['id']]);

    // optional: mark phone verified
    $pdo->prepare('UPDATE users SET phone_verified_at = NOW() WHERE id = ?')->execute([$u['id']]);

    // set cookie so they can mPIN-login on this device
    $payload = ['uid' => (int)$u['id'], 'exp' => time() + AUTH_COOKIE_TTL];
    setcookie(AUTH_COOKIE_NAME, sign_token($payload), [
        'expires'  => time() + AUTH_COOKIE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true, 
        'samesite' => 'Lax',
    ]);

    unset($_SESSION['reset_phone_verified']);
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    error_log('reset_mpin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
