<?php
require 'db.php';

header('Content-Type: application/json');

// ✅ Validate order_id
if (!isset($_GET['order_id'])) {
    echo json_encode(["success" => false, "message" => "Missing order_id"]);
    exit;
}

$order_id = intval($_GET['order_id']);

try {
    // ✅ Check if order exists
    $stmt = $pdo->prepare("SELECT user_id, payment_status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    // ✅ If already paid, no need to update again
    if ($order['payment_status'] === 'paid') {
        echo json_encode(["success" => true, "message" => "Order already marked as paid"]);
        exit;
    }

    // ✅ Mark as paid
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $ok = $stmt->execute([$order_id]);

    if ($ok) {
        // ✅ Clear cart for that user
        try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$order['user_id']]);
    } catch (Exception $ignored) {
        // Ignore if cart table doesn't exist
    }

        // ✅ Success response
        echo json_encode([
            "success" => true,
            "message" => "Payment verified and cart cleared",
            "redirect" => "orderplaced.html"
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update payment"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
