<?php
// /config/check_login.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
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

    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        $rawToken = $_COOKIE[AUTH_COOKIE_NAME];

        $verifyResult = verifyAuthToken($pdo, $rawToken, $rotate = true);

        if ($verifyResult !== false) {
            if (is_string($verifyResult)) {
                $newRawToken = $verifyResult;
                $newHash = hash('sha256', $newRawToken);
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = :th LIMIT 1");
                $stmt->execute([':th' => $newHash]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && !empty($row['id'])) {
                    $_SESSION['user'] = [
                        'id'    => (int)$row['id'],
                        'name'  => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                    ];

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
                    clearAuthCookie();
                    echo json_encode(['logged_in' => false]);
                    exit;
                }
            } elseif (is_int($verifyResult)) {
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

        clearAuthCookie();
    }

    echo json_encode(['logged_in' => false]);
    exit;

} catch (Throwable $e) {
    error_log('/check_login.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['logged_in' => false, 'error' => 'Server error']);
    exit;
}
