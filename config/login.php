<?php
// config/login.php — mPIN-only login using device cookie (uses sign_token/verify_token)
// Expects JSON body: { "mpin": "1234" }
// Requires: config_auth.php (AUTH_COOKIE_NAME, AUTH_COOKIE_TTL, verify_token, sign_token), db.php ($pdo)

require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';
error_log('DEBUG login.php - HTTP_COOKIE header: ' . ($_SERVER['HTTP_COOKIE'] ?? '(none)'));
error_log('DEBUG login.php - $_COOKIE snapshot: ' . json_encode($_COOKIE));

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

try {
    // Accept JSON body
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true) ?: [];

    $mpin = isset($in['mpin']) ? trim((string)$in['mpin']) : '';
    if ($mpin === '' || !preg_match('/^\d{4,6}$/', $mpin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'invalid_mpin_format', 'message' => 'mPIN must be 4-6 digits.']);
        exit;
    }

    // Get token from cookie
    $cookieName = defined('AUTH_COOKIE_NAME') ? AUTH_COOKIE_NAME : 'login_user';
    $token = $_COOKIE[$cookieName] ?? null;
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'no_device_cookie',
            'message' => 'Device not recognized. Please register or sign in once to bind this device.'
        ]);
        exit;
    }

    if (!function_exists('verify_token')) {
        error_log('login.php: verify_token() missing in config_auth.php');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'server_misconfigured']);
        exit;
    }

    $payload = verify_token($token);
    error_log('DEBUG login.php - verify_token payload: ' . json_encode($payload));
    if (!$payload || empty($payload['uid'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'invalid_device_token',
            'message' => 'Device token invalid or expired. Please re-authenticate (register/login).'
        ]);
        exit;
    }

    $userId = (int)$payload['uid'];

    // Fetch user by id
    $stmt = $pdo->prepare("SELECT id, name, email, phone, mpin_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'user_not_found', 'message' => 'User not found.']);
        exit;
    }

    // Verify mPIN using password_verify (matches register.php)
    if (empty($user['mpin_hash']) || !password_verify($mpin, $user['mpin_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'invalid_mpin', 'message' => 'Invalid mPIN.']);
        exit;
    }

    // Successful auth: rotate/refresh token payload and cookie
    $ttl = defined('AUTH_COOKIE_TTL') ? (int)AUTH_COOKIE_TTL : (60*60*24*120); // fallback 120 days
    $newPayload = [
        'uid' => (int)$user['id'],
        'iat' => time(),
        'exp' => time() + $ttl
    ];

    // Use sign_token if available (register used sign_token). Fallback to existing token if not.
    if (!function_exists('sign_token')) {
        // fallback: reuse existing token (but log to help troubleshooting)
        error_log('login.php: sign_token() not found in config_auth.php — reusing existing token');
        $newToken = $token;
    } else {
        $newToken = sign_token($newPayload);
    }

    // Set cookie (match register behavior)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    // ensure domain left default here; change if you need a specific domain
    setcookie($cookieName, $newToken, [
        'expires'  => $newPayload['exp'],
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Return success with minimal user info (no secrets)
    echo json_encode([
        'success' => true,
        'message' => 'login_success',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null
        ]
    ]);
    exit;

} catch (PDOException $e) {
    error_log('login.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_db_error', 'message' => 'Database error']);
    exit;
} catch (Throwable $e) {
    error_log('login.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error', 'message' => 'Server error']);
    exit;
}
