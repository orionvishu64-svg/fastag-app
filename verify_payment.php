<?php
// verify_payment.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/admin_ship_api.php';

// Example: you receive POST with order_id and gateway status
$order_id = (int)($_POST['order_id'] ?? 0);
$gateway_status = $_POST['gateway_status'] ?? 'failed'; // depends on your gateway

if (!$order_id) { echo json_encode(['success'=>false,'error'=>'missing order_id']); exit; }

// verify gateway response with their APIs/SDK (not covered here). Assume success:
if ($gateway_status === 'success') {
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'paid', transaction_id = :tx, updated_at = NOW() WHERE id = :id");
    $upd->execute([':tx'=>$_POST['transaction_id'] ?? null, ':id'=>$order_id]);

    // call admin to create shipment
    $resp = admin_api_post('/api/create_shipment.php', ['order_id' => $order_id]);
    if (!empty($resp['success'])) {
        echo json_encode(['success'=>true,'admin'=>$resp['json'] ?? $resp]);
    } else {
        // if admin create failed, return success but the workers will pick it up later
        echo json_encode(['success'=>true,'warning'=>'shipment_pending','admin_error'=>$resp]);
    }
    exit;
} else {
    echo json_encode(['success'=>false,'error'=>'payment_failed']);
    exit;
}
