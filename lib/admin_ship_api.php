<?php
// lib/admin_ship_api.php
// Server-side helper to call admin APIs. Keep ADMIN_API_TOKEN on server only.

function get_admin_token() {
    // load token from protected config file (create config/admin_token.php with ADMIN_API_TOKEN)
    $cfg = __DIR__ . '/../config/admin_token.php';
    if (!file_exists($cfg)) return null;
    require $cfg; // should define ADMIN_API_TOKEN
    return defined('ADMIN_API_TOKEN') ? ADMIN_API_TOKEN : null;
}

function admin_api_post($path, $data = [], $timeout = 15) {
    $token = get_admin_token();
    if (!$token) return ['success'=>false,'error'=>'no_admin_token'];

    $url = rtrim('https://ADMIN_HOST', '/') . $path; // replace ADMIN_HOST or set in config
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res) $decoded = json_decode($res, true);
    return ['http'=>$http, 'raw'=>$res, 'json'=>$decoded, 'error'=>$err, 'success'=>($http >= 200 && $http < 300)];
}
