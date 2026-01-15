<?php
// /config/login.php

require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

define('SAVE_MPIN_HASH', false);

try {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $in = is_array($json) ? $json : $_POST;

    $mpin  = isset($in['mpin'])  ? trim((string)$in['mpin'])  : '';
    $phone = isset($in['phone']) ? preg_replace('/\D/', '', (string)$in['phone']) : '';
    $email = isset($in['email']) ? trim((string)$in['email']) : '';

    if (!preg_match('/^\d{4,6}$/', $mpin)) {
        throw new Exception('Invalid mPIN');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }
    if ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
        throw new Exception('Invalid phone');
    }

    $rawCookieToken = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    $u = null;

    if ($rawCookieToken) {
        try {
            $verifyUid = verifyAuthToken($pdo, $rawCookieToken, $rotate = false);
        } catch (Throwable $ex) {
            error_log('/login.php: verifyAuthToken failed: '.$ex->getMessage());
            $verifyUid = false;
        }

        if ($verifyUid !== false && is_int($verifyUid)) {
            $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash, avatar_url, remote_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$verifyUid]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($u) {
                try {
                    $newRaw = createAuthToken($pdo, (int)$u['id'], AUTH_COOKIE_TTL);
                    setAuthCookie($newRaw, AUTH_COOKIE_TTL);
                } catch (Throwable $ex2) {
                    error_log('/login.php: rotate auth token failed: '.$ex2->getMessage());
                }
            } else {
                clearAuthCookie();
                $u = null;
            }
        } else {
            clearAuthCookie();
        }
    }

    $provider_user_not_found = false;
    if (!$u && !empty($phone)) {
        $provider_url = 'https://www.apnapayment.com/api/agent/loginMpin';
        $payload = json_encode(['mobile' => $phone, 'mpin' => $mpin]);

        $ch = curl_init($provider_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $prov_resp = curl_exec($ch);
        $prov_err = curl_error($ch);
        $prov_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($prov_err) {
            error_log('/login.php: provider curl error: '.$prov_err);
        } else {
            $prov_json = json_decode($prov_resp, true);

            $prov_success = ($prov_status === 200 && isset($prov_json['success']) && stripos((string)$prov_json['success'], 'verified') !== false);
            $prov_not_found = ($prov_status === 404)
                || (!empty($prov_json['message']) && stripos($prov_json['message'], 'not found') !== false)
                || (!empty($prov_json['code']) && in_array($prov_json['code'], ['AGENT_NOT_FOUND', '404', 'NOT_FOUND']));
            $prov_invalid_mpin = (!empty($prov_json['error']) && stripos((string)$prov_json['error'], 'invalid') !== false)
                || (!empty($prov_json['message']) && stripos((string)$prov_json['message'], 'invalid') !== false)
                || ($prov_status === 401);

            if ($prov_success) {
                $pdata = $prov_json['data'] ?? [];

                $prov_user = [
                    'remote_id'  => $pdata['id'] ?? null,
                    'name'       => trim(($pdata['first_name'] ?? '') . ' ' . ($pdata['last_name'] ?? '')),
                    'email'      => $pdata['email'] ?? null,
                    'avatar_url' => $pdata['image'] ?? null,
                    'phone'      => $phone
                ];

                try {
                    $existing = null;

                    if (!empty($prov_user['remote_id'])) {
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE remote_id = :rid LIMIT 1');
                        $stmt->execute([':rid' => $prov_user['remote_id']]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if (!$existing && !empty($phone)) {
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
                        $stmt->execute([':phone' => $phone]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if (!$existing && !empty($prov_user['email'])) {
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                        $stmt->execute([':email' => $prov_user['email']]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($existing) {
                        $local_id = $existing['id'];
                        if (SAVE_MPIN_HASH) {
                            $sql = 'UPDATE users SET name=:name, email=:email, phone=:phone, avatar_url=:avatar, remote_id=:remote_id, provider=:provider, login_type=:login_type, is_verified=1, mpin_hash=:mpin_hash, updated_at=NOW() WHERE id=:id';
                            $params = [
                                ':name' => $prov_user['name'],
                                ':email' => $prov_user['email'],
                                ':phone' => $phone,
                                ':avatar' => $prov_user['avatar_url'],
                                ':remote_id' => $prov_user['remote_id'],
                                ':provider' => 'apnapayment',
                                ':login_type' => 'apnapayment',
                                ':mpin_hash' => password_hash($mpin, PASSWORD_DEFAULT),
                                ':id' => $local_id
                            ];
                        } else {
                            $sql = 'UPDATE users SET name=:name, email=:email, phone=:phone, avatar_url=:avatar, remote_id=:remote_id, provider=:provider, login_type=:login_type, is_verified=1, updated_at=NOW() WHERE id=:id';
                            $params = [
                                ':name' => $prov_user['name'],
                                ':email' => $prov_user['email'],
                                ':phone' => $phone,
                                ':avatar' => $prov_user['avatar_url'],
                                ':remote_id' => $prov_user['remote_id'],
                                ':provider' => 'apnapayment',
                                ':login_type' => 'apnapayment',
                                ':id' => $local_id
                            ];
                        }
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $userId = $local_id;
                    } else {
                        if (SAVE_MPIN_HASH) {
                            $stmt = $pdo->prepare('INSERT INTO users (name,email,phone,mpin_hash,avatar_url,remote_id,provider,login_type,is_verified,created_at,updated_at) VALUES (:name,:email,:phone,:mpin_hash,:avatar,:remote_id,:provider,:login_type,1,NOW(),NOW())');
                            $stmt->execute([
                                ':name' => $prov_user['name'],
                                ':email' => $prov_user['email'],
                                ':phone' => $phone,
                                ':mpin_hash' => password_hash($mpin, PASSWORD_DEFAULT),
                                ':avatar' => $prov_user['avatar_url'],
                                ':remote_id' => $prov_user['remote_id'],
                                ':provider' => 'apnapayment',
                                ':login_type' => 'apnapayment'
                            ]);
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO users (name,email,phone,avatar_url,remote_id,provider,login_type,is_verified,created_at,updated_at) VALUES (:name,:email,:phone,:avatar,:remote_id,:provider,:login_type,1,NOW(),NOW())');
                            $stmt->execute([
                                ':name' => $prov_user['name'],
                                ':email' => $prov_user['email'],
                                ':phone' => $phone,
                                ':avatar' => $prov_user['avatar_url'],
                                ':remote_id' => $prov_user['remote_id'],
                                ':provider' => 'apnapayment',
                                ':login_type' => 'apnapayment'
                            ]);
                        }
                        $userId = $pdo->lastInsertId();
                    }

                    $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash, avatar_url, remote_id FROM users WHERE id = ? LIMIT 1');
                    $stmt->execute([$userId]);
                    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                } catch (Throwable $ex) {
                    error_log('/login.php: provider upsert failed: '.$ex->getMessage());
                    $u = [
                        'id' => null,
                        'name' => $prov_user['name'],
                        'email' => $prov_user['email'],
                        'phone' => $phone,
                        'login_type' => 'apnapayment',
                        'avatar_url' => $prov_user['avatar_url'],
                        'mpin_hash' => null,
                        'remote_id' => $prov_user['remote_id']
                    ];
                }
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => isset($u['id']) && $u['id'] !== null ? (int)$u['id'] : null,
                    'name' => $u['name'] ?? null,
                    'email' => $u['email'] ?? null,
                    'phone' => $u['phone'] ?? null,
                    'login_type' => $u['login_type'] ?? 'apnapayment',
                    'avatar_url' => $u['avatar_url'] ?? null,
                    'remote_id' => $u['remote_id'] ?? ($prov_user['remote_id'] ?? null)
                ];
                $_SESSION['user_id'] = isset($u['id']) && $u['id'] !== null ? (int)$u['id'] : null;
                $_SESSION['last_activity'] = time();

                try {
                    $cookie_user_id = isset($u['id']) && $u['id'] !== null ? (int)$u['id'] : null;
                    $rawTokenForClient = createAuthToken($pdo, $cookie_user_id, AUTH_COOKIE_TTL);
                    setAuthCookie($rawTokenForClient, AUTH_COOKIE_TTL);
                } catch (Throwable $ex) {
                    error_log('/login.php: setAuthCookie failed: '.$ex->getMessage());
                }

                $partner_required = false;
                try {
                    if (!empty($u['id'])) {
                        $stmt1 = $pdo->prepare('SELECT COUNT(*) FROM gv_partners WHERE user_id = ?');
                        $stmt1->execute([(int)$u['id']]);
                        $gv_count = (int)$stmt1->fetchColumn();

                        $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM partners WHERE user_id = ?');
                        $stmt2->execute([(int)$u['id']]);
                        $partner_count = (int)$stmt2->fetchColumn();

                        $partner_required = ($gv_count === 0 && $partner_count === 0);
                    } else {
                        $partner_required = false;
                    }
                } catch (Throwable $ex) {
                    error_log('/login.php: partner check failed after provider login: '.$ex->getMessage());
                    $partner_required = false;
                }

                echo json_encode(['success' => true, 'partner_required' => $partner_required, 'user' => [
                    'id' => $prov_user['remote_id'] ?? null,
                    'name' => $prov_user['name'],
                    'email' => $prov_user['email'],
                    'image' => $prov_user['avatar_url'],
                    'phone' => $phone
                ]]);
                exit;
            }

            if ($prov_not_found) {
                $provider_user_not_found = true;
            }

            if ($prov_invalid_mpin) {
                http_response_code(401);
                echo json_encode(['success' => false, 'status' => 'invalid_credentials', 'message' => 'Invalid mPIN (provider). Use Forgot mPIN to reset.']);
                exit;
            }
        }
    }

    if (!$u) {
        if ($phone === '' && $email === '') {
            throw new Exception('Please enter your phone or email to log in on this device.');
        }

        if ($phone !== '') {
            $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash, avatar_url, remote_id FROM users WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email, phone, login_type, mpin_hash, avatar_url, remote_id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
        }

        $u = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (isset($provider_user_not_found) && $provider_user_not_found && !$u) {
        http_response_code(404);
        echo json_encode(['status' => 'not_registered', 'success' => false, 'message' => 'User not registered with provider; please create an account.']);
        exit;
    }

    if (!$u) {
        throw new Exception('Account not found. Check your details.');
    }

    if (empty($u['mpin_hash']) || !password_verify($mpin, $u['mpin_hash'])) {
        throw new Exception('Incorrect mPIN');
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'phone' => $u['phone'],
        'login_type' => $u['login_type'],
        'avatar_url' => $u['avatar_url'] ?? null,
        'remote_id' => $u['remote_id'] ?? null
    ];
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['last_activity'] = time();

    try {
        $rawTokenForClient = createAuthToken($pdo, (int)$u['id'], AUTH_COOKIE_TTL);
        setAuthCookie($rawTokenForClient, AUTH_COOKIE_TTL);
    } catch (Throwable $ex) {
        error_log('/login.php: setAuthCookie failed for local login: '.$ex->getMessage());
    }

    try {
        $pdo->prepare('UPDATE users SET updated_at = NOW() WHERE id = ?')->execute([$u['id']]);
    } catch (Throwable $ex) {
    }

    $partner_required = false;
    try {
        $stmt1 = $pdo->prepare('SELECT COUNT(*) FROM gv_partners WHERE user_id = ?');
        $stmt1->execute([(int)$u['id']]);
        $gv_count = (int)$stmt1->fetchColumn();

        $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM partners WHERE user_id = ?');
        $stmt2->execute([(int)$u['id']]);
        $partner_count = (int)$stmt2->fetchColumn();

        $partner_required = ($gv_count === 0 && $partner_count === 0);
    } catch (Throwable $ex) {
        error_log('/login.php: partner check failed for local login: '.$ex->getMessage());
        $partner_required = false;
    }
    echo json_encode(['success' => true, 'partner_required' => $partner_required, 'user' => [
        'id' => $u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'image' => $u['avatar_url'] ?? null,
        'phone' => $u['phone']
    ]]);
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}