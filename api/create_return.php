<?php
// api/create_return.php
// Creates a local return entry and forwards it to the admin site for RVP/QC handling.

header('Content-Type: application/json; charset=utf-8');

// prefer common_start helpers if present
$common = __DIR__ . '/../config/common_start.php';
if (file_exists($common)) require_once $common;

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/admin_ship_api.php';

// determine current user id (robust fallback chain)
$currentUserId = null;
if (function_exists('get_current_user_id')) {
    $currentUserId = get_current_user_id();
}
if ($currentUserId === null) {
    if (!empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
}
if ($currentUserId === null) {
    if (!empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];
}

if (empty($currentUserId)) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['order_id']) || empty($input['reason'])) {
    echo json_encode(['success' => false, 'error' => 'invalid_input']);
    exit;
}

$order_id = (int)$input['order_id'];
$reason = trim($input['reason']);

try {
    // Verify order ownership
    $stmt = $pdo->prepare("SELECT id, user_id, awb FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'order_not_found']);
        exit;
    }
    if ((int)$order['user_id'] !== (int)$currentUserId) {
        echo json_encode(['success' => false, 'error' => 'not_owner']);
        exit;
    }

    // Insert into returns table
    $ins = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, status, created_at, updated_at) VALUES (:order_id, :uid, :reason, 'requested', NOW(), NOW())");
    $ins->execute([':order_id' => $order_id, ':uid' => $currentUserId, ':reason' => $reason]);
    $return_id = (int)$pdo->lastInsertId();

    // Build payload for admin (best effort)
    $payload = [
        'return_id' => $return_id,
        'order_id' => $order_id,
        'user_id' => $currentUserId,
        'reason' => $reason,
        'awb' => $order['awb'] ?? null,
        'status' => 'requested'
    ];

    // Forward to admin API asynchronously (best-effort)
    try {
        $resp = admin_api_post('/api/returns/create.php', $payload);
        if ($resp['success'] && !empty($resp['json'])) {
            $j = $resp['json'];
            // Optionally update local return row with data admin returned
            if (!empty($j['rvp_awb'])) {
                $u = $pdo->prepare("UPDATE returns SET external_awb = :awb, status = 'processing', updated_at = NOW() WHERE id = :id");
                $u->execute([':awb' => $j['rvp_awb'], ':id' => $return_id]);
            }
        }
    } catch (Exception $e) {
        error_log('create_return admin_api_post exception: ' . $e->getMessage());
        // continue - return success to customer, admin will be notified eventually
    }

    echo json_encode(['success' => true, 'return_id' => $return_id, 'message' => 'Return requested successfully']);
    exit;

} catch (PDOException $e) {
    error_log('create_return db error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}
