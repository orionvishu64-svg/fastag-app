<?php
// delhivery_webhook.php
// Public endpoint that Delhivery will POST tracking updates to.
// Place in fastag_website/ and ensure HTTPS + publicly accessible URL.
// Optionally validate incoming requests (IP whitelist / signature) if available.

require_once 'common_start.php';
require 'db.php';

file_put_contents(__DIR__ . "/logs/webhook_test.log",
    date("c") . " " . file_get_contents("php://input") . "\n",
    FILE_APPEND
);

http_response_code(200); // respond quickly, we'll process

$raw = file_get_contents('php://input');
if (empty($raw)) {
    error_log("delhivery_webhook: empty payload");
    echo "ok";
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    error_log("delhivery_webhook: invalid json: " . substr($raw,0,500));
    echo "ok";
    exit;
}

// Log payload for debugging (rotate logs in production)
file_put_contents(__DIR__ . "/logs/delhivery_webhook.log", date('c') . " " . $raw . PHP_EOL, FILE_APPEND);

// Example: Delhivery webhook structure can vary; look for waybill/order/status/checkpoints
// We'll try both : 'waybill' or 'order' mapping
$waybill = $data['waybill'] ?? ($data['packages'][0]['waybill'] ?? null);
$order_ref = $data['order'] ?? ($data['packages'][0]['order'] ?? null);
$status = $data['status'] ?? ($data['packages'][0]['status'] ?? null);
$checkpoint = $data['checkpoint'] ?? null; // sometimes includes location/time/message

try {
    if ($order_ref) {
        // find DB order by order reference
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$order_ref]);
        $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $order_id = $orderRow ? intval($orderRow['id']) : null;
    } else if ($waybill) {
        // try by awb
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE awb = ? LIMIT 1");
        $stmt->execute([$waybill]);
        $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $order_id = $orderRow ? intval($orderRow['id']) : null;
    } else {
        $order_id = null;
    }

    if ($order_id) {
        // update order status if available
        if (!empty($status)) {
            $upd = $pdo->prepare("UPDATE orders SET delhivery_status = ? WHERE id = ?");
            $upd->execute([$status, $order_id]);
        }

        // store a generic tracking event
        $loc = $data['location'] ?? ($checkpoint['location'] ?? null);
        $msg = $data['message'] ?? ($checkpoint['message'] ?? json_encode($data));
        $time = $data['time'] ?? ($checkpoint['time'] ?? date('Y-m-d H:i:s'));

        $ins = $pdo->prepare("INSERT INTO order_tracking (order_id, location, updated_at) VALUES (?, ?, ?)");
        $ins->execute([$order_id, substr($msg . ($loc ? ' @' . $loc : ''), 0, 250), $time]);

        // optionally: notify user via email/SMS/push — add hooks here
    } else {
        // no order match — log for manual inspection
        error_log("delhivery_webhook: could not map webhook to order. payload: " . substr($raw,0,500));
    }
} catch (Exception $e) {
    error_log("delhivery_webhook exception: " . $e->getMessage() . " payload: " . substr($raw,0,500));
}

// respond 200 OK
echo "ok";
