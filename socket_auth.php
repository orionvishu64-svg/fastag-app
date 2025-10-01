<?php
// socket_auth.php
// Safe: defines helpers and only runs routes when requested directly.
// Place in fastag_website/ alongside common_start.php

require_once __DIR__ . '/common_start.php'; // ensures SESSION and SOCKET_SECRET available

// ---------------- configuration ----------------
if (!defined('SOCKET_TOKEN_TTL')) {
    // seconds: default 5 minutes. Change to smaller/larger as needed.
    define('SOCKET_TOKEN_TTL', 300);
}

// ---------------- helpers ----------------
if (!function_exists('session_user_id')) {
    function session_user_id(): int {
        if (!empty($_SESSION['user_id'])) return (int) $_SESSION['user_id'];
        if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id'])) return (int) $_SESSION['user']['id'];
        if (!empty($_SESSION['user']) && is_object($_SESSION['user']) && !empty($_SESSION['user']->id)) return (int) $_SESSION['user']->id;
        return 0;
    }
}

if (!function_exists('create_socket_token')) {
    function create_socket_token(int $uid, int $ttl = SOCKET_TOKEN_TTL): string {
        $exp = time() + $ttl;
        $payload = json_encode(['uid' => $uid, 'exp' => $exp]);
        $payload_b64 = base64_encode($payload);
        $signature = hash_hmac('sha256', $payload_b64, SOCKET_SECRET);
        return $payload_b64 . '.' . $signature;
    }
}

if (!function_exists('verify_socket_token')) {
    function verify_socket_token(string $token): int {
        if (empty($token) || strpos($token, '.') === false) return 0;
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return 0;
        [$payload_b64, $sig] = $parts;

        $expected = hash_hmac('sha256', $payload_b64, SOCKET_SECRET);
        if (!hash_equals($expected, $sig)) return 0;

        $payload_json = base64_decode($payload_b64);
        if ($payload_json === false) return 0;
        $data = json_decode($payload_json, true);
        if (!is_array($data) || empty($data['uid']) || empty($data['exp'])) return 0;
        if (!ctype_digit((string)$data['uid']) && !is_int($data['uid'])) return 0;
        if (!ctype_digit((string)$data['exp']) && !is_int($data['exp'])) return 0;
        if ($data['exp'] < time()) return 0;

        return (int)$data['uid'];
    }
}

// ---------------- route handler (only when accessed directly) ----------------
// Only run routing when this file is the actual HTTP entry point.
// This avoids side-effects when socket_auth.php is included via require_once.
$shouldHandleRoutes = (php_sapi_name() !== 'cli')
    && (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));

if ($shouldHandleRoutes) {
    header('Content-Type: application/json; charset=utf-8');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    // Issue a token for the current session user
    if ($method === 'GET' && $action === 'issue') {
        $uid = session_user_id();
        if ($uid <= 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        $ttl = SOCKET_TOKEN_TTL;
        $token = create_socket_token($uid, $ttl);
        echo json_encode(['token' => $token, 'expires' => time() + $ttl, 'uid' => $uid]);
        exit;
    }

    // Verify token (debug)
    if ($method === 'POST' && $action === 'verify') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $input['token'] ?? '';
        if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);
        $uid = verify_socket_token($token);
        if ($uid > 0) {
            echo json_encode(['valid' => true, 'uid' => $uid]);
        } else {
            http_response_code(401);
            echo json_encode(['valid' => false]);
        }
        exit;
    }

    // Default
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
