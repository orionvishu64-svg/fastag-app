<?php
// returns.php
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('returns.php: $pdo missing');
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'db_connection_error']);
        exit;
    }
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

function json_exit($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_id = (int)($_POST['order_id'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');
    $csrf     = $_POST['csrf_token'] ?? '';

    $genericError = 'Something went wrong. Please try again.';

    if ($order_id <= 0 || $reason === '') {
        json_exit([
            'success' => false,
            'message' => $genericError
        ]);
    }

    if (
        empty($_SESSION['return_csrf_token']) ||
        !hash_equals($_SESSION['return_csrf_token'], $csrf)
    ) {
        json_exit([
            'success' => false,
            'message' => $genericError
        ]);
    }

    $q = $pdo->prepare("
        SELECT id, user_id, status
        FROM orders
        WHERE id = :id
        LIMIT 1
    ");
    $q->execute([':id' => $order_id]);
    $orderRow = $q->fetch(PDO::FETCH_ASSOC);

    if (!$orderRow || (int)$orderRow['user_id'] !== $user_id) {
        json_exit([
            'success' => false,
            'message' => 'Unable to process return for this order.'
        ]);
    }

    $allowedStatuses = ['delivered', 'completed'];
    if (!in_array(strtolower($orderRow['status']), $allowedStatuses, true)) {
        json_exit([
            'success' => false,
            'message' => 'Return is available only after delivery.'
        ]);
    }

    $chk = $pdo->prepare("
        SELECT id FROM returns
        WHERE order_id = :oid
        AND status IN ('requested','approved')
        LIMIT 1
    ");
    $chk->execute([':oid' => $order_id]);
    if ($chk->fetch()) {
        json_exit([
            'success' => false,
            'message' => 'You have already requested a return for this order.'
        ]);
    }

    try {
        $ins = $pdo->prepare("
            INSERT INTO returns (order_id, user_id, reason, status, created_at, updated_at)
            VALUES (:oid, :uid, :reason, 'requested', NOW(), NOW())
        ");
        $ins->execute([
            ':oid'    => $order_id,
            ':uid'    => $user_id,
            ':reason' => $reason
        ]);
    } catch (Throwable $e) {
        error_log('return insert failed: '.$e->getMessage());
        json_exit([
            'success' => false,
            'message' => $genericError
        ], 500);
    }

    json_exit([
        'success'   => true,
        'return_id'=> $pdo->lastInsertId(),
        'message'  => 'Return request submitted successfully.'
    ]);
}

if (empty($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo "Invalid order id.";
    exit;
}
$order_id = (int) $_GET['order_id'];

// load order and confirm ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :oid LIMIT 1");
    $stmt->execute([':oid' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || (int)$order['user_id'] !== $user_id) {
        http_response_code(404);
        echo "Order not found or access denied.";
        exit;
    }
} catch (Throwable $e) {
    error_log("returns.php: order lookup failed: " . $e->getMessage());
    http_response_code(500);
    echo "Server error.";
    exit;
}

// load a recent address to display (not critical)
$address = [];
try {
    $a = $pdo->prepare("SELECT house_no, landmark, city, pincode FROM addresses WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $a->execute([':uid' => $user_id]);
    $address = $a->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $address = [];
}

// items
$items = [];
try {
    $it = $pdo->prepare("SELECT id, order_id, bank, product_name, quantity, price, product_id FROM order_items WHERE order_id = :oid");
    $it->execute([':oid' => $order_id]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $items = [];
}

// tracking preview
$tracking = [];
try {
    $tr = $pdo->prepare("SELECT id, order_id, location, event_status, note, updated_at, occurred_at FROM order_tracking WHERE order_id = :oid ORDER BY COALESCE(occurred_at, updated_at) ASC");
    $tr->execute([':oid' => $order_id]);
    $tracking = $tr->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tracking = [];
}

// existing return check
$return_exists = false;
$existing_return = null;
try {
    $r = $pdo->prepare("SELECT id, status, created_at, reason FROM returns WHERE order_id = :oid AND user_id = :uid LIMIT 1");
    $r->execute([':oid' => $order_id, ':uid' => $user_id]);
    $existing_return = $r->fetch(PDO::FETCH_ASSOC) ?: null;
    $return_exists = (bool)$existing_return;
} catch (Throwable $e) {
    $return_exists = false;
    $existing_return = null;
}

// CSRF token for return form
if (empty($_SESSION['return_csrf_token'])) {
    $_SESSION['return_csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['return_csrf_token'];

function money($val) { return '₹ ' . number_format((float)$val, 2); }

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Request Return — Order #<?= e($order['id']) ?></title>
  <link rel="stylesheet" href="/public/css/styles.css" />
  <link rel="stylesheet" href="/public/css/order_details.css" />
  <link rel="stylesheet" href="/public/css/returns.css" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <main class="container">
    <header class="topbar">
      <div class="brand">
        <div>
          <h1>Request Return</h1>
          <p class="lead">Order- <?= e($order['order_code']) ?> — <?= e($order['status'] ?? '') ?></p>
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
              <div class="label">Placed on <?= e(date('d M, Y H:i', strtotime($order['created_at'] ?? 'now'))) ?></div>
            </div>
            <div style="text-align:right">
              <div class="label">Order total</div>
              <div class="big"><?= money($order['amount'] ?? $order['total'] ?? 0) ?></div>
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
                    <div class="small label">Bank <?= ($it['bank']) ?> - Qty: <?= (int)($it['quantity'] ?? 0) ?> • <?= '₹ '.number_format($it['price'] ?? 0,2) ?></div>
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
                <strong>Shipping:</strong> <?= money($order['shipping_amount'] ?? 0) ?>
              </div>

              <div style="margin-top:12px">
                <h4>Shipping reference</h4>
                <div class="label">AWB: <strong id="awb"><?= e($order['awb'] ?? '—') ?></strong></div>
                <div class="label">Shipment status: <strong><?= e($order['shipment_status'] ?? $order['delhivery_status'] ?? '—') ?></strong></div>
                <div class="actions" style="margin-top:10px">
                  <?php if (!empty($order['awb'])): ?>
                    <button class="btn" onclick="alert('External tracking links have been removed. Use the tracking timeline or contact support for updates.')">Shipment info</button>
                    <button class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($order['awb']) ?>').then(()=>alert('AWB copied'))">Copy AWB</button>
                  <?php endif; ?>
                  <a class="btn secondary" href="invoice.php?order_id=<?= (int)$order['id'] ?>">Download invoice</a>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px">
            <h3>Tracking timeline</h3>
            <div id="tracking-area" class="timeline">
              <?php if (empty($tracking)): ?>
                <div class="label">No tracking updates found.</div>
              <?php else: foreach ($tracking as $t): ?>
                <div class="tl-item">
                  <strong><?= e($t['location'] ?? ($t['event'] ?? '')) ?></strong>
                  <small class="label"><?= e($t['occurred_at'] ?? $t['updated_at'] ?? '') ?></small>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <div style="margin-top:16px" class="return-form">
            <?php if ($return_exists): ?>
              <div class="msg success">
                A return request already exists for this order.
                <?php if (!empty($existing_return['id'])): ?>
                  <div>Return ID: <?= e($existing_return['id']) ?> • Status: <?= e($existing_return['status'] ?? 'requested') ?></div>
                  <div class="small">Requested at: <?= e($existing_return['created_at'] ?? '') ?></div>
                  <?php if (!empty($existing_return['reason'])): ?>
                    <div class="small">Reason: <?= e($existing_return['reason']) ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <div style="margin-top:8px">
                <a class="btn" href="track_orders.php">Back to orders</a>
                <a class="btn secondary" href="order_details.php?order_id=<?= (int)$order['id'] ?>">Go to order</a>
              </div>

            <?php else: ?>
              <form id="returnForm">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="field">
                  <label for="reason">Reason for return <span class="small muted">(required)</span></label>
                  <textarea id="reason" name="reason" required maxlength="1000" placeholder="Describe why you want to return this order"></textarea>
                </div>

                <div class="actions">
                  <button type="submit" class="btn" id="submitBtn">Submit return request</button>
                  <a href="order_details.php?order_id=<?= (int)$order['id'] ?>" class="btn secondary">Cancel</a>
                </div>

                <div id="result" style="margin-top:12px"></div>
              </form>
            <?php endif; ?>
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
            <a class="btn" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
          </div>
        </div>
      </aside>
    </div>
  </main>

<script src="/public/js/script.js"></script>
<script>
(function(){
  const form = document.getElementById('returnForm');
  if (!form) return;

  const submitBtn = document.getElementById('submitBtn');
  const resultDiv = document.getElementById('result');
  const ORDER_ID = <?= json_encode((int)$order['id']) ?>;

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    resultDiv.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    const fd = new FormData(form);

    try {
      const resp = await fetch('returns.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      let data;
      try {
        data = await resp.json();
      } catch {
        throw new Error('Something went wrong. Please try again.');
      }
      if (data.success) {
        resultDiv.innerHTML =
          '<div class="msg success">' +
          escapeHtml(data.message || 'Return request submitted successfully.') +
          '</div>';

        setTimeout(() => {
          window.location.href =
            'order_details.php?order_id=' + encodeURIComponent(ORDER_ID);
        }, 900);
      } else {
        resultDiv.innerHTML =
          '<div class="msg error">' +
          escapeHtml(data.message || 'Return is available only after delivery.') +
          '</div>';
      }

    } catch (err) {
      resultDiv.innerHTML =
        '<div class="msg error">' +
        'Something went wrong. Please try again later.' +
        '</div>';
      console.error('return submit error', err);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit return request';
    }
  });
})();
</script>
</body>
</html>