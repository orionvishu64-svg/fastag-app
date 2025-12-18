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

function forward_post_to_api($path, array $fields = []) {
    // Build base URL using same host/scheme as request
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $url = $scheme . '://' . $host . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Forward session cookie (PHPSESSID) so API can rely on same session
    if (session_status() === PHP_SESSION_ACTIVE) {
        $sess = session_id();
        if ($sess) curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PHPSESSID={$sess}"]);
    }
    // forward X-Requested-With header (AJAX)
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
        ["X-Requested-With: XMLHttpRequest"],
        // additional headers will be added below if needed
    ));
    // Prepare fields - use urlencoded form data
    $postFields = http_build_query($fields);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    // Set content-type
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded", "X-Requested-With: XMLHttpRequest"]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) {
        error_log("forward_post_to_api: curl error to {$url} : {$err}");
        return ['ok' => false, 'http_code' => 0, 'body' => '', 'error' => $err];
    }
    return ['ok' => true, 'http_code' => (int)$httpCode, 'body' => $raw];
}

/* ---------------- Handle POST submissions to create a return (this file now acts as the UI + fallback API proxy) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept both form-encoded and AJAX JSON multipart submissions (we'll use $_POST primarily)
    $order_id = (int) ($_POST['order_id'] ?? $_REQUEST['order_id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? $_REQUEST['reason'] ?? ''));
    $external_awb = trim((string) ($_POST['external_awb'] ?? $_REQUEST['external_awb'] ?? ''));
    $csrf = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '';

    // If an API file exists, prefer forwarding the request there (non-invasive)
    $api_path = __DIR__ . '/api/create_return.php';
    if (is_file($api_path) && is_readable($api_path)) {
        // Build fields to forward (same names expected)
        $fields = [
            'order_id' => $order_id,
            'reason' => $reason,
            'external_awb' => $external_awb,
            'csrf_token' => $csrf
        ];
        $res = forward_post_to_api('/api/create_return.php', $fields);
        if ($res['ok']) {
            $body = $res['body'];
            $code = $res['http_code'] ?: 200;
            $firstBrace = strpos($body, '{');
            if ($firstBrace !== false) {
                $maybe = substr($body, $firstBrace);
                $decoded = json_decode($maybe, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    http_response_code($code);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($decoded);
                    exit;
                }
            }
            http_response_code($code);
            header('Content-Type: text/plain; charset=utf-8');
            echo $body;
            exit;
        }
        error_log('returns.php: forwarding to api/create_return.php failed, falling back to local handler');
    }

    if ($order_id <= 0) {
        json_exit(['success' => false, 'message' => 'invalid_order_id'], 400);
    }
    if ($reason === '') {
        json_exit(['success' => false, 'message' => 'reason_required'], 400);
    }
    // Validate CSRF
    if (empty($_SESSION['return_csrf_token']) || !hash_equals($_SESSION['return_csrf_token'], (string)$csrf)) {
        json_exit(['success' => false, 'message' => 'invalid_csrf'], 403);
    }

    // Confirm order existence and ownership
    try {
        $os = $pdo->prepare("SELECT id, user_id, status FROM orders WHERE id = :oid LIMIT 1");
        $os->execute([':oid' => $order_id]);
        $ord = $os->fetch(PDO::FETCH_ASSOC);
        if (!$ord || (int)$ord['user_id'] !== $user_id) {
            json_exit(['success' => false, 'message' => 'order_not_found_or_access_denied'], 404);
        }
    } catch (Throwable $e) {
        error_log('returns.php: order lookup failed on POST: ' . $e->getMessage());
        json_exit(['success' => false, 'message' => 'server_error'], 500);
    }

    // Check for an existing return for this order and user
    try {
        $check = $pdo->prepare("SELECT id, status FROM returns WHERE order_id = :oid AND user_id = :uid LIMIT 1");
        $check->execute([':oid' => $order_id, ':uid' => $user_id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            json_exit(['success' => false, 'message' => 'return_already_exists', 'return_id' => $existing['id'], 'status' => $existing['status'] ?? 'requested'], 409);
        }
    } catch (Throwable $e) {
        error_log('returns.php: existing return check failed: ' . $e->getMessage());
        json_exit(['success' => false, 'message' => 'server_error'], 500);
    }

    // Insert the return request
    try {
        $ins = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, external_awb, status, created_at, updated_at) VALUES (:oid, :uid, :reason, :awb, :status, NOW(), NOW())");
        $ins->execute([
            ':oid' => $order_id,
            ':uid' => $user_id,
            ':reason' => $reason,
            ':awb' => $external_awb !== '' ? $external_awb : null,
            ':status' => 'requested'
        ]);
        $return_id = (int)$pdo->lastInsertId();

        // Optionally log the event to order_tracking (local timeline) for visibility
        try {
            $tins = $pdo->prepare("INSERT INTO order_tracking (order_id, location, event, note, event_status, event_source, occurred_at, updated_at) VALUES (:oid, :loc, :evt, :note, :st, :src, NOW(), NOW())");
            $tins->execute([
                ':oid' => $order_id,
                ':loc' => 'Return requested',
                ':evt' => 'Return Requested',
                ':note' => substr("Return ID {$return_id}: " . $reason, 0, 1000),
                ':st' => 'return_requested',
                ':src' => 'system_returns'
            ]);
        } catch (Throwable $_e) {
            // don't fail the return on tracking insert errors; just log
            error_log('returns.php: failed to insert tracking note for return ' . $_e->getMessage());
        }

        // Successful response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_exit(['success' => true, 'message' => 'return_created', 'return_id' => $return_id], 201);
        } else {
            // Non-AJAX: redirect to the same page (GET) to show the success UI
            header('Location: returns.php?order_id=' . urlencode($order_id) . '&created_return=' . urlencode($return_id));
            exit;
        }
    } catch (Throwable $e) {
        error_log('returns.php: failed inserting return: ' . $e->getMessage());
        json_exit(['success' => false, 'message' => 'db_insert_failed'], 500);
    }
}

// Validate order_id on GET
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
    $r = $pdo->prepare("SELECT id, status, created_at, reason, external_awb FROM returns WHERE order_id = :oid AND user_id = :uid LIMIT 1");
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
                  <?php if (!empty($existing_return['external_awb'])): ?>
                    <div class="small">External AWB: <?= e($existing_return['external_awb']) ?></div>
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

      if (!resp.ok) {
        let txt = await resp.text().catch(()=> '');
        throw new Error('HTTP ' + resp.status + (txt ? ': ' + txt : ''));
      }
      const data = await resp.json();
      if (data.success) {
        resultDiv.innerHTML = '<div class="msg success">Return requested successfully. Return ID: ' + escapeHtml(data.return_id || '') + '</div>';
        setTimeout(() => {
          window.location.href = 'order_details.php?order_id=' + encodeURIComponent(ORDER_ID);
        }, 900);
      } else {
        resultDiv.innerHTML = '<div class="msg error">' + escapeHtml(data.message || data.error || 'Failed to create return') + '</div>';
      }
    } catch (err) {
      resultDiv.innerHTML = '<div class="msg error">Error: ' + escapeHtml(err.message) + '</div>';
      console.error('create_return submit error', err);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit return request';
    }
  });
})();
</script>
</body>
</html>