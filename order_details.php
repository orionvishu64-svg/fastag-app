<?php
// order_details.php (Delhivery-free rewrite)
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

// 1) Load order (must belong to this user)
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE id = :oid AND user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':oid' => $order_id, ':uid' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log('order_details.php: order load error '.$ex->getMessage());
    $order = false;
}

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

// 2) Load address from addresses table
$address = [];
try {
    // If not found, fallback to most recent address for this user (non-destructive)
    if (empty($address)) {
        $fb = $pdo->prepare("
            SELECT house_no, landmark, city, pincode
            FROM addresses
            WHERE user_id = :uid
            ORDER BY id DESC
            LIMIT 1
        ");
        $fb->execute([':uid' => $user_id]);
        $address = $fb->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    error_log('order_details.php: address load error '.$e->getMessage());
    $address = [];
}

// 3) Load items from order_items
try {
    $itstm = $pdo->prepare("
        SELECT id, order_id, bank, product_name, quantity, price, product_id
        FROM order_items
        WHERE order_id = :oid
    ");
    $itstm->execute([':oid' => $order_id]);
    $items = $itstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}

// 4) Load tracking from order_tracking
try {
    $tstm = $pdo->prepare("
        SELECT id, order_id, location, updated_at
        FROM order_tracking
        WHERE order_id = :oid
        ORDER BY updated_at ASC
    ");
    $tstm->execute([':oid' => $order_id]);
    $tracking = $tstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracking = [];
}

// 5) Check returns table for existing request
$return_exists = false;
try {
    $rstm = $pdo->prepare("
        SELECT id
        FROM returns
        WHERE order_id = :oid AND user_id = :uid
        LIMIT 1
    ");
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
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/order_details.css">
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <main class="container">
    <header class="topbar">
      <div class="brand">
        <div>
          <h1>Order details</h1>
          <p class="lead">Order #<?= e($order['id']) ?> — <?= e($order['status'] ?? '') ?></p>
        </div>
      </div>
      <div>
      </div>
    </header>

    <div class="layout">
      <section class="left">
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <h2>Order summary</h2>
              <div class="label">Placed on <?= e(date('d M, Y H:i', strtotime($order['created_at'] ?? 'now'))) ?></div>
            </div>
            <div style="text-align:right">
              <div class="label">Order total</div>
              <div class="big"><?= '₹ ' . number_format($order['amount'] ?? 0, 2) ?></div>
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
                  <div class="thumb"><?= e(substr($it['product_name'] ?? '', 0, 2)) ?></div>
                  <div class="meta">
                    <div class="title"><?= e($it['product_name'] ?? '') ?></div>
                    <div class="small label">Qty: <?= (int)($it['quantity'] ?? 0) ?> • <?= '₹ '.number_format($it['price'] ?? 0,2) ?></div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <div class="mini">
              <h4>Shipping & Payment</h4>

              <div class="label">
                <strong>Address:</strong>
                <?= e(trim(($address['house_no'] ?? '—') . ' ' . ($address['landmark'] ?? '') . ', ' . ($address['city'] ?? '') . ' - ' . ($address['pincode'] ?? ''))) ?>
              </div>

              <div style="margin-top:8px" class="label">
                <strong>Payment:</strong>
                <?= e($order['payment_method'] ?? '') ?> • <?= e($order['payment_status'] ?? '') ?> • TXN: <?= e($order['transaction_id'] ?? '') ?>
              </div>

              <div style="margin-top:8px" class="label">
                <strong>Shipping:</strong> <?= '₹ '.number_format($order['shipping_amount'] ?? 0,2) ?>
              </div>

              <div style="margin-top:12px">
                <h4>Shipping reference</h4>
                <div class="label">
                  <strong>AWB / Tracking number:</strong>
                  <span id="awb"><?= e($order['awb'] ?? '—') ?></span>
                  <?php if (!empty($order['awb'])): ?>
                    &nbsp; <button class="btn" onclick="copyText('awb')">Copy</button>
                  <?php endif; ?>
                </div>

                <div class="label" style="margin-top:6px">
                  <strong>Shipment status:</strong>
                  <span id="ship_status"><?= e($order['shipment_status'] ?? 'Not available') ?></span>
                </div>

                <div class="actions" style="margin-top:10px">
                  <?php if (!empty($order['awb'])): ?>
                    <button class="btn" onclick="alert('Shipping provider link removed. For shipment updates contact support.')">Shipment info</button>
                  <?php endif; ?>
                  <a class="btn danger" type="submit" href="invoice.php?order_id=<?= (int)$order['id'] ?>">Download invoice</a>
                  <a class="btn" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px">
            <h3>Tracking timeline</h3>
            <div class="timeline">
              <?php if (empty($tracking)): ?>
                <div class="label">No tracking updates recorded for this order.</div>
              <?php else: foreach ($tracking as $t): ?>
                <div class="tl-item">
                  <strong><?= e($t['location']) ?></strong>
                  <small class="label"><?= e($t['updated_at']) ?></small>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <div style="margin-top:16px" class="actions">
  <?php
    $oid = (int) ($order['id'] ?? 0);
  ?>

  <?php if ($oid > 0 && !$return_exists): ?>
    <a href="returns.php?order_id=<?= $oid ?>" class="btn danger">Request return</a>
  <?php elseif ($oid > 0 && $return_exists): ?>
    <div class="label">Return requested — check your Returns page for status.</div>
  <?php else: ?>
    <div class="label">Return not available</div>
  <?php endif; ?>

  <a class="btn primary" href="track_orders.php">Back to orders</a>
</div>

        </div>
      </section>

      <aside class="right">
        <div class="card">
          <h4>Order info</h4>
          <div class="label">Order #: <strong><?= e($order['id']) ?></strong></div>
          <div class="label">Placed: <?= e(date('d M, Y', strtotime($order['created_at'] ?? 'now'))) ?></div>
          <div class="label">Status: <strong><?= e($order['status'] ?? '') ?></strong></div>
          <div style="margin-top:10px" class="actions">
            <a class="btn primary" type="submit" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <script>
  function copyText(id){
    var el = document.getElementById(id);
    if (!el) return;
    var txt = el.innerText || el.textContent;
    if (!txt) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(txt).then(function(){ alert('Copied'); }).catch(function(){ fallbackCopy(txt); });
    } else {
      fallbackCopy(txt);
    }
  }
  function fallbackCopy(text){
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); alert('Copied'); } catch(e) { alert('Copy failed'); }
    document.body.removeChild(ta);
  }
  </script>

  <script src="/public/js/script.js"></script>
</body>
</html>
