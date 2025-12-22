<?php
// api/tracking.php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../logs';
@mkdir($logDir, 0750, true);
$logFile = $logDir . '/tracking.log';
function log_msg($s){
    global $logFile;
    @file_put_contents($logFile, date('c').' '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

$waybill = trim($_GET['waybill'] ?? $_GET['awb'] ?? '');
if ($waybill === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'missing_waybill']);
    exit;
}

$secrets = '/opt/bitnami/fastag_secrets/secrets.php';
if (file_exists($secrets)) require_once $secrets;

$base = defined('DELHIVERY_BASE_URL')
    ? rtrim(DELHIVERY_BASE_URL,'/')
    : 'https://track.delhivery.com';

$url = $base.'/api/v1/packages/json/?waybill='.urlencode($waybill);

$headers = ['Accept: application/json'];
if (defined('DELHIVERY_API_KEY') && DELHIVERY_API_KEY) {
    if (defined('DELHIVERY_TOKEN_IN_QUERY') && DELHIVERY_TOKEN_IN_QUERY) {
        $url .= '&token='.urlencode(DELHIVERY_API_KEY);
    } else {
        $headers[] = 'Authorization: Token '.DELHIVERY_API_KEY;
    }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => $headers
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $err) {
    log_msg("curl error $waybill: $err");
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'remote_error']);
    exit;
}

$json = json_decode($resp, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_msg("json error $waybill: ".json_last_error_msg());
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'invalid_response']);
    exit;
}

$events = [];
$status = null;

if (!empty($json['ShipmentData'][0]['Shipment']['Scans'])) {
    foreach ($json['ShipmentData'][0]['Shipment']['Scans'] as $scan) {
        $d = $scan['ScanDetail'] ?? [];
        $events[] = [
            'status'   => $d['Scan'] ?? '',
            'location' => $d['ScannedLocation'] ?? '',
            'time'     => $d['ScanDateTime'] ?? '',
            'remarks'  => $d['Instructions'] ?? ''
        ];
    }
    $status = $json['ShipmentData'][0]['Shipment']['Status']['Status'] ?? null;
}

echo json_encode([
    'success' => true,
    'awb' => $waybill,
    'http_code' => $code,
    'current_status' => $status,
    'events' => $events,
    'raw' => $json
]);
exit;