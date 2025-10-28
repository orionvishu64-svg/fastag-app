<?php
// /config/google_login.php
// Robust Google sign-in verification with fallback and logging.

session_start();
header('Content-Type: application/json');

// load local DB connection (db.php should be in same folder)
require_once __DIR__ . '/db.php';

// try to load composer autoloader (non-fatal — fallback exists)
$autoloadA = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadA)) {
    require_once $autoloadA;
}

use Google\Client as GoogleClient;

function log_error_line($msg) {
    error_log('[google_login] ' . $msg);
}

try {
    $in = json_decode(file_get_contents('php://input'), true);
    if (empty($in['id_token'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Missing id_token']);
        exit;
    }

    $id_token = $in['id_token'];

    // Client ID: prefer environment variable, fall back to provided client id
    $CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: '305867100147-ifebl6o2q5kqqrcauc6vv9t5n92h6bvf.apps.googleusercontent.com';

    $payload = null;

    // Try using Google PHP client if available
    if (class_exists('Google\\Client')) {
        try {
            $client = new GoogleClient();
            $client->setClientId($CLIENT_ID);
            $payload = $client->verifyIdToken($id_token);
        } catch (Throwable $e) {
            log_error_line("Google client verification threw: " . $e->getMessage());
            $payload = null; // fall back to tokeninfo
        }
    } else {
        log_error_line("Google\\Client not available; falling back to tokeninfo endpoint.");
    }

    // Fallback: verify via Google's tokeninfo endpoint (no php client required)
    if (!$payload) {
        $tokeninfo_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

        $resp = false;
        if (function_exists('curl_version')) {
            $ch = curl_init($tokeninfo_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200) {
                log_error_line("tokeninfo http response code: " . $code . " body: " . substr((string)$resp,0,400));
                $resp = false;
            }
        } else {
            $resp = @file_get_contents($tokeninfo_url);
        }

        if ($resp !== false) {
            $payload = json_decode($resp, true);
            if (!is_array($payload)) {
                log_error_line("tokeninfo returned non-json or malformed response");
                $payload = null;
            }
        } else {
            log_error_line("tokeninfo request failed or returned non-200");
            $payload = null;
        }
    }

    // basic payload checks
    if (empty($payload) || !is_array($payload)) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid Google token']);
        exit;
    }

    // Validate audience (client id)
    if (!isset($payload['aud']) || $payload['aud'] !== $CLIENT_ID) {
        log_error_line("token aud mismatch. aud=" . ($payload['aud'] ?? 'NULL') . " expected={$CLIENT_ID}");
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid token audience']);
        exit;
    }

    // Validate issuer
    if (!isset($payload['iss']) || !in_array($payload['iss'], ['accounts.google.com','https://accounts.google.com'])) {
        log_error_line("token issuer invalid: " . ($payload['iss'] ?? 'NULL'));
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Invalid token issuer']);
        exit;
    }

    // Validate expiration if present
    if (isset($payload['exp']) && time() > (int)$payload['exp']) {
        log_error_line("token expired: exp=" . $payload['exp']);
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Google token expired']);
        exit;
    }

    $googleId = $payload['sub'] ?? null;
    $email    = $payload['email'] ?? null;
    $name     = $payload['name'] ?? '';
    $picture  = $payload['picture'] ?? '';
    $email_verified = (bool)($payload['email_verified'] ?? false);

    if (!$googleId || !$email || !$email_verified) {
        log_error_line("token missing required fields: sub={$googleId} email={$email} verified=" . ($email_verified? '1':'0'));
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Google email not verified or missing']);
        exit;
    }

    // Upsert user (adjust to your schema)
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT id FROM users WHERE email=:e OR google_id=:g LIMIT 1");
    $st->execute([':e'=>$email, ':g'=>$googleId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        $uid = $u['id'];
        $pdo->prepare("UPDATE users SET google_id=:g, provider='google', avatar_url=:p WHERE id=:id")
            ->execute([':g'=>$googleId, ':p'=>$picture, ':id'=>$uid]);
    } else {
        $pdo->prepare("INSERT INTO users (name,email,google_id,avatar_url,provider,created_at) VALUES (:n,:e,:g,:p,'google',NOW())")
            ->execute([':n'=>$name, ':e'=>$email, ':g'=>$googleId, ':p'=>$picture]);
        $uid = $pdo->lastInsertId();
    }
    $pdo->commit();

    // set session
    $_SESSION['user_id'] = $uid;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $name;
    $_SESSION['auth_provider'] = 'google';

    echo json_encode([
        'success'=>true,
        'redirect'=>'/partner_form.php',
        'user'=>['id'=>$uid,'name'=>$name,'email'=>$email,'avatar'=>$picture]
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // log detailed exception for debugging (do not expose to user)
    log_error_line("Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    log_error_line("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
    exit;
}
