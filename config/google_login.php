<?php
// config/google_config.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if ($origin && $origin === $serverOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
}

$clientId = getenv('GOOGLE_CLIENT_ID') ?: '';

if (!$clientId) {
    $envFile = __DIR__ . '/.env.php';
    if (is_readable($envFile)) {
        try {
            $cfg = require $envFile;
            if (is_array($cfg) && !empty($cfg['GOOGLE_CLIENT_ID'])) {
                $clientId = (string)$cfg['GOOGLE_CLIENT_ID'];
            }
        } catch (Throwable $e) {
            error_log('[google_config] failed to load .env.php: ' . $e->getMessage());
        }
    }
}

if (!$clientId) {
    $clientId = '214217458731-2fjk2nbmk1m4ifpgdgbpssqiv8f5m38u.apps.googleusercontent.com';
}

echo json_encode(['client_id' => $clientId], JSON_UNESCAPED_SLASHES);
exit;
