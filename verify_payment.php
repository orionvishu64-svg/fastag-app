<?php
require_once 'common_start.php';
require 'db.php';
require 'delhivery.php';
header('Content-Type: application/json');

// ✅ Validate order_id
if (!isset($_GET['order_id'])) {
    echo json_encode(["success" => false, "message" => "Missing order_id"]);
    exit;
}
$order_id = intval($_GET['order_id']);

try {
    $stmt = $pdo->prepare("SELECT user_id, payment_status, address_id, amount, shipping_amount FROM orders WHERE id=?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    if ($order['payment_status'] === 'paid') {
        echo json_encode(["success" => true, "message" => "Order already marked as paid"]);
        exit;
    }

    // ✅ Mark as paid
    $upd = $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?");
    $ok = $upd->execute([$order_id]);

    if ($ok) {
        // ✅ Clear cart (if exists)
        try {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id=?");
            $stmt->execute([$order['user_id']]);
        } catch (Exception $ignored) {
            // ignore if no cart table
        }

        // ✅ Shipment Creation (UPI → after payment)
        $addrStmt = $pdo->prepare("
            SELECT a.*, u.name, u.phone as user_phone
            FROM addresses a
            JOIN users u ON a.user_id = u.id
            WHERE a.id=?
        ");
        $addrStmt->execute([$order['address_id']]);
        $address = $addrStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($address['user_phone']) && empty($address['phone'])) {
            $address['phone'] = $address['user_phone'];
        }

        $itemStmt = $pdo->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id=?");
        $itemStmt->execute([$order_id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $total_amount = floatval($order['amount']) + floatval($order['shipping_amount']);
        $shipment = delhivery_create_shipment($order_id, $address, $items, $total_amount);

        if (!empty($shipment['packages'][0]['waybill'])) {
            $awb = $shipment['packages'][0]['waybill'];
            $upd2 = $pdo->prepare("UPDATE orders SET awb=?, delhivery_status=? WHERE id=?");
            $upd2->execute([$awb, "Created", $order_id]);
        } else {
            error_log("Delhivery UPI shipment failed for order {$order_id}: " . json_encode($shipment));
        }

        echo json_encode([
            "success" => true,
            "message" => "Payment verified and cart cleared",
            "redirect" => "orderplaced.php"
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update payment"]);
    }

} catch (Exception $e) {
    error_log("verify_payment.php exception: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
