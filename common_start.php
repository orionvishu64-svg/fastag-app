<?php
// common_start.php
// Put this at the top of every script before any output

// --- Configuration: adjust as needed ---
$sessionDir = __DIR__ . '/sessions';   // directory inside project (not /tmp)
$sessionGcMaxLifetime = 86400;        // 1 day (seconds)
$sessionCookieLifetime = 86400;       // 1 day (seconds) -> persistent cookie
// ---------------------------------------

/** Ensure session directory exists and is writable by webserver user */
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
    // try to ensure ownership to www-data (best-effort)
    @chown($sessionDir, 'www-data');
    @chgrp($sessionDir, 'www-data');
}
@chmod($sessionDir, 0700);

/* enforce stable session storage */
ini_set('session.save_path', $sessionDir);
ini_set('session.gc_maxlifetime', (string)$sessionGcMaxLifetime);
ini_set('session.cookie_lifetime', (string)$sessionCookieLifetime);

/* determine host/domain without port for cookie domain */
$host = $_SERVER['HTTP_HOST'] ?? '';
// strip port if present
if (strpos($host, ':') !== false) {
    $host = explode(':', $host, 2)[0];
}
$domain = $host ?: '';

/* determine secure flag (only true if HTTPS) */
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;

/* set cookie params so PHP will send persistent cookie */
session_set_cookie_params([
  'lifetime' => $sessionCookieLifetime, // you already set this
  'path'     => '/',
  'domain'   => '',          // leave empty unless you need a specific domain
  'secure'   => !empty($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax',
]);

/* start session if not already started */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Optional: attach a simple debug log for session lifecycle (disable in production) */
$debugTo = __DIR__ . '/storage/session_debug.log';
if (is_writable(dirname($debugTo))) {
    $now = date('Y-m-d H:i:s');
    $sid = session_id() ?: '(no-id)';
    $uid = $_SESSION['user_id'] ?? '(no-user)';
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    @file_put_contents($debugTo, "[$now] IP:$remote SID:$sid UID:$uid PATH:{$_SERVER['REQUEST_URI']}\n", FILE_APPEND | LOCK_EX);
}
