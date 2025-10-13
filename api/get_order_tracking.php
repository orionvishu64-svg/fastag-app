<?php
// api/get_order_tracking.php
// Returns JSON:
// {
//   success: true,
//   order: { id, awb, label_url, latest_status, expected_delivery_date, created_at },
//   timeline: [ { id, awb, status, location, created_at }, ... ]
// }

header('Content-Type: application/json; charset=utf-8');

// include common_start (session helpers) if available
$common = __DIR__ . '/../common_start.php';
if (file_exists($common)) require_once $common;

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db.php';

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'missing_order']);
    exit;
}

// Verify user logged-in (same session shape as track_orders.php)
if (empty($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}
$user_id = (int)$_SESSION['user']['id'];

try {
    // Fetch order and ensure ownership
    $stmt = $pdo->prepare("
        SELECT id, user_id, awb, label_url, COALESCE(delhivery_status, status) AS latest_status,
               expected_delivery_date, created_at
        FROM orders
        WHERE id = :id AND user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':id' => $order_id, ':uid' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'not_owner']);
        exit;
    }

    // Fetch order_tracking rows
    $stmt2 = $pdo->prepare("SELECT id, location, updated_at FROM order_tracking WHERE order_id = :id ORDER BY updated_at ASC");
    $stmt2->execute([':id' => $order_id]);
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('get_order_tracking db error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

// Build timeline: start with order-level event then location events
$timeline = [];

// order-level event
$timeline[] = [
    'id' => null,
    'awb' => $order['awb'] ?? null,
    'status' => $order['latest_status'] ?? null,
    'location' => null,
    'created_at' => $order['created_at'] ?? null
];

// append tracking rows
foreach ($rows as $r) {
    $timeline[] = [
        'id' => isset($r['id']) ? (int)$r['id'] : null,
        'awb' => null,
        'status' => null,
        'location' => $r['location'] ?? null,
        'created_at' => $r['updated_at'] ?? null
    ];
}

$out = [
    'success' => true,
    'order' => [
        'id' => (int)$order['id'],
        'awb' => $order['awb'] ?? null,
        'label_url' => $order['label_url'] ?? null,
        'latest_status' => $order['latest_status'] ?? null,
        'expected_delivery_date' => $order['expected_delivery_date'] ?? null,
        'created_at' => $order['created_at'] ?? null
    ],
    'timeline' => $timeline
];

echo json_encode($out);
exit;
