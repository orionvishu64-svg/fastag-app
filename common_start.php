<?php

$cookie_lifetime =24 * 3600;
$cookie_path   = '/';
$cookie_domain = ''; // set to '.example.com' if needed
$secure        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$httponly      = true;
$samesite      = 'Lax';

// Set session cookie params before starting session
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $cookie_lifetime,
        'path'     => $cookie_path,
        'domain'   => $cookie_domain,
        'secure'   => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite
    ]);
} else {
    session_set_cookie_params($cookie_lifetime, $cookie_path, $cookie_domain, $secure, $httponly);
}

// Ensure PHP's GC keeps session data for the same duration
@ini_set('session.gc_maxlifetime', (string)$cookie_lifetime);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
