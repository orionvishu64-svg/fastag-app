<?php
// /logout.php — secure logout with cookie + DB cleanup
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';            // must expose $pdo
require_once __DIR__ . '/config_auth.php';   // for AUTH_COOKIE_NAME, clearAuthTokenByRaw(), clearAuthCookie(), etc.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // --- 1️⃣ Remove current auth token from DB if cookie present ---
    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        $rawToken = $_COOKIE[AUTH_COOKIE_NAME];
        clearAuthTokenByRaw($pdo, $rawToken);
    }

    // --- 2️⃣ If you want to log the user out from all devices, uncomment below ---
    /*
    if (!empty($_SESSION['user_id'])) {
        clearAllTokensForUser($pdo, (int)$_SESSION['user_id']);
    }
    */

    // --- 3️⃣ Destroy PHP session ---
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // --- 4️⃣ Clear session cookie ---
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    // --- 5️⃣ Clear persistent auth cookie ---
    clearAuthCookie();

    // --- 6️⃣ Redirect to homepage or login page ---
    header('Location: index.html');
    exit;

} catch (Throwable $e) {
    error_log('/logout.php error: ' . $e->getMessage());
    // Fallback logout if something fails
    clearAuthCookie();
    header('Location: index.html');
    exit;
}
