<?php
// login.php — mPIN login with cookie-first, then email/phone fallback
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';             // must expose $pdo (PDO)
require_once __DIR__ . '/config_auth.php';    // AUTH_COOKIE_* + sign/verify helpers
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

try {
    // Accept JSON or form (fallback)
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $in = is_array($json) ? $json : $_POST;

    $mpin  = isset($in['mpin'])  ? trim((string)$in['mpin'])  : '';
    $phone = isset($in['phone']) ? preg_replace('/\D/', '', (string)$in['phone']) : '';
    $email = isset($in['email']) ? trim((string)$in['email']) : '';

    // Basic validations
    if (!preg_match('/^\d{4,6}$/', $mpin)) {
        throw new Exception('Invalid mPIN');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }
    if ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
        throw new Exception('Invalid phone');
    }

    // 1) Try device-bound cookie first (fast path)
    $token   = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    $payload = $token ? verify_token($token) : null;

    $u = null;

    if ($payload && isset($payload['uid'])) {
        $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$payload['uid']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // 2) If no valid cookie path, fallback to email/phone login
    if (!$u) {
        if ($phone === '' && $email === '') {
            // must provide an identifier if not using a bound device
            throw new Exception('Please enter your phone or email to log in on this device.');
        }

        if ($phone !== '') {
            $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash FROM users WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
        }

        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            throw new Exception('Account not found. Check your details.');
        }
    }

    // 3) Verify mPIN against stored hash
    if (empty($u['mpin_hash']) || !password_verify($mpin, $u['mpin_hash'])) {
        throw new Exception('Incorrect mPIN');
    }

    // 4) Successful auth → create session user
    $_SESSION['user'] = [
        'id'         => (int)$u['id'],
        'name'       => $u['name'],
        'email'      => $u['email'],
        'phone'      => $u['phone'],
        'login_type' => $u['login_type'],
    ];
    session_regenerate_id(true);
$_SESSION['user_id'] = (int) ($_SESSION['user']['id'] ?? 0);


    // 5) Ensure the device is bound (issue/refresh cookie)
    $payload = ['uid' => (int)$u['id'], 'exp' => time() + AUTH_COOKIE_TTL];
    setcookie(AUTH_COOKIE_NAME, sign_token($payload), [
        'expires'  => time() + AUTH_COOKIE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']), // true only on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // 6) Touch last-activity timestamp
    $pdo->prepare('UPDATE users SET updated_at = NOW() WHERE id = ?')->execute([$u['id']]);

    echo json_encode(['success' => true]); exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
}
