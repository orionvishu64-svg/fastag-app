<?php
// /api/get_order_tracking.php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (file_exists($dbPath))     require_once $dbPath;

if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) { error_log('get_order_tracking: safe_pdo failed: '.$e->getMessage()); }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = null;
if (function_exists('get_current_user_id')) {
    try { $currentUserId = get_current_user_id(); } catch (Throwable $e) { $currentUserId = null; }
}
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

$allow_request_fallback = defined('ALLOW_REQUEST_USER_FALLBACK') ? (bool) ALLOW_REQUEST_USER_FALLBACK : true;
if ($currentUserId === null && $allow_request_fallback) {
    if (!empty($_REQUEST['user_id'])) $currentUserId = (int)$_REQUEST['user_id'];
    if ($currentUserId === null) {
        $probe = get_json_input();
        if (!empty($probe['user_id'])) $currentUserId = (int)$probe['user_id'];
    }
}

if (empty($currentUserId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$order_id = 0;
if (!empty($_GET['order_id'])) $order_id = (int)$_GET['order_id'];
if (!$order_id && !empty($_POST['order_id'])) $order_id = (int)$_POST['order_id'];
if (!$order_id) {
    $json = get_json_input();
    if (!empty($json['order_id'])) $order_id = (int)$json['order_id'];
}

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_order']);
    exit;
}

try {
    $orderStmt = $pdo->prepare("
        SELECT id, user_id, awb, label_url,
               COALESCE(delhivery_status, status) AS latest_status,
               expected_delivery_date, created_at
        FROM orders
        WHERE id = :id
        LIMIT 1
    ");
    $orderStmt->execute([':id' => $order_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'order_not_found']);
        exit;
    }
    if ((int)$order['user_id'] !== (int)$currentUserId) {
        echo json_encode(['success' => false, 'error' => 'not_owner']);
        exit;
    }

    $trackStmt = $pdo->prepare("
        SELECT id,
               location,
               event_status,
               event,
               note,
               event_source,
               awb,
               courier_name,
               payload,
               latitude,
               longitude,
               occurred_at,
               updated_at
        FROM order_tracking
        WHERE order_id = :id
        ORDER BY COALESCE(occurred_at, updated_at, '1970-01-01') ASC
    ");
    $trackStmt->execute([':id' => $order_id]);
    $tracks = $trackStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('get_order_tracking db error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

$timeline = [];

$timeline[] = [
    'id' => null,
    'event' => 'ORDER_CREATED',
    'status' => $order['latest_status'] ?? null,
    'event_status' => $order['latest_status'] ?? null,
    'note' => null,
    'location' => null,
    'event_source' => 'system',
    'awb' => $order['awb'] ?? null,
    'courier_name' => null,
    'payload' => null,
    'latitude' => null,
    'longitude' => null,
    'occurred_at' => $order['created_at'] ?? null
];

foreach ($tracks as $t) {
    $ts = $t['occurred_at'] ?? $t['updated_at'] ?? null;
    $payload = null;
    if (!empty($t['payload'])) {
        if (is_string($t['payload'])) {
            $dec = json_decode($t['payload'], true);
            $payload = (json_last_error() === JSON_ERROR_NONE) ? $dec : $t['payload'];
        } else {
            $payload = $t['payload'];
        }
    }

    $timeline[] = [
        'id' => isset($t['id']) ? (int)$t['id'] : null,
        'event' => $t['event'] ?? null,
        'status' => $t['event_status'] ?? null,
        'event_status' => $t['event_status'] ?? null,
        'note' => $t['note'] ?? null,
        'location' => $t['location'] ?? null,
        'event_source' => $t['event_source'] ?? null,
        'awb' => $t['awb'] ?? null,
        'courier_name' => $t['courier_name'] ?? null,
        'payload' => $payload,
        'latitude' => $t['latitude'] ?? null,
        'longitude' => $t['longitude'] ?? null,
        'occurred_at' => $ts
    ];
}

$need_live = false;
if (empty($tracks)) $need_live = true;
$reqParams = get_json_input();
if (!empty($_GET['refresh']) || !empty($_REQUEST['refresh']) || !empty($reqParams['refresh'])) $need_live = true;

$awb = $order['awb'] ?? null;
if ($need_live && $awb) {
    $adminApiFile = __DIR__ . '/../lib/admin_ship_api.php';
    if (file_exists($adminApiFile)) {
        try {
            require_once $adminApiFile;
            if (function_exists('admin_api_post')) {
                $payload = ['action'=>'track','awb'=>$awb];
                $res = admin_api_post('admin_api_proxy.php', $payload, 8);
                if (!empty($res['json']) && is_array($res['json'])) {
                    $j = $res['json'];
                    $tracks_from_admin = null;
                    if (!empty($j['success']) && !empty($j['data'])) {
                        $tracks_from_admin = $j['data'];
                    } elseif (!empty($j['raw'])) {
                        $tracks_from_admin = $j['raw'];
                    } elseif (!empty($j['result'])) {
                        $tracks_from_admin = $j['result'];
                    } else {
                        $tracks_from_admin = $j;
                    }

                    $scans = [];
                    if (is_array($tracks_from_admin)) {
                        if (!empty($tracks_from_admin['ShipmentData']) && is_array($tracks_from_admin['ShipmentData'])) {
                            foreach ($tracks_from_admin['ShipmentData'] as $sd) {
                                if (!empty($sd['scans']) && is_array($sd['scans'])) {
                                    foreach ($sd['scans'] as $s) $scans[] = $s;
                                }
                            }
                        }
                        if (empty($scans) && !empty($tracks_from_admin['scans']) && is_array($tracks_from_admin['scans'])) {
                            $scans = $tracks_from_admin['scans'];
                        }
                        if (empty($scans)) {
                            $plain = array_values($tracks_from_admin);
                            if ($plain && is_array($plain[0]) && array_key_exists('scan_date', $plain[0])) {
                                $scans = $plain;
                            }
                        }
                    }

                    foreach ($scans as $s) {
                        $when = $s['scan_date'] ?? $s['time'] ?? $s['datetime'] ?? $s['date'] ?? $s['occurred_at'] ?? null;
                        $desc = $s['status'] ?? $s['desc'] ?? $s['action'] ?? ($s['remark'] ?? null);
                        $location = $s['location'] ?? ($s['scan_location'] ?? null);
                        $timeline[] = [
                            'id' => null,
                            'event' => $desc,
                            'status' => $desc,
                            'event_status' => $desc,
                            'note' => $s,
                            'location' => $location,
                            'event_source' => 'delhivery',
                            'awb' => $awb,
                            'courier_name' => null,
                            'payload' => $s,
                            'latitude' => $s['lat'] ?? null,
                            'longitude' => $s['lon'] ?? $s['lng'] ?? null,
                            'occurred_at' => $when
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('get_order_tracking admin_api_post exception: '.$e->getMessage());
        }
    }
}

usort($timeline, function($a,$b){
    $ta = $a['occurred_at'] ? strtotime($a['occurred_at']) : PHP_INT_MAX;
    $tb = $b['occurred_at'] ? strtotime($b['occurred_at']) : PHP_INT_MAX;
    return $ta <=> $tb;
});

$outOrder = [
    'id' => (int)$order['id'],
    'awb' => $order['awb'] ?? null,
    'label_url' => $order['label_url'] ?? null,
    'latest_status' => $order['latest_status'] ?? null,
    'expected_delivery_date' => $order['expected_delivery_date'] ?? null,
    'created_at' => $order['created_at'] ?? null
];
echo json_encode([
    'success' => true,
    'order' => $outOrder,
    'timeline' => $timeline
], JSON_UNESCAPED_SLASHES);
exit;