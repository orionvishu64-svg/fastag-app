<?php
// /var/www/html/api/pincode_check.php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'database missing check pdo connections']);
    exit;
}
require_once $dbPath;

if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}
function out_resp(array $payload, int $httpCode = 200): void {
    if (!headers_sent()) http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) {
            error_log('pincode_check: safe_pdo failed: ' . $e->getMessage());
        }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('pincode_check: no valid PDO connection');
    out_resp(['success'=>false,'error'=>'db_unavailable'],500);
}

$inputJson  = get_json_input();
$pincodeRaw = $_GET['pincode'] ?? $_GET['pin'] ?? $_POST['pincode'] ?? $_POST['pin'] ?? $inputJson['pincode'] ?? $inputJson['pin'] ?? null;
$pincode    = is_scalar($pincodeRaw) ? trim((string)$pincodeRaw) : '';

if ($pincode === '') out_resp(['success'=>false,'error'=>'missing_pincode'],400);
if (!preg_match('/^\d{6}$/',$pincode)) out_resp(['success'=>false,'error'=>'invalid_pincode'],400);

$resultShape = [
    'success'       => true,
    'serviceable'   => false,
    'shipping_cost' => null,
    'min_tat_days'  => null,
    'max_tat_days'  => null,
    'message'       => 'not_serviceable',
    'source'        => 'none'
];

try {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $cacheFile = "$cacheDir/pincode_table.cache";

    $candidateTables = ['pincode_serviceability','pincodes','pincodes_service','pin_serviceability','pincode_service'];

    if (file_exists($cacheFile)) {
        $cachedTable = trim(file_get_contents($cacheFile));
        if ($cachedTable) $candidateTables = [$cachedTable];
    }

    foreach ($candidateTables as $tbl) {
        $tblSafe = str_replace('`','', $tbl);
        $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl");
        $existsStmt->execute([':tbl' => $tblSafe]);
        $exists = (int)$existsStmt->fetchColumn() > 0;
        if (!$exists) continue;

        $stmt = $pdo->prepare("SELECT * FROM `$tblSafe` WHERE pincode = :pin LIMIT 1");
        $stmt->execute([':pin'=>$pincode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;

        @file_put_contents($cacheFile, $tblSafe);

        $serviceable = null;
        foreach (['is_serviceable','serviceable','available','is_available'] as $k)
            if (array_key_exists($k,$row)) { $serviceable = (bool)$row[$k]; break; }
        if ($serviceable === null) $serviceable = true;

        $shipping_cost = null;
        foreach (['shipping_cost','cost','shipping','delivery_cost'] as $k)
            if (array_key_exists($k,$row) && is_numeric($row[$k])) { $shipping_cost = (float)$row[$k]; break; }

        $min_tat = null;
        foreach (['min_tat_days','min_tat','min_days'] as $k)
            if (array_key_exists($k,$row) && is_numeric($row[$k])) { $min_tat = (int)$row[$k]; break; }

        $max_tat = null;
        foreach (['max_tat_days','max_tat','max_days'] as $k)
            if (array_key_exists($k,$row) && is_numeric($row[$k])) { $max_tat = (int)$row[$k]; break; }

        $message = $serviceable ? 'serviceable' : 'not_serviceable';
        $http = $serviceable ? 200 : 404;

        out_resp(array_merge($resultShape, [
            'serviceable'   => $serviceable,
            'shipping_cost' => $shipping_cost,
            'min_tat_days'  => $min_tat,
            'max_tat_days'  => $max_tat,
            'message'       => $message,
            'source'        => 'local'
        ]), $http);
    }
} catch (Throwable $e) {
    error_log('pincode_check local lookup error: '.$e->getMessage());
}

$adminApiFile = __DIR__ . '/../lib/admin_ship_api.php';
if (file_exists($adminApiFile)) {
    try {
        require_once $adminApiFile;
        if (function_exists('admin_api_get')) {
            $resp = admin_api_get('/api/pincode_serviceability.php',['pin'=>$pincode]);
            if (!empty($resp['json']) && is_array($resp['json'])) {
                $j = $resp['json'];
                $serviceable = (bool)($j['serviceable'] ?? $j['is_serviceable'] ?? $j['available'] ?? false);
                $shipping_cost = isset($j['shipping_cost']) ? (float)$j['shipping_cost'] : null;
                $min_tat = isset($j['min_tat_days']) ? (int)$j['min_tat_days'] : null;
                $max_tat = isset($j['max_tat_days']) ? (int)$j['max_tat_days'] : null;
                $message = $j['message'] ?? ($serviceable ? 'serviceable' : 'not_serviceable');
                $http = $serviceable ? 200 : 404;

                out_resp([
                    'success'=>true,
                    'serviceable'=>$serviceable,
                    'shipping_cost'=>$shipping_cost,
                    'min_tat_days'=>$min_tat,
                    'max_tat_days'=>$max_tat,
                    'message'=>$message,
                    'source'=>'admin'
                ], $http);
            }
        }
    } catch (Throwable $e) {
        error_log('pincode_check admin_api_get exception: '.$e->getMessage());
    }
}

$delhiveryKey = getenv('DELHIVERY_API_KEY');
if ($delhiveryKey) {
    try {
        $url = "https://track.delhivery.com/c/api/pin-codes/json/?token=82882be3c32322a1fc1b9a65e2b3f0c9552c9a69&filter_codes=$pincode";
        $opts = ['http'=>[
            'method'=>'GET',
            'header'=>"Authorization: Token $delhiveryKey\r\n",
            'timeout'=>4
        ]];
        $r = @file_get_contents($url, false, stream_context_create($opts));
        $j = json_decode($r,true);
        if (!empty($j['delivery_codes'][0]['postal_code']['is_deliverable'])) {
            out_resp([
                'success'=>true,
                'serviceable'=>true,
                'shipping_cost'=>null,
                'min_tat_days'=>null,
                'max_tat_days'=>null,
                'message'=>'serviceable',
                'source'=>'delhivery_live'
            ]);
        }
    } catch (Throwable $e) {
        error_log('pincode_check delhivery_live exception: '.$e->getMessage());
    }
}
out_resp($resultShape,404);