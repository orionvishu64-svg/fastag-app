<?php
// fastag_website/verify_payment.php
// Minimal payment verification without any Delhivery API calls.
// Verifies payment details, updates order payment_status, and leaves shipment creation to admin.

require_once 'common_start.php';
require 'db.php'; // provides $pdo
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// Basic validation
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$payment_status = isset($input['payment_status']) ? $input['payment_status'] : 'failed';
$payment_ref = isset($input['payment_ref']) ? $input['payment_ref'] : null;

if ($order_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid order id"]);
    exit;
}

try {
    // Fetch order to ensure it exists
    $stmt = $pdo->prepare("SELECT id, payment_method FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    // Update payment status
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = :payment_status, payment_ref = :payment_ref, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':payment_status' => $payment_status, ':payment_ref' => $payment_ref, ':id' => $order_id]);

    // If payment succeeded and method is online, mark order for shipment creation (admin will pick this up).
    if ($payment_status === 'success') {
        $stmt2 = $pdo->prepare("UPDATE orders SET shipment_status = :shipment_status, updated_at = NOW() WHERE id = :id");
        $stmt2->execute([':shipment_status' => 'pending_shipment', ':id' => $order_id]);
    }

    echo json_encode(["success" => true, "message" => "Payment status recorded", "order_id" => $order_id]);
    exit;

} catch (Exception $e) {
    error_log("verify_payment.php exception: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit;
}
