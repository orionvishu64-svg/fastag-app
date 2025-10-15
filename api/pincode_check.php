<?php
// api/pincode_check.php
// Returns JSON:
// { success: true, serviceable: bool, shipping_cost: number|null, min_tat_days: int|null, max_tat_days: int|null, message: string }

header('Content-Type: application/json; charset=utf-8');

// include common_start if present
$common = __DIR__ . '/config/common_start.php';
if (file_exists($common)) {
    require_once $common;
}

// require DB (tries two common locations)
$dbfile = __DIR__ . '/../config/db.php';
if (!file_exists($dbfile)) {
    $dbfile = __DIR__ . '/../../config/db.php';
}
if (!file_exists($dbfile)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'db_missing']);
    exit;
}
require_once $dbfile;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'pdo_not_initialized']);
    exit;
}

// pincode param: accept 'pincode' or 'pin'
$pincode = trim($_GET['pincode'] ?? ($_GET['pin'] ?? ''));

if ($pincode === '') {
    echo json_encode(['success'=>false,'error'=>'missing_pincode']);
    exit;
}

// Strict 6-digit validation (India)
if (!preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(['success'=>false,'error'=>'invalid_pincode']);
    exit;
}

// Normalize response function
function out_resp($arr) {
    echo json_encode($arr);
    exit;
}

// 1) Try local table pincode_serviceability (preferred)
try {
    // Try common table names in case your schema uses a different name
    $candidates = ['pincode_serviceability', 'pincodes', 'pincodes_service'];
    $found = false;
    foreach ($candidates as $tbl) {
        $q = "SELECT * FROM `$tbl` WHERE pincode = :pin LIMIT 1";
        try {
            $stmt = $pdo->prepare($q);
            $stmt->execute([':pin' => $pincode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Map known column names to normalized fields
                $serviceable = isset($row['is_serviceable']) ? (bool)$row['is_serviceable'] : (isset($row['serviceable']) ? (bool)$row['serviceable'] : true);
                $shipping_cost = isset($row['shipping_cost']) ? (float)$row['shipping_cost'] : (isset($row['cost']) ? (float)$row['cost'] : null);
                $min_tat = isset($row['min_tat_days']) ? (int)$row['min_tat_days'] : (isset($row['min_tat']) ? (int)$row['min_tat'] : null);
                $max_tat = isset($row['max_tat_days']) ? (int)$row['max_tat_days'] : (isset($row['max_tat']) ? (int)$row['max_tat'] : null);

                out_resp([
                    'success' => true,
                    'serviceable' => $serviceable,
                    'shipping_cost' => $shipping_cost,
                    'min_tat_days' => $min_tat,
                    'max_tat_days' => $max_tat,
                    'message' => $serviceable ? 'serviceable' : 'not_serviceable',
                    'source' => 'local'
                ]);
            }
        } catch (PDOException $e) {
            // ignore and try next candidate table
            continue;
        }
    }
} catch (Exception $e) {
    error_log('pincode_check local lookup error: ' . $e->getMessage());
    // don't exit — try admin API below
}

// 2) Fallback: try admin API if available (lib/admin_ship_api.php)
$adminApiFile = __DIR__ . '/../lib/admin_ship_api.php';
if (file_exists($adminApiFile)) {
    require_once $adminApiFile;
    if (function_exists('admin_api_get')) {
        try {
            $resp = admin_api_get('/api/pincode_serviceability.php', ['pin' => $pincode]);
            if (!empty($resp['json']) && is_array($resp['json'])) {
                // Map admin response to normalized shape if possible
                $j = $resp['json'];
                // admin may return different keys; try to map common ones
                $serviceable = $j['serviceable'] ?? $j['is_serviceable'] ?? ($j['available'] ?? null);
                $shipping_cost = $j['shipping_cost'] ?? $j['shipping'] ?? null;
                $min_tat = $j['min_tat_days'] ?? $j['min_days'] ?? $j['min_tat'] ?? null;
                $max_tat = $j['max_tat_days'] ?? $j['max_days'] ?? $j['max_tat'] ?? null;

                // Coerce types
                if ($serviceable !== null) $serviceable = (bool)$serviceable;
                if ($shipping_cost !== null) $shipping_cost = (float)$shipping_cost;
                if ($min_tat !== null) $min_tat = (int)$min_tat;
                if ($max_tat !== null) $max_tat = (int)$max_tat;

                out_resp([
                    'success' => true,
                    'serviceable' => $serviceable ?? false,
                    'shipping_cost' => $shipping_cost,
                    'min_tat_days' => $min_tat,
                    'max_tat_days' => $max_tat,
                    'message' => $j['message'] ?? ($serviceable ? 'serviceable' : 'not_serviceable'),
                    'source' => 'admin'
                ]);
            } else {
                // Admin API returned no usable JSON
                error_log('pincode_check: admin api returned no json or empty for pin ' . $pincode . ' raw:' . ($resp['raw'] ?? ''));
            }
        } catch (Exception $e) {
            error_log('pincode_check: admin_api_get exception: ' . $e->getMessage());
        }
    } else {
        error_log('pincode_check: admin_api_get function not available in admin_ship_api.php');
    }
}

// 3) Not serviceable/found
out_resp([
    'success' => true,
    'serviceable' => false,
    'shipping_cost' => null,
    'min_tat_days' => null,
    'max_tat_days' => null,
    'message' => 'not_serviceable',
    'source' => 'none'
]);
