<?php
// /var/www/html/api/pincode_check.php
header('Content-Type: application/json; charset=utf-8');

// include common_start if present in the same folder; otherwise continue
$common = __DIR__ . '/common_start.php';
if (file_exists($common)) {
    require_once $common;
}

// require DB from site root (you told me db.php is at /var/www/html/db.php)
$dbfile = __DIR__ . '/../db.php';
if (!file_exists($dbfile)) {
    // try one level up if layout differs
    $dbfile = __DIR__ . '/../../db.php';
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

// read pincode from query param 'pincode' OR fallback to 'pin'
$pincode = trim($_GET['pincode'] ?? ($_GET['pin'] ?? ''));

if ($pincode === '') {
    echo json_encode(['success'=>false,'error'=>'missing_pincode']);
    exit;
}

// Optionally: basic validation
if (!preg_match('/^\d{5,6}$/', $pincode)) {
    echo json_encode(['success'=>false,'error'=>'invalid_pincode']);
    exit;
}

// Basic example: check serviceability in local DB table 'pincodes' (adjust to your schema)
// If your original code called admin API instead, replace this section with that call.
try {
    $stmt = $pdo->prepare("SELECT serviceable, shipping_cost, min_tat_days, max_tat_days FROM pincodes WHERE pincode = :pin LIMIT 1");
    $stmt->execute([':pin'=>$pincode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode([
            'success'=>true,
            'data' => [
                'serviceable' => (bool)$row['serviceable'],
                'shipping_cost' => (float)$row['shipping_cost'],
                'min_tat_days' => (int)$row['min_tat_days'],
                'max_tat_days' => (int)$row['max_tat_days']
            ]
        ]);
        exit;
    }

    // If not present in local DB, forward to admin API for serviceability
    // admin_api_post is expected in /lib/admin_ship_api.php — check existence if you use it
    $adminApiFile = __DIR__ . '/../lib/admin_ship_api.php';
    if (file_exists($adminApiFile)) {
        require_once $adminApiFile;
        if (function_exists('admin_api_post')) {
            $resp = admin_api_post('/api/pincode_serviceability.php', ['pincode' => $pincode]);
            // admin_api_post returns array with keys 'http','raw','json','error','success'
            if (!empty($resp['json'])) {
                echo json_encode($resp['json']);
                exit;
            }
        }
    }

    // not found -> not serviceable
    echo json_encode(['success'=>false,'error'=>'not_serviceable']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server_error']);
    // log to syslog for debugging (no secrets)
    error_log("pincode_check error: " . $e->getMessage());
    exit;
}