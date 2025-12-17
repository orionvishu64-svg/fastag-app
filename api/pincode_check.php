<?php
// api/pincode_check.php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../logs';
@mkdir($logDir, 0750, true);
$logFile = $logDir . '/pincode_check.log';

function log_msg($s) { global $logFile; @file_put_contents($logFile, date('c') . ' ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX); }

$pincode = trim($_GET['pincode'] ?? '');

if ($pincode === '' || !preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_pincode', 'message' => 'Provide a valid 6-digit pincode (e.g. 110001).']);
    exit;
}

$secrets = '/opt/bitnami/fastag_secrets/secrets.php';
if (file_exists($secrets)) require_once $secrets;
else log_msg("secrets file missing: $secrets");

$dbconf = __DIR__ . '/../config/db.php';
if (file_exists($dbconf)) {
    require_once $dbconf;
} else {
    log_msg("db.php missing at {$dbconf}");
}

$base = defined('DELHIVERY_BASE_URL') ? rtrim(DELHIVERY_BASE_URL, '/') : 'https://track.delhivery.com';
$endpoint = $base . '/c/api/pin-codes/json/';
$query = http_build_query(['filter_codes' => $pincode]);

$use_query_token = defined('DELHIVERY_TOKEN_IN_QUERY') && DELHIVERY_TOKEN_IN_QUERY;
$api_key = defined('DELHIVERY_API_KEY') ? DELHIVERY_API_KEY : '';
$headers = [
    'Accept: application/json',
];

if ($use_query_token && $api_key) {
    $query .= '&' . http_build_query(['token' => $api_key]);
} elseif ($api_key && defined('DELHIVERY_API_KEY_HEADER')) {
    $headers[] = trim(DELHIVERY_API_KEY_HEADER) . ' ' . $api_key;
}

$ch = curl_init($endpoint . '?' . $query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, defined('DELHIVERY_API_TIMEOUT') ? DELHIVERY_API_TIMEOUT : 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FAILONERROR, false);
$resp = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $curlErr) {
    log_msg("delhivery pincode fetch error: {$curlErr}");
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'remote_error', 'message' => 'Unable to check pincode right now.']);
    exit;
}

$json = json_decode($resp, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_msg("delhivery pincode json parse failed: " . json_last_error_msg() . " raw: " . substr($resp,0,400));
    echo json_encode(['success' => false, 'error' => 'invalid_remote_response', 'raw' => $resp]);
    exit;
}

$deliverable = false;
$tat = null;

if (is_array($json)) {
    if (isset($json['status']) && strtolower($json['status']) === 'success' && !empty($json['data'])) {
        $deliverable = true;
        if (!empty($json['data'][0]['tat'])) $tat = $json['data'][0]['tat'];
    } elseif (!empty($json) && array_values($json) !== $json && isset($json['0']) === false) {
        if (!empty($json['records']) || !empty($json['data'])) {
            $arr = $json['records'] ?? $json['data'] ?? [];
            if (!empty($arr)) $deliverable = true;
        } else {
            $deliverable = true;
        }
    } elseif (!empty($json)) {
        $deliverable = count($json) > 0;
    }
}

if (!$deliverable && $httpCode === 200 && !empty($json)) {
    $deliverable = true;
}

echo json_encode([
    'success' => true,
    'deliverable' => (bool)$deliverable,
    'tat' => $tat,
    'http_code' => $httpCode,
    'raw' => $json,
]);
exit;
