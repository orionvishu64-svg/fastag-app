<?php
// returns.php - corrected drop-in for your project
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
// here my order fetch and populated here
if (empty($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo "Invalid order id.";
    exit;
}
$order_id = (int) $_GET['order_id'];

// Ensure $pdo exists
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('returns.php: $pdo not found from config/db.php');
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

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
    error_log('returns.php: order load error '.$ex->getMessage());
    $order = false;
}

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

// 2) Load address (fallback to most recent)
$address = [];
try {
    $ab = $pdo->prepare("
        SELECT house_no, landmark, city, pincode
        FROM addresses
        WHERE user_id = :uid
        ORDER BY id DESC
        LIMIT 1
    ");
    $ab->execute([':uid' => $user_id]);
    $address = $ab->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('returns.php: address load error '.$e->getMessage());
    $address = [];
}

// 3) Load order items
try {
    $itstm = $pdo->prepare("
        SELECT id, order_id, bank, product_name, quantity, price, product_id
        FROM order_items
        WHERE order_id = :oid
    ");
    $itstm->execute([':oid' => $order_id]);
    $items = $itstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('returns.php: items load error '.$e->getMessage());
    $items = [];
}

// 4) Load tracking
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
    error_log('returns.php: tracking load error '.$e->getMessage());
    $tracking = [];
}

// 5) Check existing return
$return_exists = false;
$existing_return = null;
try {
    $rstm = $pdo->prepare("
        SELECT id, status, created_at, reason
        FROM returns
        WHERE order_id = :oid AND user_id = :uid
        LIMIT 1
    ");
    $rstm->execute([':oid' => $order_id, ':uid' => $user_id]);
    $existing_return = $rstm->fetch(PDO::FETCH_ASSOC);
    $return_exists = (bool)$existing_return;
} catch (Exception $e) {
    error_log('returns.php: returns check error '.$e->getMessage());
    $return_exists = false;
    $existing_return = null;
}

// CSRF token (simple)
if (empty($_SESSION['return_csrf_token'])) {
    $_SESSION['return_csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['return_csrf_token'];

function money($val) {
    return '₹ ' . number_format((float)$val, 2);
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Request Return — Order #<?= e($order['id']) ?></title>
  <link rel="stylesheet" href="public/css/order_details.css" />
  <style>
    /* page-specific — dark warm theme */
.return-form { margin-top: 12px; }

/* field layout */
.field { margin-bottom: 10px; }
.field label {
  display: block;
  font-size: 13px;
  margin-bottom: 6px;
  color: var(--muted);
}

/* inputs and textarea */
.field textarea,
.field input[type="text"],
.field input,
.field select {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;

  /* dark input surface */
  background: linear-gradient(180deg, var(--panel-dark), var(--panel-2));
  color: var(--text);
  border: 1px solid rgba(255,255,255,0.04);
  transition: border-color .16s ease, box-shadow .16s ease, transform .12s ease;
}

/* placeholder color */
.field input::placeholder,
.field textarea::placeholder {
  color: rgba(243,243,243,0.28);
}

/* focus */
.field textarea:focus,
.field input[type="text"]:focus,
.field input:focus,
.field select:focus {
  outline: none;
  border-color: var(--warm-yellow);
  box-shadow: 0 0 0 6px rgba(255,184,77,0.04);
  transform: translateY(-1px);
}

/* textarea sizing */
.field textarea {
  min-height: 100px;
  resize: vertical;
}

/* Buttons (primary / secondary) */
.btn {
  display: inline-block;
  padding: 8px 12px;
  border-radius: 8px;
  background: linear-gradient(90deg, var(--warm-yellow), var(--warm-yellow-2));
  color: #111;
  text-decoration: none;
  border: 0;
  cursor: pointer;
  font-weight: 700;
  box-shadow: 0 8px 22px rgba(255,184,77,0.06);
  transition: transform .12s ease, box-shadow .18s ease, filter .12s ease;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 40px rgba(232,92,65,0.12);
}

/* secondary variant */
.btn.secondary {
  background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
  color: var(--text);
  border: 1px solid rgba(255,255,255,0.04);
  box-shadow: 0 8px 18px rgba(0,0,0,0.45);
}
.btn.secondary:hover {
  background: linear-gradient(90deg, var(--warm-yellow), var(--warm-yellow-2));
  color: #111;
  transform: translateY(-2px);
  box-shadow: 0 14px 40px rgba(255,184,77,0.12);
}

/* small/compact */
.btn.small { padding:6px 10px; font-size:.95rem; }

/* messages / feedback */
.msg {
  margin-top: 12px;
  padding: 10px;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.25;
  box-sizing: border-box;
  border: 1px solid rgba(255,255,255,0.02);
}

/* success (dark-friendly) */
.msg.success {
  background: linear-gradient(180deg, rgba(16,63,40,0.28), rgba(9,52,33,0.22));
  color: #bbf7d0;           /* bright text */
  border-color: rgba(11,99,232,0.04);
  box-shadow: 0 8px 20px rgba(11,99,232,0.02);
}

/* error (dark-friendly) */
.msg.error {
  background: linear-gradient(180deg, rgba(94,7,7,0.16), rgba(44,3,3,0.12));
  color: #fca5a5;           /* readable on dark */
  border-color: rgba(232,92,65,0.06);
  box-shadow: 0 8px 20px rgba(232,92,65,0.03);
}

/* subtle text colors inside messages */
.msg strong { color: var(--text); font-weight:800; }

/* accessibility: focus outline for actionable controls inside forms */
.return-form :where(button, .btn, input, select, textarea):focus {
  outline: 3px solid rgba(255,184,77,0.08);
  outline-offset: 2px;
}

/* responsive tweaks for narrow screens */
@media (max-width: 480px) {
  .field label { font-size: 12px; }
  .btn { width:100%; text-align:center; }
}
  </style>
</head>
<body>
  <main class="container">
    <header class="topbar">
      <div class="brand">
        <div class="logo">APS</div>
        <div>
          <h1>Request Return</h1>
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
                <strong>Shipping:</strong> <?= money($order['shipping_amount'] ?? 0) ?>
              </div>

              <div style="margin-top:12px">
                <h4>Courier & AWB</h4>
                <div class="label">AWB: <strong id="awb"><?= e($order['awb'] ?? '—') ?></strong></div>
                <div class="label">Delhivery status: <strong><?= e($order['delhivery_status'] ?? '—') ?></strong></div>
                <div class="actions" style="margin-top:10px">
                  <?php if (!empty($order['awb'])): ?>
                    <button class="btn" onclick="window.open('https://www.delhivery.com/track?waybill=<?= e($order['awb']) ?>','_blank')">Open Delhivery</button>
                    <button class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($order['awb']) ?>').then(()=>alert('AWB copied'))">Copy AWB</button>
                  <?php endif; ?>
                  <a class="btn secondary" href="invoice.php?order_id=<?= (int)$order['id'] ?>">Download invoice</a>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px">
            <h3>Tracking timeline</h3>
            <div class="timeline">
              <?php if (!empty($order['delhivery_status'])): ?>
                <div class="tl-item done">
                  <strong>Delhivery status: <?= e($order['delhivery_status']) ?></strong>
                  <small class="label"><?= e($order['updated_at'] ?? '') ?></small>
                </div>
              <?php endif; ?>

              <?php if (empty($tracking)): ?>
                <div class="label">No tracking updates found.</div>
              <?php else: foreach ($tracking as $t): ?>
                <div class="tl-item">
                  <strong><?= e($t['location']) ?></strong>
                  <small class="label"><?= e($t['updated_at']) ?></small>
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
                  <textarea id="reason" name="reason" required placeholder="Describe why you want to return this order"></textarea>
                </div>

                <div class="field">
                  <label for="external_awb">External AWB / Tracking (optional)</label>
                  <input type="text" id="external_awb" name="external_awb" placeholder="Courier AWB / tracking number">
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

<script>
(function(){
  const form = document.getElementById('returnForm');
  if (!form) return;

  const submitBtn = document.getElementById('submitBtn');
  const resultDiv = document.getElementById('result');

  // safely pass order id to JS via JSON encoding to avoid syntax issues
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
      const resp = await fetch('/api/create_return.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });

      if (!resp.ok) {
        let txt = await resp.text().catch(()=> '');
        throw new Error('HTTP ' + resp.status + (txt ? ': ' + txt : ''));
      }

      const data = await resp.json();
      if (data.success) {
        resultDiv.innerHTML = '<div class="msg success">Return requested successfully. Return ID: ' + escapeHtml(data.return_id || '') + '</div>';
        setTimeout(() => {
          // redirect back to order details
          window.location.href = 'order_details.php?order_id=' + encodeURIComponent(ORDER_ID);
        }, 1200);
      } else {
        resultDiv.innerHTML = '<div class="msg error">' + escapeHtml(data.message || 'Failed to create return') + '</div>';
      }
    } catch (err) {
      resultDiv.innerHTML = '<div class="msg error">Error: ' + escapeHtml(err.message) + '</div>';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit return request';
    }
  });
})();
</script>
</body>
</html>
