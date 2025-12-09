<?php
// lib/admin_ship_api.php
declare(strict_types=1);

if (!defined('ADMIN_API_DEFAULT_TIMEOUT')) define('ADMIN_API_DEFAULT_TIMEOUT', 20);

define('ADMIN_API_HOST', 'http://127.0.0.1/admin');

function admin_base_host(): string {
    return rtrim(ADMIN_API_HOST, '/');
}

function admin_api_post(string $path, $data = [], int $timeout = ADMIN_API_DEFAULT_TIMEOUT): array {
    $base = admin_base_host();
    $path = '/' . ltrim($path, '/');
    $url = $base . $path;
    $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($payload)
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res !== false && $res !== null && $res !== '') {
        $decoded = json_decode($res, true);
    }

    return [
        'success' => ($err === '' || $err === null) && $http >= 200 && $http < 300,
        'http'    => $http,
        'raw'     => $res,
        'json'    => $decoded,
        'error'   => $err ?: null,
    ];
}

function admin_api_get(string $path, array $query = [], int $timeout = ADMIN_API_DEFAULT_TIMEOUT): array {
    $base = admin_base_host();
    $path = '/' . ltrim($path, '/');
    $url = $base . $path;
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $headers = ['Accept: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if ($res !== false && $res !== null && $res !== '') {
        $decoded = json_decode($res, true);
    }

    return [
        'success' => ($err === '' || $err === null) && $http >= 200 && $http < 300,
        'http'    => $http,
        'raw'     => $res,
        'json'    => $decoded,
        'error'   => $err ?: null,
    ];
}