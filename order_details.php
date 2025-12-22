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

if (empty($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo "Invalid order id.";
    exit;
}
$order_id = (int) $_GET['order_id'];

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

$awb = trim((string)($order['awb'] ?? ''));
$awb_missing = ($awb === '');

$address = [];
try {
    $fb = $pdo->prepare("SELECT house_no, landmark, city, pincode FROM addresses WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $fb->execute([':uid' => $user_id]);
    $address = $fb->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('order_details.php: address load error '.$e->getMessage());
    $address = [];
}

try {
    $itstm = $pdo->prepare("SELECT 
    oi.id AS order_item_id,
    oi.bank,
    oi.product_name,
    oi.quantity,
    oi.price,
    p.image AS product_image
  FROM order_items oi
  LEFT JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id = :oid
  ");
    $itstm->execute([':oid' => $order_id]);
    $items = $itstm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}
// ---- LOAD ALL SERIES FOR ORDER ITEMS ----
$seriesByItem = [];

try {
    $sstm = $pdo->prepare("
        SELECT order_item_id, start_series, end_series
        FROM order_item_series
        WHERE order_id = :oid
        ORDER BY order_item_id ASC, id ASC
    ");
    $sstm->execute([':oid' => $order_id]);
    $seriesRows = $sstm->fetchAll(PDO::FETCH_ASSOC);

    foreach ($seriesRows as $row) {
        $seriesByItem[$row['order_item_id']][] = [
            'start' => $row['start_series'],
            'end'   => $row['end_series']
        ];
    }
} catch (Exception $e) {
    $seriesByItem = [];
}

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

$api_tracking = null;
if (!$awb_missing) {
    $api_tracking = fetch_tracking_via_http($awb, 'awb');
}

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
  <title>Order #<?= e($order['id']) ?> — Details</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/order_details.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container">
  <header class="topbar">
    <div>
      <h1>Order details</h1>
      <p class="label">Order- <?= e($order['id']) ?> — <?= e($order['status'] ?? '') ?></p>
    </div>
  </header>

  <div class="layout">
    <section class="left">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <h2>Order summary</h2>
            <div class="label">
              Placed on <?= e(date('d M, Y H:i', strtotime($order['created_at'] ?? 'now'))) ?>
            </div>
          </div>
          <div>
            <div class="label">Order total</div>
            <div class="big">₹ <?= number_format($order['amount'] ?? 0, 2) ?></div>
          </div>
        </div>

        <div class="items">
          <?php foreach ($items as $it): ?>
            <div class="item">

              <div class="thumb">
                <?php if (!empty($it['product_image'])): ?>
                  <img src="<?= e($it['product_image']) ?>" alt="<?= e($it['product_name']) ?>">
                <?php else: ?>
                <?= e(substr($it['product_name'], 0, 2)) ?>
                <?php endif; ?>
              </div>

      <div class="meta">
        <div class="title"><?= e($it['product_name']) ?></div>

        <div class="small">
          Bank <?= e($it['bank']) ?> ·
          Qty <?= (int)$it['quantity'] ?> ·
          ₹ <?= number_format($it['price'], 2) ?>
        </div>

        <?php if (!empty($seriesByItem[$it['order_item_id']])): ?>
          <div class="series-block">
            <?php foreach ($seriesByItem[$it['order_item_id']] as $s): ?>
              <div class="series-row">
                Series: <?= e($s['start']) ?> – <?= e($s['end']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php endforeach; ?>
</div>
      </div>

      <div class="card">
        <h2>Shipping & Payment</h2>

        <div class="summary-list">
          <div class="row">
            <span>Delivery address</span>
            <span>
              <?= e(($address['house_no'] ?? '').', '.$address['city'].' - '.$address['pincode']) ?>
            </span>
          </div>

          <div class="row">
            <span>Payment</span>
            <span><?= e($order['payment_method']) ?> · <?= e($order['payment_status']) ?></span>
          </div>

          <div class="row">
            <span>Transaction ID</span>
            <span><?= e($order['transaction_id'] ?: '—') ?></span>
          </div>

          <div class="row">
            <span>Shipping charge</span>
            <span>₹ <?= number_format($order['shipping_amount'] ?? 0,2) ?></span>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Shipping reference</h2>

        <div class="summary-list">
          <div class="row">
            <span>AWB / Tracking</span>
            <span>
              <?= $awb ?: '—' ?>
              <?php if ($awb): ?>
                <button class="btn ghost" onclick="copyText('awb')">Copy</button>
              <?php endif; ?>
            </span>
          </div>

          <div class="row">
            <span>Shipment status</span>
            <span><?= e($order['shipment_status'] ?? 'Not available') ?></span>
          </div>

          <div class="row">
            <span><?php if (!$return_exists): ?>
              <a class="btn danger" href="returns.php?order_id=<?= $order_id ?>">Request return</a>
            <?php endif; ?></span>
          </div>
        </div>
      </div>
      <div class="card">
        <h2>Tracking timeline</h2>

        <?php if ($awb_missing): ?>
          <div class="label">
            Tracking will be available once the shipment is created.
          </div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($merged_history as $index => $h): ?>
            <?php $isLatest = ($index === 0); ?>
              <div class="tl-item <?= $isLatest ? 'latest' : '' ?>">
                <div class="tl-dot">
                  <i class="fa-solid <?= $isLatest ? 'fa-truck-fast' : 'fa-circle-check' ?>"></i>
                </div>
                <div class="tl-content">
                  <div class="tl-status"><?= e($h['status']) ?></div>
                  <div class="tl-time"><?= e(date('d M Y, h:i A', strtotime($h['date']))) ?></div>

                  <?php if ($h['location']): ?>
                    <div class="tl-location"><?= e($h['location']) ?></div>
                  <?php endif; ?>

                  <?php if ($h['note']): ?>
                <div class="tl-note"><?= e($h['note']) ?></div>
               <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    
    <aside class="right">
      <div class="card">
        <h2>Quick actions</h2>

        <div class="actions">
          <a class="btn" href="contact.php?order_id=<?= $order_id ?>">Contact support</a>

          <a class="btn primary" href="track_orders.php">Back to orders</a>
        </div>
      </div>
    </aside>
  </div>
</main>
<script>
function copyText(id){
  const el = document.getElementById(id);
  if (!el) return;
  navigator.clipboard.writeText(el.innerText);
  alert('Copied');
}
</script>
  <script src="/public/js/script.js"></script>
</body>
</html>
