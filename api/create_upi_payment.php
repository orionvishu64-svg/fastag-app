<?php
// api/create_upi_payment.php
require_once __DIR__ . '/../config/common_start.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_exit($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    exit;
}

function generate_order_code() {
    return 'AFT' . date('ymd_Hi') . chr(rand(65, 90));
}

function generate_token() {
    return bin2hex(random_bytes(16)); 
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    json_exit([
        'success' => false,
        'message' => 'Not authenticated'
    ], 401);
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || !is_array($cart)) {
    json_exit([
        'success' => false,
        'message' => 'Cart is empty'
    ], 400);
}

$totalAmount = 0;
foreach ($cart as $item) {
    $qty = max(1, (int)($item['quantity'] ?? 1));
    $price = (float)($item['price'] ?? 0);
    $totalAmount += ($qty * $price);
}

if ($totalAmount <= 0) {
    json_exit([
        'success' => false,
        'message' => 'Invalid order amount'
    ], 400);
}

try {
    $pdo->beginTransaction();

    // 1️⃣ Create order
    $orderCode = generate_order_code();

    $stmt = $pdo->prepare("
        INSERT INTO orders
        (user_id, amount, payment_method, payment_status, status, created_at)
        VALUES
        (:uid, :amt, 'upi', 'pending', 'created', NOW())
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':amt' => $totalAmount
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // 2️⃣ Insert order items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items
        (order_id, product_name, bank, quantity, price, product_id)
        VALUES
        (:oid, :name, :bank, :qty, :price, :pid)
    ");

    foreach ($cart as $item) {
        $itemStmt->execute([
            ':oid'   => $orderId,
            ':name'  => $item['product_name'] ?? '',
            ':bank'  => $item['bank'] ?? null,
            ':qty'   => (int)($item['quantity'] ?? 1),
            ':price' => (float)($item['price'] ?? 0),
            ':pid'   => $item['product_id'] ?? null
        ]);
    }

    // 3️⃣ Create payment token (5 min expiry)
    $token = generate_token();

    $payStmt = $pdo->prepare("
        INSERT INTO payments
        (order_id, token, amount, status, created_at, expires_at)
        VALUES
        (:oid, :token, :amt, 'INITIATED', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
    ");
    $payStmt->execute([
        ':oid'   => $orderId,
        ':token' => $token,
        ':amt'   => $totalAmount
    ]);

    // 4️⃣ Store order_code safely
    $pdo->prepare("
        UPDATE orders SET transaction_id = :code WHERE id = :id
    ")->execute([
        ':code' => $orderCode,
        ':id'   => $orderId
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_exit([
        'success' => false,
        'message' => 'Unable to create payment'
    ], 500);
}

json_exit([
    'success'    => true,
    'token'      => $token,
    'order_code' => $orderCode,
    'amount'     => number_format($totalAmount, 2, '.', '')
]);