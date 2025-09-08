<?php
// common_start.php — place this at the top of every PHP file (before any output)

$cookie_lifetime = 24 * 3600;  // 24 hours
$idle_timeout    = 30 * 60;    // 30 minutes idle (optional)
$absolute_limit  = 24 * 3600;  // server-side absolute (same as cookie)

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$httponly = true;
$samesite = 'Lax';

// set cookie params BEFORE session_start()
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $cookie_lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite
    ]);
} else {
    session_set_cookie_params($cookie_lifetime, '/', '', $secure, $httponly);
}

@ini_set('session.gc_maxlifetime', (string)$absolute_limit);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Idle timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idle_timeout)) {
    session_unset();
    session_destroy();
    $_SESSION = [];
}
// absolute creation time
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} elseif (time() - $_SESSION['created_at'] > $absolute_limit) {
    session_unset();
    session_destroy();
    $_SESSION = [];
}
$_SESSION['last_activity'] = time();
?>
