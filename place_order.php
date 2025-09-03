<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// ✅ Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Please log in to place an order"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ Get POST data
$data = json_decode(file_get_contents("php://input"), true);
error_log("Received order payload: " . print_r($data, true));

if (!isset($data['address_id'], $data['payment_method'], $data['amount'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$address_id     = intval($data['address_id']);
$payment_method = strtolower(trim($data['payment_method']));
$amount         = floatval($data['amount']);
$transaction_id = isset($data['transaction_id']) ? trim($data['transaction_id']) : null;

// ✅ Allowed payment methods
$allowed_methods = ['agent-id', 'upi'];
if (!in_array($payment_method, $allowed_methods)) {
    echo json_encode(["status" => "error", "message" => "Invalid payment method"]);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($payment_method === 'agent-id') {
        // ✅ Agent Order → Paid immediately
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, address_id, payment_method, transaction_id, amount, status, payment_status)
            VALUES (:user_id, :address_id, :payment_method, :transaction_id, :amount, 'placed', 'paid')
        ");
        $stmt->execute([
            ':user_id'        => $user_id,
            ':address_id'     => $address_id,
            ':payment_method' => $payment_method,
            ':transaction_id' => $transaction_id,
            ':amount'         => $amount
        ]);
    } elseif ($payment_method === 'upi') {
        // ✅ UPI → Insert order but mark as unpaid (will be updated after verification)
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, address_id, payment_method, amount, status, payment_status)
            VALUES (:user_id, :address_id, :payment_method, :amount, 'placed', 'unpaid')
        ");
        $stmt->execute([
            ':user_id'        => $user_id,
            ':address_id'     => $address_id,
            ':payment_method' => $payment_method,
            ':amount'         => $amount
        ]);
    }

    $order_id = $pdo->lastInsertId();

    // ✅ Insert order items
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    if (!empty($items)) {
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, bank, product_name, quantity, price)
            VALUES (:order_id, :bank, :product_name, :quantity, :price)
        ");

        foreach ($items as $item) {
            if (isset($item['product_id'])) {
                $bankStmt = $pdo->prepare("SELECT bank FROM products WHERE id = ?");
                $bankStmt->execute([$item['product_id']]);
                $bank = $bankStmt->fetchColumn();
            } else {
                $bank = $item['bank'] ?? null;
            }

            $product_name = $item['product_name'] ?? ($item['name'] ?? 'Item');
            $quantity     = $item['quantity'] ?? 1;
            $price        = $item['price'] ?? 0;

            $itemStmt->execute([
                ':order_id'     => $order_id,
                ':bank'         => $bank,
                ':product_name' => $product_name,
                ':quantity'     => $quantity,
                ':price'        => $price
            ]);
        }
    }

    $pdo->commit();

    // ✅ Response
    echo json_encode([
        "status"  => "success",
        "message" => $payment_method === 'upi' ? "Proceed with UPI payment" : "Order placed successfully",
        "order_id" => $order_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
