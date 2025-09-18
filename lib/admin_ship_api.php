<?php
// lib/admin_ship_api.php
// Server-side helper to call admin APIs. Keep ADMIN_API_TOKEN on server only.

function get_admin_config() {
    $cfg = __DIR__ . '/../config/admin_token.php';
    if (!file_exists($cfg)) return null;
    require_once $cfg; // defines ADMIN_API_HOST, ADMIN_API_TOKEN
    if (!defined('ADMIN_API_HOST') || !defined('ADMIN_API_TOKEN')) return null;
    return ['host' => rtrim(ADMIN_API_HOST, '/'), 'token' => ADMIN_API_TOKEN];
}

function admin_api_post($path, $data = [], $timeout = 15) {
    $cfg = get_admin_config();
    if (!$cfg) return ['success' => false, 'error' => 'no_admin_config'];

    // Normalize path: ensure it begins with a single slash
    $path = '/' . ltrim($path, '/');

    $url = $cfg['host'] . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['token']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    // optionally: curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res) $decoded = json_decode($res, true);

    return [
        'http' => $http,
        'raw' => $res,
        'json' => $decoded,
        'error' => $err,
        'success' => ($http >= 200 && $http < 300)
    ];
}
