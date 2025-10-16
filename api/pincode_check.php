<?php
// /var/www/html/api/pincode_check.php
// Returns JSON:
// { success: true, serviceable: bool, shipping_cost: number|null, min_tat_days: int|null, max_tat_days: int|null, message: string, source: 'local'|'admin'|'none'}

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// include common_start and db config (relative paths)
$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'database missing check pdo connections']);
    exit;
}
require_once $dbPath;

// helper: read JSON body (if common_start already has get_json_input, this will not override)
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

// helper: unified output
function out_resp(array $payload, int $httpCode = 200): void {
    if (!headers_sent()) http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// ensure PDO available (db.php should define $pdo)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // attempt safe_pdo if present
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) { error_log('pincode_check: safe_pdo failed: ' . $e->getMessage()); }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    out_resp(['success' => false, 'error' => 'db_unavailable'], 500);
}

// Accept pincode via GET, POST form or JSON
$inputJson = get_json_input();
$pincodeRaw = null;
if (!empty($_GET['pincode'])) $pincodeRaw = $_GET['pincode'];
elseif (!empty($_GET['pin'])) $pincodeRaw = $_GET['pin'];
elseif (!empty($_POST['pincode'])) $pincodeRaw = $_POST['pincode'];
elseif (!empty($_POST['pin'])) $pincodeRaw = $_POST['pin'];
elseif (!empty($inputJson['pincode'])) $pincodeRaw = $inputJson['pincode'];
elseif (!empty($inputJson['pin'])) $pincodeRaw = $inputJson['pin'];

// sanitize
$pincode = is_scalar($pincodeRaw) ? trim((string)$pincodeRaw) : '';

if ($pincode === '') {
    out_resp(['success' => false, 'error' => 'missing_pincode'], 400);
}

// validate 6-digit Indian pincode
if (!preg_match('/^\d{6}$/', $pincode)) {
    out_resp(['success' => false, 'error' => 'invalid_pincode'], 400);
}

// normalize results shape
$resultShape = [
    'success' => true,
    'serviceable' => false,
    'shipping_cost' => null,
    'min_tat_days' => null,
    'max_tat_days' => null,
    'message' => 'not_serviceable',
    'source' => 'none'
];

// 1) Try local DB lookup (multiple candidate tables & column mapping)
try {
    $candidateTables = ['pincode_serviceability','pincodes','pincodes_service','pin_serviceability','pincode_service'];
    foreach ($candidateTables as $tbl) {
        // check table exists quickly via information_schema to avoid exceptions
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl");
        $checkStmt->execute([':tbl' => $tbl]);
        $exists = (int)$checkStmt->fetchColumn() > 0;
        if (!$exists) continue;

        $q = "SELECT * FROM `$tbl` WHERE pincode = :pin LIMIT 1";
        $stmt = $pdo->prepare($q);
        $stmt->execute([':pin' => $pincode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;

        // Map known column names to normalized fields
        // support a variety of naming conventions
        $serviceable = null;
        foreach (['is_serviceable','serviceable','available','is_available'] as $k) {
            if (array_key_exists($k, $row)) { $serviceable = (bool)$row[$k]; break; }
        }
        if ($serviceable === null) $serviceable = true; // assume serviceable if record present unless explicit flag exists

        $shipping_cost = null;
        foreach (['shipping_cost','cost','shipping','delivery_cost'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') { $shipping_cost = (float)$row[$k]; break; }
        }

        $min_tat = null;
        foreach (['min_tat_days','min_tat','min_days'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') { $min_tat = (int)$row[$k]; break; }
        }

        $max_tat = null;
        foreach (['max_tat_days','max_tat','max_days'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') { $max_tat = (int)$row[$k]; break; }
        }

        $message = $serviceable ? 'serviceable' : 'not_serviceable';

        out_resp(array_merge($resultShape, [
            'serviceable' => $serviceable,
            'shipping_cost' => $shipping_cost,
            'min_tat_days' => $min_tat,
            'max_tat_days' => $max_tat,
            'message' => $message,
            'source' => 'local',
        ]));
    }
} catch (Throwable $e) {
    error_log('pincode_check local lookup error: ' . $e->getMessage());
    // continue to admin API fallback
}

// 2) Try admin API via lib/admin_ship_api.php (if present)
$adminApiFile = __DIR__ . '/../lib/admin_ship_api.php';
if (file_exists($adminApiFile)) {
    try {
        require_once $adminApiFile;
        if (function_exists('admin_api_get')) {
            $resp = admin_api_get('/api/pincode_serviceability.php', ['pin' => $pincode]);
            if (!empty($resp['json']) && is_array($resp['json'])) {
                $j = $resp['json'];
                // map common keys from admin
                $serviceable = $j['serviceable'] ?? $j['is_serviceable'] ?? $j['available'] ?? false;
                $shipping_cost = $j['shipping_cost'] ?? $j['shipping'] ?? $j['cost'] ?? null;
                $min_tat = $j['min_tat_days'] ?? $j['min_days'] ?? $j['min_tat'] ?? null;
                $max_tat = $j['max_tat_days'] ?? $j['max_days'] ?? $j['max_tat'] ?? null;
                $message = $j['message'] ?? ($serviceable ? 'serviceable' : 'not_serviceable');

                // coerce types
                $serviceable = (bool)$serviceable;
                $shipping_cost = $shipping_cost !== null ? (float)$shipping_cost : null;
                $min_tat = $min_tat !== null ? (int)$min_tat : null;
                $max_tat = $max_tat !== null ? (int)$max_tat : null;

                out_resp([
                    'success' => true,
                    'serviceable' => $serviceable,
                    'shipping_cost' => $shipping_cost,
                    'min_tat_days' => $min_tat,
                    'max_tat_days' => $max_tat,
                    'message' => $message,
                    'source' => 'admin'
                ]);
            } else {
                error_log('pincode_check: admin api returned empty or invalid JSON for pin ' . $pincode . ' raw:' . ($resp['raw'] ?? ''));
            }
        } else {
            error_log('pincode_check: admin_api_get not found in lib/admin_ship_api.php');
        }
    } catch (Throwable $e) {
        error_log('pincode_check: admin_api_get exception: ' . $e->getMessage());
    }
}

// 3) Not found/serviceable fallback
out_resp($resultShape);
