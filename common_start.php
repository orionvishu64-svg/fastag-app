<?php
// common_start.php (improved, drop-in)

/* Config */
$sessionDir = __DIR__ . '/sessions';
$sessionGcMaxLifetime = 86400;
$sessionCookieLifetime = 86400;

/* create session dir if missing */
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
    // Try to set owner/group if running as root; ignore failures
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        @chown($sessionDir, 'www-data');
        @chgrp($sessionDir, 'www-data');
    }
    @chmod($sessionDir, 0700);
}

/* compute secure flag and domain */
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, ':') !== false) $host = explode(':', $host, 2)[0];
$domain = $host ?: '';
$secure = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['SERVER_PORT'] ?? '') == 443);

/* ensure session path writable, else fallback to system temp */
$finalSessionPath = $sessionDir;
if (!is_writable($finalSessionPath)) {
    $fallback = sys_get_temp_dir() . '/php_sessions_' . (int)getmyuid();
    @mkdir($fallback, 0700, true);
    if (is_writable($fallback)) {
        $finalSessionPath = $fallback;
        error_log("[common_start] using fallback session dir: $fallback");
    } else {
        error_log("[common_start] WARNING: neither $sessionDir nor $fallback is writable. Sessions may fail.");
    }
}

/* apply settings */
@ini_set('session.save_path', $finalSessionPath);
@ini_set('session.gc_maxlifetime', (string)$sessionGcMaxLifetime);
@ini_set('session.cookie_lifetime', (string)$sessionCookieLifetime);

session_set_cookie_params([
    'lifetime' => $sessionCookieLifetime,
    'path'     => '/',
    'domain'   => '',      // leave empty or set $domain if needed for cross-subdomain cookies
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* normalize session keys so legacy files work */
if (empty($_SESSION['user_id']) && !empty($_SESSION['user']['id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['user']['id'];
}
if (empty($_SESSION['user']) && !empty($_SESSION['user_id'])) {
    $_SESSION['user'] = ['id' => (int) $_SESSION['user_id']];
}

/* helper to get canonical user id */
if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        return (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    }
}

/* try to secure sessions directory (create index.html to avoid listing) */
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @file_put_contents($sessionDir . '/index.html', '<!-- block -->');
}

/* session debug log (ensure parent folder exists and is writable) */
$debugDir = __DIR__ . '/storage';
@mkdir($debugDir, 0750, true);
$debugTo = $debugDir . '/session_debug.log';
if (is_writable($debugDir)) {
    $now = date('Y-m-d H:i:s');
    $sid = session_id() ?: '(no-id)';
    $uid = $_SESSION['user_id'] ?? '(no-user)';
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $req = $_SERVER['REQUEST_URI'] ?? '(no-uri)';
    @file_put_contents($debugTo, "[$now] IP:$remote SID:$sid UID:$uid PATH:$req\n", FILE_APPEND | LOCK_EX);
} else {
    // fallback log to PHP error log so you can see issues in server logs
    error_log("[common_start] storage dir not writable: $debugDir");
}
