<?php
// place_order.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/admin_ship_api.php';

// assume POST contains: user_id, address_id, items (json), payment_method ('upi' or 'agent_id')
$user_id = (int)($_POST['user_id'] ?? 0);
$address_id = (int)($_POST['address_id'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? 'upi');
$items = json_decode($_POST['items'] ?? '[]', true);

// Compute total server-side. Example:
$total = 0.00;
foreach ($items as $it) {
    $qty = (int)($it['quantity'] ?? 1);
    $price = (float)($it['price'] ?? 0.0);
    $total += $qty * $price;
}

// shipping_amount can be calculated via admin calculate_cost API if needed.
// For now set to 0 or compute via admin API later
$shipping_amount = 0.00;

// Insert order
$ins = $pdo->prepare("INSERT INTO orders (user_id, address_id, payment_method, amount, shipping_amount, payment_status, created_at, updated_at) VALUES (:uid, :aid, :pm, :amt, :ship, :pstatus, NOW(), NOW())");
$payment_status = 'pending';
if ($payment_method === 'agent_id') {
    // Agent_id is treated prepaid per your requirement
    $payment_status = 'paid';
}
$ins->execute([
    ':uid'=>$user_id, ':aid'=>$address_id, ':pm'=>$payment_method,
    ':amt'=>number_format($total,2,'.',''), ':ship'=>$shipping_amount, ':pstatus'=>$payment_status
]);
$order_id = $pdo->lastInsertId();

// insert order_items
$itstmt = $pdo->prepare("INSERT INTO order_items (order_id, product_name, quantity, price, product_id) VALUES (:oid, :pname, :qty, :price, :pid)");
foreach ($items as $it) {
    $itstmt->execute([
        ':oid'=>$order_id,
        ':pname'=>$it['product_name'] ?? '',
        ':qty'=> (int)($it['quantity'] ?? 1),
        ':price'=> number_format((float)($it['price'] ?? 0),2,'.',''),
        ':pid'=> (int)($it['product_id'] ?? 0)
    ]);
}

// If payment_method is UPI, redirect to payment gateway flow and set payment_status to 'pending' until verify_payment marks 'paid'.
// For agent_id (prepaid) we already set 'paid' and should immediately call admin to create shipment:
if ($payment_status === 'paid') {
    // call admin create_shipment for instant AWB
    $resp = admin_api_post('/api/create_shipment.php', ['order_id' => (int)$order_id]);
    // store admin response in a log table or check $resp['success']
    // optionally show AWB to user if present
}

// Return success to client
echo json_encode(['success'=>true,'order_id'=>$order_id]);
exit;
