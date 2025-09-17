<?php
require_once 'common_start.php';
require 'db.php';
header('Content-Type: application/json');

// user must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','message'=>'Please log in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// read input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) $input = $_POST;

$payment_method = trim($input['payment_method'] ?? '');
$transaction_id = $input['transaction_id'] ?? null;
$address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0;
$items = $input['items'] ?? [];
if ($address_id <= 0 || empty($items)) {
    echo json_encode(['status'=>'error','message'=>'Missing or invalid order data']);
    exit;
}

// compute total
$total_amount = 0.0;
foreach ($items as $it) {
    $qty = isset($it['quantity']) ? (int)$it['quantity'] : 1;
    $price = isset($it['price']) ? (float)$it['price'] : 0;
    $total_amount += $qty * $price;
}

// apply your rule: agent-id = free
if (strtolower($payment_method) === 'agent-id') {
    $total_amount = 0.0;
}

try {
    $pdo->beginTransaction();

    // insert order (your schema)
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, address_id, payment_method, transaction_id, amount, shipping_amount, payment_status, status, created_at, updated_at)
        VALUES (:user_id, :address_id, :payment_method, :transaction_id, :amount, 0.00, 'pending', 'new', NOW(), NOW())
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':address_id' => $address_id,
        ':payment_method' => $payment_method,
        ':transaction_id' => $transaction_id,
        ':amount' => number_format($total_amount, 2, '.', '')
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // insert items
    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, bank, product_name, quantity, price, product_id) VALUES (:order_id, :bank, :product_name, :quantity, :price, :product_id)");
    foreach ($items as $it) {
        $stmtItem->execute([
            ':order_id' => $order_id,
            ':bank' => $it['bank'] ?? null,
            ':product_name' => $it['product_name'] ?? 'Item',
            ':quantity' => isset($it['quantity']) ? (int)$it['quantity'] : 1,
            ':price' => isset($it['price']) ? number_format((float)$it['price'], 2, '.', '') : 0.00,
            ':product_id' => $it['product_id'] ?? null
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status'=>'success',
        'order_id'=>$order_id,
        'message'=>($payment_method==='upi' ? 'Proceed with UPI payment' : 'Order placed successfully')
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status'=>'error','message'=>'DB error: '.$e->getMessage()]);
}
