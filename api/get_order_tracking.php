<?php
// /var/www/html/api/get_order_tracking.php
// Returns JSON:
// {
//   success: true,
//   order: { id, awb, label_url, latest_status, expected_delivery_date, created_at },
//   timeline: [ { id, event, event_status, status, note, location, event_source, awb, courier_name, payload, latitude, longitude, occurred_at }, ... ]
// }

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// include project bootstrap and DB config (relative to api/)
$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (file_exists($dbPath))     require_once $dbPath;

// fallback JSON body helper if common_start does not provide it
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

// Ensure PDO is available (db.php usually sets $pdo). Try safe_pdo() fallback.
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

// Ensure session
if (session_status() === PHP_SESSION_NONE) session_start();

// Determine current user id (respect common_start helpers and request-fallback policy)
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

// Accept order_id via GET, POST or JSON
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
    // Fetch order and ensure ownership
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

    // Fetch tracking rows, include event_source, awb, courier_name, payload, lat/lon if present
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

// Build timeline: start with an order-level entry
$timeline = [];

// order-level entry (Order created / placed)
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

// append tracking rows
foreach ($tracks as $t) {
    $ts = $t['occurred_at'] ?? $t['updated_at'] ?? null;
    // try to decode payload if present
    $payload = null;
    if (!empty($t['payload'])) {
        // payload may be JSON or string; attempt decode safely
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

// Build output order object
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
