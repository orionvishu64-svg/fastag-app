<?php
// order_details.php
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fetch_tracking_via_http($query, $paramName = 'awb') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $base = $scheme . '://' . $host;

    $q = rawurlencode((string)$query);
    $url = $base . '/api/tracking.php?' . $paramName . '=' . $q;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $sess = session_id();
        if ($sess) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PHPSESSID={$sess}"]);
        }
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlErr) {
        error_log("fetch_tracking_via_http: curl error: {$curlErr}");
        return null;
    }

    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }

    $firstBrace = strpos($raw, '{');
    if ($firstBrace !== false) {
        $maybe = substr($raw, $firstBrace);
        $json2 = json_decode($maybe, true);
        if (json_last_error() === JSON_ERROR_NONE) return $json2;
    }

    error_log("fetch_tracking_via_http: invalid JSON from {$url}; http_code={$httpCode}; raw_len=" . strlen($raw));
    return null;
}

/* ---------------- Validate input ---------------- */
if (empty($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo "Invalid order id.";
    exit;
}
$order_id = (int) $_GET['order_id'];

/* ---------------- Load order ---------------- */
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :oid AND user_id = :uid LIMIT 1");
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

/* ---------------- If AWB missing: return 400 per your requirement ---------------- */
$awb = trim((string)($order['awb'] ?? ''));
if ($awb === '') {
    http_response_code(400);
    echo "Missing AWB (tracking number) for order id {$order_id}.";
    exit;
}

/* ---------------- Load address ---------------- */
$address = [];
try {
    $fb = $pdo->prepare("SELECT house_no, landmark, city, pincode FROM addresses WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $fb->execute([':uid' => $user_id]);
    $address = $fb->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('order_details.php: address load error '.$e->getMessage());
    $address = [];
}

/* ---------------- Load items ---------------- */
try {
    $itstm = $pdo->prepare("SELECT id, order_id, bank, product_name, quantity, price, product_id FROM order_items WHERE order_id = :oid");
    $itstm->execute([':oid' => $order_id]);
    $items = $itstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}

/* ---------------- Load DB tracking rows ---------------- */
try {
    $tstm = $pdo->prepare("
        SELECT id, order_id, location, event, note, event_status, awb, event_source, payload, latitude, longitude, occurred_at, updated_at
        FROM order_tracking
        WHERE order_id = :oid
        ORDER BY COALESCE(occurred_at, updated_at, '1970-01-01') ASC
    ");
    $tstm->execute([':oid' => $order_id]);
    $tracking = $tstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracking = [];
}

$return_exists = false;
try {
    $rstm = $pdo->prepare("SELECT id FROM returns WHERE order_id = :oid AND user_id = :uid LIMIT 1");
    $rstm->execute([':oid' => $order_id, ':uid' => $user_id]);
    $return_exists = (bool)$rstm->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $return_exists = false;
}

$api_tracking = fetch_tracking_via_http($awb, 'awb');

$api_history = [];
if (is_array($api_tracking) && !empty($api_tracking['success']) && !empty($api_tracking['tracking'])) {
    $trk = $api_tracking['tracking'];
    if (!empty($trk['history']) && is_array($trk['history'])) {
        foreach ($trk['history'] as $h) {
            $date = $h['date'] ?? $h['occurred_at'] ?? $h['occured_at'] ?? $h['time'] ?? ($h['updated_at'] ?? null);
            $status = $h['status'] ?? $h['event'] ?? $h['event_status'] ?? '';
            $location = $h['location'] ?? ($h['place'] ?? '');
            $note = $h['note'] ?? $h['remark'] ?? ($h['description'] ?? '');
            $api_history[] = ['date' => $date, 'status' => $status, 'location' => $location, 'note' => $note];
        }
    } elseif (!empty($api_tracking['rows']) && is_array($api_tracking['rows'])) {
        foreach ($api_tracking['rows'] as $r) {
            $api_history[] = [
                'date' => $r['occurred_at'] ?? $r['updated_at'] ?? null,
                'status' => $r['event_status'] ?? $r['event'] ?? '',
                'location' => $r['location'] ?? '',
                'note' => $r['note'] ?? ''
            ];
        }
    }
}

$db_history = [];
if (is_array($tracking) && count($tracking)) {
    foreach ($tracking as $r) {
        $db_history[] = [
            'date' => $r['occurred_at'] ?? $r['updated_at'] ?? null,
            'status' => $r['event_status'] ?? $r['event'] ?? '',
            'location' => $r['location'] ?? '',
            'note' => $r['note'] ?? ''
        ];
    }
}

usort($api_history, function($a,$b){ $ta = empty($a['date'])?0:strtotime($a['date']); $tb = empty($b['date'])?0:strtotime($b['date']); return $tb <=> $ta; });
usort($db_history, function($a,$b){ $ta = empty($a['date'])?0:strtotime($a['date']); $tb = empty($b['date'])?0:strtotime($b['date']); return $tb <=> $ta; });

$merged_history = $api_history;
foreach ($db_history as $dbh) {
    $dup = false;
    foreach ($merged_history as $mh) {
        if (!empty($mh['date']) && !empty($dbh['date']) && (strtotime($mh['date']) == strtotime($dbh['date'])) && trim($mh['status']) === trim($dbh['status'])) {
            $dup = true; break;
        }
    }
    if (!$dup) $merged_history[] = $dbh;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Order #<?= e($order['id']) ?> — Details & Tracking</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/order_details.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .timeline { margin-top:10px; border-left:2px solid #e6e6e6; padding-left:14px; }
    .tl-item { margin-bottom:12px; position:relative; }
    .tl-item strong { display:block; }
    .tl-item .label { color:#666; font-size:0.9rem; }
    .raw-json { background:#f7f7f7; padding:10px; border-radius:6px; white-space:pre-wrap; font-family:monospace; }
  </style>
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
                  &nbsp; <button class="btn" onclick="copyText('awb')">Copy</button>
                </div>

                <div class="label" style="margin-top:6px">
                  <strong>Shipment status:</strong>
                  <span id="ship_status"><?= e($order['shipment_status'] ?? 'Not available') ?></span>
                </div>

                <div class="actions" style="margin-top:10px">
                  <button class="btn" onclick="alert('Shipping provider link removed. For shipment updates contact support.')">Shipment info</button>
                  <a class="btn danger" type="submit" href="invoice.php?order_id=<?= (int)$order['id'] ?>">Download invoice</a>
                  <a class="btn" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-top:16px">
            <h3>Tracking timeline</h3>
            <div class="timeline">
              <?php
                if (empty($merged_history)) {
                    echo '<div class="label">No tracking updates recorded for this order.</div>';
                } else {
                    foreach ($merged_history as $h) {
                        $ds = $h['date'] ?? '';
                        $status = $h['status'] ?? '';
                        $loc = $h['location'] ?? '';
                        $note = $h['note'] ?? '';
                        ?>
                        <div class="tl-item">
                          <strong><?= e($status ?: $loc ?: 'Update') ?></strong>
                          <small class="label"><?= e($ds) ?></small>
                          <?php if ($loc): ?><div class="small-muted"><?= e($loc) ?></div><?php endif; ?>
                          <?php if ($note): ?><div class="small-muted"><?= e($note) ?></div><?php endif; ?>
                        </div>
                        <?php
                    }
                }
              ?>
            </div>
          </div>

          <div style="margin-top:16px" class="actions">
            <?php $oid = (int) ($order['id'] ?? 0); ?>
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
            <a class="btn primary" href="contact.php?order_id=<?= (int)$order['id'] ?>">Contact support</a>
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
