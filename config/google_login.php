<?php
// /config/google_login.php
// Google sign-in verification with fallback, unified session handling,
// and redirect to collect_phone if phone is missing.

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

// local DB connection (db.php should provide $pdo)
require_once __DIR__ . '/db.php';

// try composer autoload (non-fatal)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use Google\Client as GoogleClient;

/** Small logger helper */
function glog(string $msg): void {
    error_log('[google_login] ' . $msg);
}

try {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (empty($in['id_token']) || !is_string($in['id_token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing id_token']);
        exit;
    }
    $id_token = trim($in['id_token']);

    // Client ID: prefer environment variable
    $CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: '305867100147-ifebl6o2q5kqqrcauc6vv9t5n92h6bvf.apps.googleusercontent.com';

    $payload = null;

    // Prefer Google PHP client if available
    if (class_exists('Google\\Client')) {
        try {
            $client = new GoogleClient();
            $client->setClientId($CLIENT_ID);
            $payload = $client->verifyIdToken($id_token);
        } catch (Throwable $e) {
            glog('Google client verification error: ' . $e->getMessage());
            $payload = null; // fallback below
        }
    } else {
        glog('Google\\Client not installed; using tokeninfo fallback.');
    }

    // Fallback: tokeninfo endpoint
    if (!$payload) {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
        $resp = false;
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) {
                glog("tokeninfo http code {$httpCode}");
                $resp = false;
            }
        } else {
            $resp = @file_get_contents($url);
        }
        if ($resp !== false) {
            $payload = json_decode($resp, true);
            if (!is_array($payload)) {
                glog('tokeninfo returned non-json');
                $payload = null;
            }
        } else {
            glog('tokeninfo request failed');
            $payload = null;
        }
    }

    // Basic payload checks
    if (empty($payload) || !is_array($payload)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
        exit;
    }

    if (!isset($payload['aud']) || $payload['aud'] !== $CLIENT_ID) {
        glog('token aud mismatch. aud=' . ($payload['aud'] ?? 'NULL') . ' expected=' . $CLIENT_ID);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token audience']);
        exit;
    }

    if (!isset($payload['iss']) || !in_array($payload['iss'], ['accounts.google.com', 'https://accounts.google.com'], true)) {
        glog('invalid token issuer: ' . ($payload['iss'] ?? 'NULL'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token issuer']);
        exit;
    }

    if (isset($payload['exp']) && time() > (int)$payload['exp']) {
        glog('token expired: exp=' . $payload['exp']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Google token expired']);
        exit;
    }

    $googleId = $payload['sub'] ?? null;
    $email    = $payload['email'] ?? null;
    $name     = $payload['name'] ?? '';
    $picture  = $payload['picture'] ?? '';
    $email_verified = !empty($payload['email_verified']);

    if (!$googleId || !$email || !$email_verified) {
        glog("token missing fields sub={$googleId} email={$email} verified=" . ($email_verified ? '1' : '0'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Google email not verified or missing']);
        exit;
    }

    // Upsert user
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e OR google_id = :g LIMIT 1");
    $stmt->execute([':e' => $email, ':g' => $googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $uid = (int)$user['id'];
        $u2 = $pdo->prepare("UPDATE users SET google_id = :g, provider = 'google', avatar_url = :p, updated_at = NOW() WHERE id = :id");
        $u2->execute([':g' => $googleId, ':p' => $picture, ':id' => $uid]);
    } else {
        $ins = $pdo->prepare("INSERT INTO users (name, email, google_id, avatar_url, provider, created_at, updated_at) VALUES (:n,:e,:g,:p,'google',NOW(),NOW())");
        $ins->execute([':n' => $name, ':e' => $email, ':g' => $googleId, ':p' => $picture]);
        $uid = (int)$pdo->lastInsertId();
    }
    $pdo->commit();

    // Fetch user's phone (if any)
    $sphone = $pdo->prepare("SELECT phone FROM users WHERE id = :id LIMIT 1");
    $sphone->execute([':id' => $uid]);
    $row = $sphone->fetch(PDO::FETCH_ASSOC);
    $phone = $row['phone'] ?? null;

    // Unified session structure (matches manual login)
    $_SESSION['user'] = [
        'id'         => $uid,
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'login_type' => 'google'
    ];

    // Legacy aliases for older pages
    $_SESSION['user_id']    = $uid;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_phone'] = $phone;
    $_SESSION['auth_provider'] = 'google';
    $_SESSION['last_activity'] = time();

    // Decide redirect: if phone missing -> collect_phone, else partner_form
    $redirect = $phone ? '/partner_form.php' : '/collect_phone.html';

    echo json_encode([
        'success' => true,
        'redirect' => $redirect,
        'user' => [
            'id' => $uid,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'avatar' => $picture
        ]
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    glog('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    glog('Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
