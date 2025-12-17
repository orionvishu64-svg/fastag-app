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
    error_log('track_orders.php: failed fetching orders for user '.$user_id.': '.$ex->getMessage());
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
          <p class="lead">All orders - shipping & tracking info</p>
        </div>
      </div>
    </header>

    <div class="layout">
      <section class="left">
        <div class="card">
          <h2>Recent Orders</h2>

          <?php if (empty($orders)): ?>
            <div class="no-orders">No orders found.</div>
          <?php else: ?>
            <div class="orders-list">
              <?php foreach ($orders as $o): ?>
                <article class="order-card">
                  <div class="order-meta">
                    <div class="order-id">
                      #<?= e($o['id']) ?>
                      <?= isset($o['transaction_id']) ? ' • TXN:' . e($o['transaction_id']) : '' ?>
                    </div>
                    <div class="order-date">
                        <?= e(date('d M, Y H:i', strtotime($o['created_at'] ?? 'now'))) ?>
                    </div>
                  </div>

                  <div class="order-info">
                    <div class="order-amount">₹ <?= number_format($o['amount'] ?? 0, 2) ?></div>
                    <div class="order-status"><?= e(ucwords(str_replace('_', ' ', $o['status'] ?? ''))) ?></div>
                    <div class="small label">Status: <?= e($o['delhivery_status'] ?? 'N/A') ?></div>
                  </div>

                  <div class="order-actions">
                    <a class="btn small" href="order_details.php?order_id=<?= (int)$o['id'] ?>">View</a>

                    <?php if (!empty($o['awb'])): ?>
                      <button class="btn small ghost"
                        onclick="alert('External tracking is disabled. View full details for more info.')">
                        Track AWB
                      </button>

                      <button class="btn small" onclick="copyToClipboard('awb-<?= (int)$o['id'] ?>')">
                        Copy AWB
                      </button>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($o['awb'])): ?>
                    <div id="awb-<?= (int)$o['id'] ?>" style="display:none;"><?= e($o['awb']) ?></div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </section>

      <aside class="right">
        <div class="card">
          <h3>Quick Actions</h3>
          <div class="actions">
            <a class="btn ghost" href="contact.php">Contact support</a>
            <a class="btn ghost" href="products.php">Explore products</a>
          </div>
        </div>

        <div class="card" style="margin-top:12px;">
          <h4>Shipping Info</h4>
          <div class="label">Courier details available in order page</div>
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
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(txt).then(() => alert('Copied'));
  } else {
    var ta = document.createElement('textarea');
    ta.value = txt;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    alert('Copied');
  }
}
</script>

<script src="/public/js/script.js"></script>
</body>
</html>
