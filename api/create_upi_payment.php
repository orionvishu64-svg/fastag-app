<?php
// api/create_upi_payment.php

require_once __DIR__ . '/../config/common_start.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_exit(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function generate_order_code(): string {
    return 'AFT' . date('ymd_Hi') . '_' . strtoupper(bin2hex(random_bytes(2)));
}

function generate_token(): string {
    return bin2hex(random_bytes(16));
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    json_exit(['success' => false, 'message' => 'Not authenticated'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_exit(['success' => false, 'message' => 'Invalid JSON'], 400);
}

$cart = $input['items'] ?? [];
$addressId = (int)($input['address_id'] ?? 0);

if ($addressId <= 0) {
    json_exit(['success' => false, 'message' => 'Invalid address'], 400);
}

if (empty($cart) || !is_array($cart)) {
    json_exit(['success' => false, 'message' => 'Cart is empty'], 400);
}

$totalAmount = 0.0;

foreach ($cart as $item) {
    if (empty($item['name'])) {
        json_exit(['success' => false, 'message' => 'Invalid product name'], 400);
    }

    $qty   = max(1, (int)($item['quantity'] ?? 1));
    $price = (float)($item['price'] ?? 0);

    if ($price <= 0) {
        json_exit(['success' => false, 'message' => 'Invalid product price'], 400);
    }

    $totalAmount += $qty * $price;
}

if ($totalAmount <= 0) {
    json_exit(['success' => false, 'message' => 'Invalid order amount'], 400);
}

try {
    $pdo->beginTransaction();

    /* 1️⃣ Create order */
    $orderCode = generate_order_code();

    $stmt = $pdo->prepare("
        INSERT INTO orders
        (
            user_id,
            address_id,
            payment_method,
            transaction_id,
            amount,
            shipping_amount,
            payment_status,
            status,
            created_at
        )
        VALUES
        (
            :uid,
            :aid,
            'upi',
            :trx,
            :amt,
            0,
            'pending',
            'created',
            NOW()
        )
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':aid' => $addressId,
        ':trx' => $orderCode,
        ':amt' => $totalAmount
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* 2️⃣ Insert order items */
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items
        (
            order_id,
            product_name,
            bank,
            quantity,
            price,
            product_id
        )
        VALUES
        (
            :oid,
            :name,
            :bank,
            :qty,
            :price,
            :pid
        )
    ");

    foreach ($cart as $item) {
        $itemStmt->execute([
            ':oid'   => $orderId,
            ':name'  => $item['name'],
            ':bank'  => $item['bank'] ?? null,
            ':qty'   => (int)($item['quantity'] ?? 1),
            ':price' => (float)$item['price'],
            ':pid'   => $item['product_id'] ?? null
        ]);
    }

    /* 3️⃣ Create payment record */
    $token = generate_token();

    $payStmt = $pdo->prepare("
        INSERT INTO payments
        (
            order_id,
            token,
            payment_method,
            amount,
            status,
            created_at,
            expires_at
        )
        VALUES
        (
            :oid,
            :token,
            'upi',
            :amt,
            'PENDING',
            NOW(),
            DATE_ADD(NOW(), INTERVAL 5 MINUTE)
        )
    ");
    $payStmt->execute([
        ':oid'   => $orderId,
        ':token' => $token,
        ':amt'   => $totalAmount
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('UPI CREATE ERROR: ' . $e->getMessage());

    json_exit(['success' => false, 'message' => 'Unable to create payment'], 500);
}

json_exit([
    'success'    => true,
    'token'      => $token,
    'order_code' => $orderCode,
    'amount'     => number_format($totalAmount, 2, '.', '')
]);