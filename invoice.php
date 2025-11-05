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
  <style>
    :root{
  /* theme tokens (matching styles.css) */
  --bg-dark: #0b0b0c;
  --panel-dark: #0f0f10;
  --panel-2: #141414;
  --text: #f3f3f3;
  --muted: #b0b0b0;
  --warm-yellow: #ffb84d;
  --warm-yellow-2: #ffcf73;
  --warm-red: #e85c41;
  --accent-glow: rgba(255,184,77,0.12);

  /* small helpers */
  --card-radius: 8px;
  --border-subtle: rgba(255,255,255,0.04);
}

/* base */
body{
  font-family: Inter, Arial, Helvetica, sans-serif;
  color:var(--text);
  background: linear-gradient(180deg,var(--bg-dark), #070707);
  margin:16px;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* container */
.container{
  max-width:900px;
  margin:0 auto;
  background: linear-gradient(180deg,var(--panel-dark),var(--panel-2));
  border:1px solid var(--border-subtle);
  padding:20px;
  border-radius:var(--card-radius);
  box-shadow:0 8px 30px rgba(0,0,0,0.6);
}

/* header */
.header{display:flex;justify-content:space-between;align-items:center;gap:12px}
.company h2{margin:0;color:var(--warm-yellow);font-weight:800}
.meta{ text-align:right; color:var(--muted) }

/* table */
table{width:100%;border-collapse:collapse;margin-top:18px;background:transparent}
th,td{
  padding:10px 12px;
  border:1px solid rgba(255,255,255,0.03);
  text-align:left;
  vertical-align:middle;
}
th{
  background: linear-gradient(180deg, rgba(255,184,77,0.04), rgba(255,184,77,0.02));
  color:var(--text);
  font-weight:700;
}
td{color:var(--text)}

/* right align helper */
.right{text-align:right}

/* small / muted */
.small{font-size:.9em;color:var(--muted)}

/* totals block */
.totals{margin-top:12px;display:flex;justify-content:flex-end}
.totals table{width:360px;border-collapse:collapse}
.totals th, .totals td{padding:10px;border:1px solid rgba(255,255,255,0.03);background:transparent}
.totals th{color:var(--muted);font-weight:600}
.totals td{color:var(--text);font-weight:800}

/* print button */
.print-btn{
  display:inline-block;
  padding:8px 12px;
  background:linear-gradient(90deg,var(--warm-yellow),var(--warm-yellow-2));
  color:#111;
  border-radius:8px;
  text-decoration:none;
  font-weight:700;
  box-shadow:0 8px 22px rgba(255,184,77,0.06);
  border:0;
  cursor:pointer;
}

/* small responsive tweaks */
@media (max-width:600px){
  .header{flex-direction:column;align-items:flex-start}
  .meta{text-align:left}
  .totals{justify-content:flex-start}
  .totals table{width:100%}
  table, th, td{font-size:.95rem}
}

/* accessibility focus */
a:focus, button:focus, .print-btn:focus {
  outline:3px solid rgba(255,184,77,0.08);
  outline-offset:3px;
}

/* Print rules:
   - For printing, force a light palette (better contrast on paper).
   - Keep content layout; hide UI elements you don't want printed.
*/
@media print {
  body{background: #fff; color:#111; margin:0}
  .container{background:#fff;border:0;box-shadow:none;padding:0}
  .print-hide{display:none !important}
  table, th, td{border:1px solid #e6e6e6;color:#111}
  th{background:#f7f7f7;color:#111}
  .company h2{color:#111}
  .print-btn{display:none}
}
  </style>
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
