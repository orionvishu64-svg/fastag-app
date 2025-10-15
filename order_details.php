<?php
// order_details.php
// Drop-in replacement — uses your DB schema and paths exactly.

require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (empty($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo "Invalid order id.";
    exit;
}
$order_id = (int) $_GET['order_id'];

// Load order (must belong to user)
try {
    $stmt = $pdo->prepare("
        SELECT o.*, a.house_no, a.landmark, a.city, a.pincode, a.user_id AS address_user
        FROM orders o
        LEFT JOIN addresses a ON a.id = o.address_id
        WHERE o.id = :oid AND o.user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':oid' => $order_id, ':uid' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("order_details.php: db error loading order {$order_id}: " . $ex->getMessage());
    $order = false;
}

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

// Items
try {
    $itstm = $pdo->prepare("SELECT id, order_id, bank, product_name, quantity, price, product_id FROM order_items WHERE order_id = :oid");
    $itstm->execute([':oid' => $order_id]);
    $items = $itstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}

// Tracking timeline from order_tracking
try {
    $tstm = $pdo->prepare("SELECT id, order_id, location, updated_at FROM order_tracking WHERE order_id = :oid ORDER BY updated_at ASC");
    $tstm->execute([':oid' => $order_id]);
    $tracking = $tstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracking = [];
}

// Check if there is a return record
$return_exists = false;
try {
    $rstm = $pdo->prepare("SELECT id, order_id, user_id, status, created_at FROM returns WHERE order_id = :oid AND user_id = :uid LIMIT 1");
    $rstm->execute([':oid' => $order_id, ':uid' => $user_id]);
    $return_exists = (bool) $rstm->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $return_exists = false;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Order #<?= e($order['id']) ?> — Details</title>
  <link rel="stylesheet" href="/public/css/order_details.css">
</head>
<body>
  <main class="container">
    <header class="topbar">
      <div class="brand">
        <div class="logo">APS</div>
        <div>
          <h1>Order details</h1>
          <p class="lead">Order #<?= e($order['id']) ?> — <?= e($order['status'] ?? '') ?></p>
        </div>
      </div>
      <div>
        <small class="lead">Signed in as <strong><?= e($_SESSION['user']['email'] ?? $_SESSION['user']['name'] ?? 'User') ?></strong></small>
      </div>
    </header>

    <div class="layout">
      <section class="left">
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <h2>Order summary</h2>
              <div class="label">Placed on <?= e(date('d M, Y H:i', strtotime($order['created_at']))) ?></div>
            </div>
            <div style="text-align:right">
              <div class="label">Order total</div>
              <div class="big"><?= '₹ ' . number_format($order['amount'], 2) ?></div>
            </div>
          </div>

          <hr style="margin:12px 0;border:none;height:1px;background:rgba(0,0,0,0.06)">

          <div class="order-summary">
            <div class="mini">
              <h4>Items</h4>
              <?php if (empty($items)): ?>
                <div class="label">No items found.</div>
              <?php else: foreach ($items as $it): ?>
                <div class="item">
                  <div class="thumb"><?= e(substr($it['product_name'],0,2)) ?></div>
                  <div class="meta">
                    <div class="title"><?= e($it['product_name']) ?></div>
                    <div class="small label">Qty: <?= (int)$it['quantity'] ?> • <?= '₹ '.number_format($it['price'],2) ?></div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <div class="mini">
              <h4>Shipping & payment</h4>
              <div class="label"><strong>Address:</strong> <?= e(trim(($addresses['house_no'] ?? '') . ' ' . ($addresses['landmark'] ?? '') . ', ' . ($addresses['city'] ?? '') . ' - ' . ($addresses['pincode'] ?? ''))) ?></div>
              <div style="margin-top:8px" class="label"><strong>Payment:</strong> <?= e($order['payment_method'] ?? '') ?> • <?= e($order['payment_status'] ?? '') ?> • TXN: <?= e($order['transaction_id'] ?? '') ?></div>
              <div style="margin-top:8px" class="label"><strong>Shipping:</strong> <?= '₹ '.number_format($order['shipping_amount'] ?? 0,2) ?></div>

              <div style="margin-top:12px">
                <h4>Courier & AWB</h4>
                <div class="label">AWB: <strong id="awb"><?= e($order['awb'] ?? '') ?></strong></div>
                <div class="label">Delhivery status: <strong><?= e($order['delhivery_status'] ?? '') ?></strong></div>
                <div class="actions" style="margin-top:10px">
                  <?php if (!empty($order['awb'])): ?>
                    <button class="btn" onclick="window.open('https://www.delhivery.com/track?waybill=<?= e($order['awb']) ?>','_blank')">Open Delhivery</button>
                    <button class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($order['awb']) ?>').then(()=>alert('AWB copied'))">Copy AWB</button>
                  <?php endif; ?>
                  <a class="btn danger" href="invoice.php?order_id=<?= (int)$order['id'] ?>">Download invoice</a>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px">
            <h3>Tracking timeline</h3>
            <div class="timeline">
              <!-- show delhivery status as primary event -->
              <?php if (!empty($order['delhivery_status'])): ?>
                <div class="tl-item done"><strong>Delhivery status: <?= e($order['delhivery_status']) ?></strong><small class="label"><?= e($order['updated_at']) ?></small></div>
              <?php endif; ?>

              <?php if (empty($tracking)): ?>
                <div class="label">No tracking updates found.</div>
              <?php else: foreach ($tracking as $t): ?>
                <div class="tl-item <?= '' ?>"><strong><?= e($t['location']) ?></strong><small class="label"><?= e($t['updated_at']) ?></small></div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <div style="margin-top:16px" class="actions">
            <?php if (!$return_exists): ?>
              <form method="post" action="returns.php" onsubmit="return confirm('Request a return for this order?');" style="display:inline-block">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">
                <button type="submit" class="btn danger">Request return</button>
              </form>
            <?php else: ?>
              <div class="label">Return requested — check your Returns page for status.</div>
            <?php endif; ?>

            <a class="btn primary" href="orders.php">Back to orders</a>
          </div>

        </div>
      </section>

      <aside class="right">
        <div class="card">
          <h4>Order info</h4>
          <div class="label">Order #: <strong><?= e($order['id']) ?></strong></div>
          <div class="label">Placed: <?= e(date('d M, Y', strtotime($order['created_at']))) ?></div>
          <div class="label">Status: <strong><?= e($order['status'] ?? '') ?></strong></div>
          <div style="margin-top:10px" class="actions">
            <a class="btn primary" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
          </div>
        </div>
      </aside>
    </div>
  </main>
</body>
</html>
