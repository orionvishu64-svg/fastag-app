<?php
require_once __DIR__ . '/common_start.php';
require 'db.php';

// ✅ Match track_orders.php session logic
if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}

$user_id = (int)$_SESSION['user']['id'];

// Validate order_id
$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    http_response_code(400);
    echo "Order ID missing.";
    exit;
}

// Fetch order and ensure ownership
$stmt = $pdo->prepare("
    SELECT o.id, o.user_id, o.amount, o.payment_method,
           o.awb, o.label_url, COALESCE(o.delhivery_status, o.status) AS delhivery_status,
           o.expected_delivery_date, o.created_at,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           a.house_no, a.landmark, a.city, a.pincode
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.id = :id AND o.user_id = :uid
    LIMIT 1
");
$stmt->execute([':id' => $order_id, ':uid' => $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo "Order not found or not yours.";
    exit;
}

// Fetch order items
$it = $pdo->prepare("SELECT bank, product_name, quantity, price, product_id FROM order_items WHERE order_id = :id");
$it->execute([':id' => $order_id]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

// Helper
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order #<?php echo esc($order_id); ?> — Details</title>
<link rel="stylesheet" href="/public/css/order_details.css">
</head>
<body>
<div class="order-details-container">
  <h2>Order #<?php echo esc($order_id); ?></h2>

  <div class="section">
    <h3>Order Info</h3>
    <p><strong>Date:</strong> <?php echo esc(date("d M Y, h:i A", strtotime($order['created_at']))); ?></p>
    <p><strong>Status:</strong> <?php echo esc(ucfirst($order['delhivery_status'] ?? 'Pending')); ?></p>
    <?php if (!empty($order['expected_delivery_date'])): ?>
      <p><strong>Expected Delivery:</strong> <?php echo esc($order['expected_delivery_date']); ?></p>
    <?php endif; ?>
    <p><strong>Payment Method:</strong> <?php echo esc($order['payment_method'] ?? ''); ?></p>
    <p><strong>Total:</strong> ₹<?php echo number_format($order['amount'], 2); ?></p>
  </div>

  <div class="section">
    <h3>Shipping & AWB</h3>
    <?php if (!empty($order['awb'])): ?>
      <p class="awb-line"><strong>AWB:</strong> <?php echo esc($order['awb']); ?>
        <?php if (!empty($order['label_url'])): ?>
          — <a href="<?php echo esc($order['label_url']); ?>" target="_blank">Download Label / Print</a>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="small-muted">Shipment has not been created yet. Please check back later.</p>
    <?php endif; ?>
    <div class="action-row">
      <a class="back-btn primary" href="/invoice.php?order_id=<?php echo esc($order_id); ?>" target="_blank">Download Invoice</a>
    </div>
  </div>

  <div class="section">
    <h3>Tracking Timeline</h3>
    <div id="tracking-area">
      <p class="small-muted">Loading timeline…</p>
    </div>
  </div>

  <div class="section">
    <h3>Items</h3>
    <table>
      <thead>
        <tr><th>Bank</th><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>
      </thead>
      <tbody>
      <?php if ($items): foreach ($items as $it): ?>
        <tr>
          <td><?php echo esc($it['bank']); ?></td>
          <td><?php echo esc($it['product_name']); if (!empty($it['sku'])) echo ' <small>(' . esc($it['sku']) . ')</small>'; ?></td>
          <td><?php echo (int)$it['quantity']; ?></td>
          <td>₹<?php echo number_format($it['price'], 2); ?></td>
          <td>₹<?php echo number_format($it['price'] * $it['quantity'], 2); ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5">No items found for this order.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;gap:12px;align-items:center;margin-top:18px">
    <a class="back-btn" href="/track_orders.php">⬅ Back to Orders</a>

    <?php
      $cur_status = $order['delhivery_status'] ?? null;
      $can_return = in_array(strtolower((string)$cur_status), ['delivered','out_for_delivery','delivered_by_courier','delivered'], true);
    ?>
    <?php if ($can_return): ?>
      <button id="open-return" class="back-btn success">Request Return</button>
    <?php endif; ?>
  </div>
</div>

<!-- Return modal (kept simple) -->
<div id="return-modal" style="display:none;position:fixed;left:0;right:0;top:0;bottom:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center">
  <div class="modal-card" style="max-width:560px;margin:40px auto;background:#fff;padding:18px;border-radius:8px">
    <h3>Request Return for Order #<?php echo esc($order_id); ?></h3>
    <textarea id="return-reason" placeholder="Describe reason for return" style="width:100%;height:120px;padding:10px;border-radius:6px;border:1px solid #eee"></textarea>
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
      <button id="submit-return" class="back-btn primary" style="background:#1976d2;color:#fff">Submit</button>
      <button id="cancel-return" class="back-btn">Cancel</button>
    </div>
    <div id="return-feedback" style="margin-top:8px"></div>
  </div>
</div>

<script>
  const ORDER_ID = <?php echo (int)$order_id; ?>;
</script>
<script src="/public/js/site.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Hook up modal and submit behavior
    var openBtn = document.getElementById('open-return');
    if (openBtn) openBtn.addEventListener('click', function(){ document.getElementById('return-modal').style.display = 'flex'; });
    var cancelBtn = document.getElementById('cancel-return');
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ document.getElementById('return-modal').style.display = 'none'; });
    var submitBtn = document.getElementById('submit-return');
    if (submitBtn) submitBtn.addEventListener('click', function(){ submitReturnRequest(); });

    // Load timeline via AJAX (/public/js/site.js -> loadTracking expects timeline items containing created_at, status, location, awb)
    if (typeof loadTracking === 'function') {
        loadTracking(ORDER_ID);
        // optional polling for updates
        setInterval(function(){ loadTracking(ORDER_ID); }, 60000);
    }
});
</script>
</body>
</html>
