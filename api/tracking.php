<?php
// api/tracking.php
// GET ?waybill=xxxx  OR ?awb=xxxx
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../logs';
@mkdir($logDir, 0750, true);
$logFile = $logDir . '/tracking.log';
function log_msg($s){ global $logFile; @file_put_contents($logFile, date('c') . ' ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX); }

$waybill = trim($_GET['waybill'] ?? $_GET['awb'] ?? '');
if ($waybill === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_waybill', 'message' => 'Provide waybill or awb parameter.']);
    exit;
}

// include secrets
$secrets = '/opt/bitnami/fastag_secrets/secrets.php';
if (file_exists($secrets)) require_once $secrets;

// prepare delhivery endpoint
$base = defined('DELHIVERY_BASE_URL') ? rtrim(DELHIVERY_BASE_URL, '/') : 'https://track.delhivery.com';
$endpoint = $base . '/api/v1/packages/json';
$params = ['waybill' => $waybill];

$api_key = defined('DELHIVERY_API_KEY') ? DELHIVERY_API_KEY : '';
if (defined('DELHIVERY_TOKEN_IN_QUERY') && DELHIVERY_TOKEN_IN_QUERY && $api_key) {
    $params['token'] = $api_key;
}
$url = $endpoint . '?' . http_build_query($params);

$headers = ['Accept: application/json'];
if (!defined('DELHIVERY_TOKEN_IN_QUERY') || !DELHIVERY_TOKEN_IN_QUERY) {
    if ($api_key && defined('DELHIVERY_API_KEY_HEADER')) {
        $headers[] = trim(DELHIVERY_API_KEY_HEADER) . ' ' . $api_key;
    }
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, defined('DELHIVERY_API_TIMEOUT') ? DELHIVERY_API_TIMEOUT : 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$resp = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $curlErr) {
    log_msg("tracking curl error: {$curlErr}");
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'remote_error', 'message' => 'Failed to fetch tracking data.']);
    exit;
}

$json = json_decode($resp, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_msg("tracking parse error: " . json_last_error_msg() . " raw: " . substr($resp,0,400));
    echo json_encode(['success' => false, 'error' => 'invalid_remote_response', 'raw' => $resp]);
    exit;
}

// Normalize some useful fields if present
$result = [
    'success' => true,
    'http_code' => $httpCode,
    'raw' => $json
];

// Try to extract events if Delhivery returns them under data->packages or similar
if (is_array($json)) {
    // many delhivery responses return an object with "packages" or array of package details
    if (isset($json['packages'])) $result['packages'] = $json['packages'];
    else $result['packages'] = $json;
}

echo json_encode($result);
exit;
