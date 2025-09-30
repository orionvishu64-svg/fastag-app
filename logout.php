<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/config_auth.php';

// Clear session
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
  session_destroy();
}

// Clear session cookie
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// ✅ Also clear mPIN signed cookie
setcookie(AUTH_COOKIE_NAME, '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);

// Redirect to login (or return JSON)
header('Location: index.html');
exit;
