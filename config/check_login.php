<?php
// /config/check_login.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';           // should provide $pdo (PDO)
require_once __DIR__ . '/config_auth.php';  // functions: verifyAuthToken(), setAuthCookie(), clearAuthCookie(), etc.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    // 1) Session-first (existing behavior)
    $user = $_SESSION['user'] ?? null;
    if ($user && !empty($user['id'])) {
        echo json_encode([
            'logged_in' => true,
            'user' => [
                'id'    => (int)$user['id'],
                'name'  => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'phone' => $user['phone'] ?? null,
            ]
        ]);
        exit;
    }

    // 2) Cookie fallback: verify persistent token and restore session
    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        $rawToken = $_COOKIE[AUTH_COOKIE_NAME];

        // verifyAuthToken() returns: NEW_RAW_TOKEN (string) on rotate=true success, or false on failure.
        $verifyResult = verifyAuthToken($pdo, $rawToken, $rotate = true);

        if ($verifyResult !== false) {
            if (is_string($verifyResult)) {
                $newRawToken = $verifyResult;
                // lookup user by the new token's hash
                $newHash = hash('sha256', $newRawToken);
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = :th LIMIT 1");
                $stmt->execute([':th' => $newHash]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && !empty($row['id'])) {
                    // restore session user in the same shape as before
                    $_SESSION['user'] = [
                        'id'    => (int)$row['id'],
                        'name'  => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                    ];

                    // send rotated cookie to client
                    setAuthCookie($newRawToken, AUTH_COOKIE_TTL);

                    echo json_encode([
                        'logged_in' => true,
                        'user' => [
                            'id'    => (int)$row['id'],
                            'name'  => $row['name'] ?? null,
                            'email' => $row['email'] ?? null,
                            'phone' => $row['phone'] ?? null,
                        ]
                    ]);
                    exit;
                } else {
                    // Unexpected: rotated token created but user row not found -> clear cookie and fail
                    clearAuthCookie();
                    echo json_encode(['logged_in' => false]);
                    exit;
                }
            } elseif (is_int($verifyResult)) {
                // If verifyAuthToken was called with rotate=false it would return user_id (not used here)
                $uid = $verifyResult;
                $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $uid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $_SESSION['user'] = [
                        'id'    => (int)$row['id'],
                        'name'  => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                    ];
                    echo json_encode([
                        'logged_in' => true,
                        'user' => [
                            'id'    => (int)$row['id'],
                            'name'  => $row['name'] ?? null,
                            'email' => $row['email'] ?? null,
                            'phone' => $row['phone'] ?? null,
                        ]
                    ]);
                    exit;
                } else {
                    clearAuthCookie();
                    echo json_encode(['logged_in' => false]);
                    exit;
                }
            }
        }

        // cookie verify failed
        clearAuthCookie();
    }

    // not logged in
    echo json_encode(['logged_in' => false]);
    exit;

} catch (Throwable $e) {
    error_log('/check_login.php error: ' . $e->getMessage());
    // Be conservative on error — don't reveal internals
    http_response_code(500);
    echo json_encode(['logged_in' => false, 'error' => 'Server error']);
    exit;
}
