<?php
require_once 'common_start.php';
require 'db.php';
require 'delhivery.php';
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
$payment_method = strtolower(trim($data['payment_method'])); // upi or agent-id
$amount         = floatval($data['amount']);                 // base product cost
$transaction_id = isset($data['transaction_id']) ? trim($data['transaction_id']) : null;

$allowed_methods = ['agent-id', 'upi'];
if (!in_array($payment_method, $allowed_methods)) {
    echo json_encode(["status" => "error", "message" => "Invalid payment method"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ✅ Fetch address + user name
    $addrStmt = $pdo->prepare("
        SELECT a.*, u.name, u.phone as user_phone
        FROM addresses a
        JOIN users u ON a.user_id = u.id
        WHERE a.id=? AND a.user_id=?");
    $addrStmt->execute([$address_id, $user_id]);
    $address = $addrStmt->fetch(PDO::FETCH_ASSOC);

    if (!$address || empty($address['pincode'])) {
        throw new Exception("Invalid address or pincode not found");
    }

    if (empty($address['phone']) && !empty($address['user_phone'])) {
        $address['phone'] = $address['user_phone'];
    }

    // ✅ Calculate shipping cost
    $shipping_amount = delhivery_calculate_shipping($address['pincode']);
    $total_amount = $amount + $shipping_amount;

    // ✅ Insert order
    if ($payment_method === 'agent-id') {
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, address_id, payment_method, transaction_id, amount, shipping_amount, status, payment_status)
            VALUES (:user_id, :address_id, :payment_method, :transaction_id, :amount, :shipping_amount, 'placed', 'paid')
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':address_id' => $address_id,
            ':payment_method' => $payment_method,
            ':transaction_id' => $transaction_id,
            ':amount' => $amount,
            ':shipping_amount' => $shipping_amount
        ]);
    } else { // upi
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, address_id, payment_method, amount, shipping_amount, status, payment_status)
            VALUES (:user_id, :address_id, :payment_method, :amount, :shipping_amount, 'placed', 'unpaid')
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':address_id' => $address_id,
            ':payment_method' => $payment_method,
            ':amount' => $amount,
            ':shipping_amount' => $shipping_amount
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
            if (!empty($item['product_id'])) {
                $bankStmt = $pdo->prepare("SELECT bank FROM products WHERE id=?");
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

    // ✅ Shipment Creation for Agent-ID (immediate)
    if ($payment_method === 'agent-id') {
        $itemStmt = $pdo->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id=?");
        $itemStmt->execute([$order_id]);
        $order_items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $shipment = delhivery_create_shipment($order_id, $address, $order_items, $total_amount);

        if (!empty($shipment['packages'][0]['waybill'])) {
            $awb = $shipment['packages'][0]['waybill'];
            $upd = $pdo->prepare("UPDATE orders SET awb=?, delhivery_status=? WHERE id=?");
            $upd->execute([$awb, "Created", $order_id]);
        } else {
            error_log("Delhivery Agent-ID shipment failed for order {$order_id}: " . json_encode($shipment));
        }
    }

    echo json_encode([
        "status"          => "success",
        "message"         => $payment_method === 'upi' ? "Proceed with UPI payment" : "Order placed successfully",
        "order_id"        => $order_id,
        "shipping_amount" => $shipping_amount,
        "total_amount"    => $total_amount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("place_order.php exception: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
