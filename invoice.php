<?php
// invoice.php - robust invoice rendering (joins users + addresses)
//
// Usage: /invoice.php?order_id=NN
require_once __DIR__ . '/config/common_start.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';

// helper
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    http_response_code(400);
    echo "Missing order_id";
    exit;
}

// get current user id (allow admin)
$currentUserId = null;
if (function_exists('get_current_user_id')) $currentUserId = get_current_user_id();
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.user_id, o.amount, o.shipping_amount, o.created_at, o.awb, o.label_url,
            o.payment_method, o.payment_status, o.status AS order_status,
            u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            a.house_no, a.landmark, a.city, a.pincode
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("invoice fetch error: " . $e->getMessage());
    http_response_code(500);
    echo "Server error.";
    exit;
}

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

// Access control: owner or admin
$is_admin = !empty($_SESSION['admin_id']);
if (!$is_admin && ($currentUserId === null || (int)$order['user_id'] !== (int)$currentUserId)) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// fetch items
try {
    $it = $pdo->prepare("SELECT product_name, bank, quantity, price FROM order_items WHERE order_id = :id");
    $it->execute([':id' => $order_id]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("invoice items fetch error: " . $e->getMessage());
    $items = [];
}

// totals
$totalAmount = isset($order['amount']) ? (float)$order['amount'] : 0.0;
$shipping = isset($order['shipping_amount']) ? (float)$order['shipping_amount'] : 0.0;
$grand = $totalAmount + $shipping;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Invoice #<?php echo esc($order['id']); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="public/css/invoice.css" />
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="company">
        <h2>Apna Payments Services PVT LTD</h2>
        <div class="small">A-40, KARDHANI, GOVINDPURA,<br>JAIPUR, RAJASTHAN, 302012  • GSTIN: 08AAVCA0650L1ZA</div>
      </div>
      <div class="meta">
        <div><strong>Invoice</strong></div>
        <div>Invoice #: <strong><?php echo esc($order['id']); ?></strong></div>
        <div>Date: <?php echo esc($order['created_at'] ?? date('Y-m-d H:i:s')); ?></div>
        <div class="small">Order #: <?php echo esc($order['id']); ?></div>
      </div>
    </div>

    <div style="margin-top:16px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <h4 style="margin:0 0 6px 0">Billed To</h4>
        <div><?php echo esc($order['customer_name'] ?? ''); ?></div>
        <div class="small"><?php echo esc($order['customer_email'] ?? ''); ?></div>
        <?php if(!empty($order['customer_phone'])): ?><div class="small"><?php echo esc($order['customer_phone']); ?></div><?php endif; ?>
      </div>

      <div style="flex:1;min-width:220px">
        <h4 style="margin:0 0 6px 0">Shipping</h4>
        <?php if(!empty($order['house_no'])): ?><div><?php echo esc($order['house_no']); ?></div><?php endif; ?>
        <?php if(!empty($order['landmark'])): ?><div class="small"><?php echo esc($order['landmark']); ?></div><?php endif; ?>
        <?php if(!empty($order['city']) || !empty($order['pincode'])): ?><div class="small"><?php echo esc($order['city'] ?? '') . ' - ' . esc($order['pincode'] ?? ''); ?></div><?php endif; ?>
        <?php if(!empty($order['awb'])): ?><div style="margin-top:8px"><strong>AWB:</strong> <?php echo esc($order['awb']); ?></div><?php endif; ?>
        <?php if(!empty($order['order_status'])): ?><div class="small">Status: <?php echo esc($order['order_status']); ?></div><?php endif; ?>
      </div>

      <div style="min-width:200px">
        <h4 style="margin:0 0 6px 0">Payment</h4>
        <div><?php echo esc(ucfirst($order['payment_method'] ?? '')); ?></div>
        <div class="small">Payment status: <?php echo esc($order['payment_status'] ?? ''); ?></div>
      </div>
    </div>

    <table>
      <thead><tr><th>Product</th><th>Bank</th><th>Qty</th><th>Price</th><th class="right">Total</th></tr></thead>
      <tbody>
        <?php if(!empty($items)): foreach($items as $it): ?>
          <tr>
            <td><?php echo esc($it['product_name']); ?></td>
            <td><?php echo esc($it['bank'] ?? ''); ?></td>
            <td><?php echo (int)$it['quantity']; ?></td>
            <td>₹<?php echo number_format((float)$it['price'],2); ?></td>
            <td class="right">₹<?php echo number_format(((int)$it['quantity'])*(float)$it['price'],2); ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5">No items found for this order.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <table>
        <tr><td class="small">Subtotal</td><td class="right">₹<?php echo number_format($totalAmount,2); ?></td></tr>
        <tr><td class="small">Shipping</td><td class="right">₹<?php echo number_format($shipping,2); ?></td></tr>
        <tr><th>Total</th><th class="right">₹<?php echo number_format($grand,2); ?></th></tr>
      </table>
    </div>

    <div style="margin-top:12px" class="print-hide">
      <a class="print-btn" href="javascript:window.print()">Print Invoice</a>
    </div>
  </div>
</body>
</html>
