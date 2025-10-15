<?php
// /config_auth.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// NOTE: Some callers included this file for AJAX endpoints; keep header if desired.
// If you include this file from page templates, you may want to remove the header().
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

/* ----------------- Configuration constants ----------------- */
const AUTH_COOKIE_NAME   = 'login_user';
const AUTH_COOKIE_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_AT_LEAST_64_BYTES';
/*
 * AUTH_COOKIE_TTL is seconds (default: 120 days).
 * You may change to any number of seconds you want the cookie to live.
 */
const AUTH_COOKIE_TTL    = 60 * 60 * 24 * 120;

const AUTH_COOKIE_PATH   = '/';
const AUTH_COOKIE_DOMAIN = ''; // set if needed (e.g. '.example.com')
const AUTH_COOKIE_SAMESITE = 'Lax';

/* ----------------- Low-level cookie helpers ----------------- */

function setAuthCookie(string $rawToken, int $ttl = AUTH_COOKIE_TTL) : void {
    $expire = time() + $ttl;
    $options = [
        'expires' => $expire,
        'path'    => AUTH_COOKIE_PATH,
        'domain'  => (AUTH_COOKIE_DOMAIN !== '') ? AUTH_COOKIE_DOMAIN : null,
        'secure'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'=> true,
        'samesite'=> AUTH_COOKIE_SAMESITE
    ];
    // PHP setcookie supports options array in 7.3+
    setcookie(AUTH_COOKIE_NAME, $rawToken, $options);
    // Also update superglobal for immediate availability in same request
    $_COOKIE[AUTH_COOKIE_NAME] = $rawToken;
}

function clearAuthCookie(): void {
    $options = [
        'expires' => time() - 3600,
        'path'    => AUTH_COOKIE_PATH,
        'domain'  => (AUTH_COOKIE_DOMAIN !== '') ? AUTH_COOKIE_DOMAIN : null,
        'secure'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'=> true,
        'samesite'=> AUTH_COOKIE_SAMESITE
    ];
    setcookie(AUTH_COOKIE_NAME, '', $options);
    unset($_COOKIE[AUTH_COOKIE_NAME]);
}

/* ----------------- Token generation & DB helpers (auth_tokens table) -----------------
  Table expected structure (example):
  CREATE TABLE auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (token_hash),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id)
      REFERENCES users(id)
      ON DELETE CASCADE
  );
-------------------------------------------------------------------------- */

/**
 * createAuthToken
 * - inserts token_hash into auth_tokens and returns raw token for client cookie
 */
function createAuthToken(PDO $pdo, int $user_id, int $ttl = AUTH_COOKIE_TTL) : string {
    $raw = bin2hex(random_bytes(32)); // 64 hex chars
    $hash = hash('sha256', $raw);
    $expires_at = (new DateTime())->modify("+{$ttl} seconds")->format('Y-m-d H:i:s');
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, user_agent, ip_address, expires_at) VALUES (:uid, :th, :ua, :ip, :exp)");
    $stmt->execute([
        ':uid' => $user_id,
        ':th'  => $hash,
        ':ua'  => $user_agent,
        ':ip'  => $ip,
        ':exp' => $expires_at
    ]);

    return $raw;
}

/**
 * verifyAuthToken
 * - Verifies raw token against auth_tokens table.
 * - If $rotate is true, removes the used DB row and creates a new token, returning the NEW raw token string.
 * - If $rotate is false, returns user_id (int) on success.
 * - Returns false on failure.
 */
function verifyAuthToken(PDO $pdo, string $rawToken, bool $rotate = true) {
    $hash = hash('sha256', $rawToken);
    $now = (new DateTime())->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT id, user_id, expires_at FROM auth_tokens WHERE token_hash = :th LIMIT 1");
    $stmt->execute([':th' => $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    if ($row['expires_at'] < $now) {
        // expired: remove row
        $del = $pdo->prepare("DELETE FROM auth_tokens WHERE id = :id");
        $del->execute([':id' => $row['id']]);
        return false;
    }

    $user_id = (int)$row['user_id'];

    if ($rotate) {
        // rotate: remove old token and issue a new one
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM auth_tokens WHERE id = :id");
            $del->execute([':id' => $row['id']]);

            $newRaw = createAuthToken($pdo, $user_id, AUTH_COOKIE_TTL);
            $pdo->commit();
            return $newRaw;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('verifyAuthToken rotate error: ' . $e->getMessage());
            return false;
        }
    } else {
        return $user_id;
    }
}

/**
 * clearAuthTokenByHash
 * - removes a single token by its raw token hash (used on logout)
 */
function clearAuthTokenByRaw(PDO $pdo, string $rawToken) : void {
    $hash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token_hash = :th");
    $stmt->execute([':th' => $hash]);
}

/**
 * clearAllTokensForUser
 * - optionally clear all tokens for a user (logout everywhere)
 */
function clearAllTokensForUser(PDO $pdo, int $user_id) : void {
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
}

/* ----------------- Backwards-compatible (signed payload) helpers kept for reference -----------------
   The project previously used a signed JSON payload cookie. We are now using random tokens,
   but keeping these functions does not interfere. You may remove them if not used.
---------------------------------------------------------------------------- */
function sign_token(array $payload): string {
    $b = base64_encode(json_encode($payload));
    $sig = hash_hmac('sha256', $b, AUTH_COOKIE_SECRET);
    return $b . '.' . $sig;
}

function verify_token(string $token): ?array {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    [$b, $sig] = $parts;
    if (!hash_equals(hash_hmac('sha256', $b, AUTH_COOKIE_SECRET), $sig)) return null;
    $p = json_decode(base64_decode($b), true);
    if (!$p) return null;
    if (($p['exp'] ?? 0) < time()) return null;
    return $p;
}
