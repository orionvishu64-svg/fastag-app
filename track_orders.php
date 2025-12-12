<?php
// track_orders.php

require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';
if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Fetch user's recent orders
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, user_id, address_id, payment_method, transaction_id, amount,
               shipping_amount, awb, label_url, delhivery_status, manifest_id,
               payment_status, status, created_at, updated_at, expected_delivery_date
        FROM orders
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute([':uid' => $user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("track_orders.php: failed fetching orders for user {$user_id}: " . $ex->getMessage());
    $orders = [];
}

// If a specific order is requested, fetch preview data (items + tracking)
$order_preview = null;
$order_items = [];
$order_tracking = [];
if (!empty($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $oid = (int) $_GET['order_id'];
    try {
        $os = $pdo->prepare("SELECT * FROM orders WHERE id = :oid AND user_id = :uid LIMIT 1");
        $os->execute([':oid' => $oid, ':uid' => $user_id]);
        $order_preview = $os->fetch(PDO::FETCH_ASSOC);

        if ($order_preview) {
            // items (order_items table)
            $it = $pdo->prepare("
                SELECT id, order_id, bank, product_name, quantity, price, product_id
                FROM order_items
                WHERE order_id = :oid
            ");
            $it->execute([':oid' => $oid]);
            $order_items = $it->fetchAll(PDO::FETCH_ASSOC);

            // tracking timeline (order_tracking table)
            $tr = $pdo->prepare("
                SELECT id, order_id, location, event, note, event_status, awb, event_source, payload, latitude, longitude, occurred_at, updated_at
                FROM order_tracking
                WHERE order_id = :oid
                ORDER BY COALESCE(occurred_at, updated_at, '1970-01-01') ASC
                LIMIT 200
            ");
            $tr->execute([':oid' => $oid]);
            $order_tracking = $tr->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("track_orders.php: error loading order {$oid}: " . $e->getMessage());
        $order_preview = null;
        $order_items = [];
        $order_tracking = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Orders — Track</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/track_orders.css">
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <main class="container">
    <header class="topbar">
      <div class="brand">
        <div>
          <h1>My Orders</h1>
          <p class="lead">All orders — shipping & tracking info</p>
        </div>
      </div>
    </header>

    <div class="layout">
      <section class="left">
        <div class="card">
          <h2>Recent Orders</h2>

          <?php if (empty($orders)): ?>
            <div class="no-orders">No orders found for your account.</div>
          <?php else: ?>
            <div class="orders-list">
              <?php foreach ($orders as $o): ?>
                <article class="order-card">
                  <div class="order-meta">
                    <div class="order-id">#<?= e($o['id']) ?> <?= isset($o['transaction_id']) ? '• TXN:' . e($o['transaction_id']) : '' ?></div>
                    <div class="order-date"><?= e(date('d M, Y H:i', strtotime($o['created_at'] ?? 'now'))) ?></div>
                  </div>

                  <div class="order-info">
                    <div class="order-amount"><?= '₹ ' . number_format($o['amount'] ?? 0, 2) ?></div>
                    <div class="order-status"><?= e(ucwords(str_replace('_',' ', $o['status'] ?? ''))) ?></div>
                    <div class="small label">Status: <?= e($o['delhivery_status'] ?? 'N/A') ?></div>
                  </div>

                  <div class="order-actions">
                    <a class="btn small" href="track_orders.php?order_id=<?= (int)$o['id'] ?>">View</a>

                    <?php if (!empty($o['awb'])): ?>
                      <button class="btn small ghost" onclick="alert('External tracking links have been removed. Use the tracking timeline or contact support for updates.')">Track AWB</button>
                      <button class="btn small" onclick="copyToClipboard('awb-<?= (int)$o['id'] ?>')">Copy AWB</button>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($o['awb'])): ?>
                    <div style="display:none" id="awb-<?= (int)$o['id'] ?>"><?= e($o['awb']) ?></div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>

        <?php if ($order_preview): ?>
          <div class="card" style="margin-top:16px">
            <h3>Order #<?= e($order_preview['id']) ?> details</h3>
            <div class="label">Status: <strong><?= e($order_preview['status'] ?? '') ?></strong></div>
            <div class="label">status: <strong><?= e($order_preview['delhivery_status'] ?? 'N/A') ?></strong></div>
            <div class="label">AWB: <strong><?= e($order_preview['awb'] ?? '—') ?></strong></div>

            <div style="margin-top:12px">
              <h4>Items</h4>
              <?php if (empty($order_items)): ?>
                <div class="label">No items recorded.</div>
              <?php else: ?>
                <?php foreach ($order_items as $it): ?>
                  <div class="item">
                    <div class="thumb"><?= e(substr($it['product_name'] ?? '',0,2)) ?></div>
                    <div class="meta">
                      <div class="title"><?= e($it['product_name'] ?? '') ?></div>
                      <small class="label">Qty: <?= (int)($it['quantity'] ?? 0) ?> • <?= '₹ ' . number_format($it['price'] ?? 0,2) ?></small>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div style="margin-top:12px">
              <h4>Tracking timeline</h4>
              <div id="tracking-area" class="timeline">
                <?php if (empty($order_tracking)): ?>
                  <div class="label">No tracking updates yet.</div>
                <?php else: ?>
                  <?php foreach ($order_tracking as $t): ?>
                    <?php
                      $ts = $t['occurred_at'] ?? $t['updated_at'] ?? '';
                      $loc = $t['location'] ?? ($t['event'] ?? '');
                      $note = $t['note'] ?? '';
                    ?>
                    <div class="tl-item">
                      <strong><?= e($loc) ?></strong>
                      <small class="label"><?= e($ts) ?></small>
                      <?php if ($note): ?><div class="small-muted"><?= e($note) ?></div><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div style="margin-top:12px" class="actions">
              <?php if (!empty($order_preview['awb'])): ?>
                <button class="btn" onclick="alert('Shipment details: AWB shown above. External tracking links are removed.')">Shipment info</button>
              <?php endif; ?>
              <a class="btn ghost" href="order_details.php?order_id=<?= (int)$order_preview['id'] ?>">Full details</a>
              <a href="returns.php?order_id=<?= (int)$order_preview['id'] ?>" class="btn danger">Request return</a>
            </div>
          </div>
        <?php endif; ?>

      </section>

      <aside class="right">
        <div class="card">
          <h3>Quick actions</h3>
          <div class="actions">
            <a class="btn ghost" href="contact.php">Contact support</a>
            <a class="btn ghost" href="products.php">Explore More</a>
          </div>
        </div>

        <div class="card" style="margin-top:12px">
          <h4>Shipping info</h4>
          <div class="label">Courier</div>
        </div>
      </aside>
    </div>
  </main>

  <script>
  function copyToClipboard(id){
    var el = document.getElementById(id);
    if (!el) return alert('Nothing to copy');
    var txt = el.innerText || el.textContent || '';
    if (!txt) return alert('Nothing to copy');
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
