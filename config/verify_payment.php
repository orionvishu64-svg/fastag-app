<?php
// config/verify_payment.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../lib/admin_ship_api.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'missing_order_id']);
    exit;
}

function respond($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, amount, payment_status FROM orders WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'order_not_found'], 404);
    }

    if (isset($order['payment_status']) && strtolower($order['payment_status']) === 'paid') {
        $pdo->commit();
        respond(['success' => true, 'message' => 'already_paid']);
    }

    if (!$transaction_id) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'missing_transaction_id', 'note' => 'Provide transaction_id for server verification']);
    }

    // -------------------------
    // Replace this function with real gateway verification.
    function verify_with_gateway_server($transaction_id, $order_id) {
        // Example stub - REPLACE with real verification using gateway API/SDK:
        // For Razorpay: GET /payments/{payment_id} with Basic Auth using key:secret
        // For Cashfree/Paytm: use their verification API.
        // Verify:
        //  - payment status is captured / success
        //  - amount (in paise) matches orders.amount * 100
        //  - merchant/order id matches if available
        return ['ok' => true, 'status' => 'captured', 'amount_paise' => null, 'raw' => ['note'=>'stub']];
    }
    // -------------------------

    $verify = verify_with_gateway_server($transaction_id, $order_id);
    if (empty($verify['ok']) || !in_array(strtolower($verify['status']), ['captured','success','settled'])) {
        $pdo->rollBack();
        error_log("verify_payment: gateway not verified order={$order_id} tx={$transaction_id} resp=" . json_encode($verify));
        respond(['success' => false, 'pending' => true, 'error' => 'not_verified']);
    }

    // Compare amounts if gateway returned amount (gateway amount expected in paise)
    if (isset($verify['amount_paise']) && $verify['amount_paise'] !== null) {
        $expected_paise = (int) round((float)$order['amount'] * 100);
        if ((int)$verify['amount_paise'] !== $expected_paise) {
            $pdo->rollBack();
            error_log("verify_payment: amount_mismatch order={$order_id} tx={$transaction_id} expected={$expected_paise} got={$verify['amount_paise']}");
            respond(['success' => false, 'error' => 'amount_mismatch']);
        }
    }

    // Update order as paid
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'paid', transaction_id = :tx, updated_at = NOW() WHERE id = :id");
    $upd->execute([':tx' => $transaction_id, ':id' => $order_id]);

    $pdo->commit();

    // Create shipment (server-to-server)
    $resp = admin_api_post('/api/create_shipment.php', ['order_id' => $order_id]);

    if (!empty($resp['success'])) {
        respond(['success' => true, 'message' => 'payment_verified', 'admin' => $resp['json'] ?? $resp]);
    } else {
        error_log("verify_payment: shipment_creation_failed order={$order_id} resp=" . json_encode($resp));
        respond(['success' => true, 'warning' => 'shipment_pending', 'admin_error' => $resp]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("verify_payment: exception order_id={$order_id} msg=" . $e->getMessage());
    respond(['success' => false, 'error' => 'server_error']);
}
