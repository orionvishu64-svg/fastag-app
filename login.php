<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $in   = json_decode(file_get_contents('php://input'), true) ?: [];
    $mpin = trim($in['mpin'] ?? '');

    if (!preg_match('/^\d{4,6}$/', $mpin)) throw new Exception('Invalid mPIN');

    $token   = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    $payload = $token ? verify_token($token) : null;
    if (!$payload) {
        throw new Exception('Device not recognized. Use "Forgot mPIN" to bind this device.');
    }
    $uid = (int)($payload['uid'] ?? 0);
    if ($uid <= 0) throw new Exception('Invalid identifier');

    $st = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u || empty($u['mpin_hash']) || !password_verify($mpin, $u['mpin_hash'])) {
        throw new Exception('Incorrect mPIN');
    }

    // Log in
    $_SESSION['user'] = [
        'id'         => (int)$u['id'],
        'name'       => $u['name'],
        'email'      => $u['email'],
        'phone'      => $u['phone'],
        'login_type' => $u['login_type'],
    ];

    $pdo->prepare('UPDATE users SET updated_at = NOW() WHERE id = ?')->execute([$u['id']]);

    echo json_encode(['success' => true]); exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
}
