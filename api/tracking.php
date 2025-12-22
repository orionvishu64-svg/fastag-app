<?php
// api/tracking.php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../logs';
@mkdir($logDir, 0750, true);
$logFile = $logDir . '/tracking.log';

function log_msg(string $msg): void {
    global $logFile;
    @file_put_contents(
        $logFile,
        date('c') . ' ' . $msg . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

$awb = trim($_GET['awb'] ?? $_GET['waybill'] ?? '');
if ($awb === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'missing_awb',
        'message' => 'awb / waybill is required'
    ]);
    exit;
}

$secrets = '/opt/bitnami/fastag_secrets/secrets.php';
if (file_exists($secrets)) {
    require_once $secrets;
}

$baseUrl = defined('DELHIVERY_BASE_URL')
    ? rtrim(DELHIVERY_BASE_URL, '/')
    : 'https://track.delhivery.com';

$url = $baseUrl . '/api/v1/packages/json/?waybill=' . urlencode($awb);

$headers = ['Accept: application/json'];
if (defined('DELHIVERY_API_KEY') && DELHIVERY_API_KEY) {
    if (defined('DELHIVERY_TOKEN_IN_QUERY') && DELHIVERY_TOKEN_IN_QUERY === true) {
        $url .= '&token=' . urlencode(DELHIVERY_API_KEY);
    } else {
        $headers[] = 'Authorization: Token ' . DELHIVERY_API_KEY;
    }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_FAILONERROR    => false
]);

$response  = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError) {
    log_msg("CURL error for {$awb}: {$curlError}");
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => 'delhivery_unreachable'
    ]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_msg("JSON parse error for {$awb}: " . json_last_error_msg());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => 'invalid_delhivery_response'
    ]);
    exit;
}

$events = [];
$currentStatus = null;

if (!empty($data['ShipmentData'][0]['Shipment'])) {
    $shipment = $data['ShipmentData'][0]['Shipment'];

    $currentStatus = $shipment['Status']['Status'] ?? null;

    if (!empty($shipment['Scans']) && is_array($shipment['Scans'])) {
        foreach ($shipment['Scans'] as $scan) {
            $d = $scan['ScanDetail'] ?? [];
            $events[] = [
                'status'   => $d['Scan'] ?? '',
                'location' => $d['ScannedLocation'] ?? '',
                'time'     => $d['ScanDateTime'] ?? '',
                'remarks'  => $d['Instructions'] ?? ''
            ];
        }
    }
}
usort($events, function ($a, $b) {
    return strtotime($b['time'] ?? '') <=> strtotime($a['time'] ?? '');
});

echo json_encode([
    'success'        => true,
    'awb'            => $awb,
    'http_code'      => $httpCode,
    'current_status' => $currentStatus,
    'events'         => $events,
    'source'         => 'delhivery'
]);
exit;