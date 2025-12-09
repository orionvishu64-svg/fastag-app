<?php
// lib/admin_ship_api.php
// Server-side helper to call admin APIs. Keep ADMIN_API_TOKEN on server only.

define('ADMIN_API_DEFAULT_TIMEOUT', 20);

function get_admin_config() {
    $cfg = __DIR__ . '/../config/admin_token.php';
    if (!file_exists($cfg)) return null;
    include_once $cfg;
    if (!defined('ADMIN_API_HOST') || !defined('ADMIN_API_TOKEN')) return null;
    return ['host' => rtrim(ADMIN_API_HOST, '/'), 'token' => ADMIN_API_TOKEN];
}

function admin_api_post($path, $data = [], $timeout = ADMIN_API_DEFAULT_TIMEOUT) {
    $cfg = get_admin_config();
    if (!$cfg) return ['success' => false, 'error' => 'no_admin_config', 'http' => 0];

    $path = '/' . ltrim($path, '/');
    $url = $cfg['host'] . $path;

    $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-ADMIN-API-TOKEN: ' . $cfg['token'],
        'Content-Length: ' . strlen($payload)
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res) {
        $decoded = json_decode($res, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        }
    }

    return [
        'http' => (int)$http,
        'raw' => $res,
        'json' => $decoded,
        'error' => $err ?: null,
        'success' => ($http >= 200 && $http < 300)
    ];
}

function admin_api_get($path, $query = [], $timeout = ADMIN_API_DEFAULT_TIMEOUT) {
    $cfg = get_admin_config();
    if (!$cfg) return ['success' => false, 'error' => 'no_admin_config', 'http' => 0];

    $path = '/' . ltrim($path, '/');
    $url = $cfg['host'] . $path;
    if (!empty($query)) $url .= '?' . http_build_query($query);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);

    $headers = [
        'Accept: application/json',
        'X-ADMIN-API-TOKEN: ' . $cfg['token']
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res) {
        $decoded = json_decode($res, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        }
    }

    return [
        'http' => (int)$http,
        'raw' => $res,
        'json' => $decoded,
        'error' => $err ?: null,
        'success' => ($http >= 200 && $http < 300)
    ];
}