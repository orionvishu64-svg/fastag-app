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
<style>
body { background:#f5f7fb; }
.order-hero { background:#fff; border-radius:14px; }
.timeline-dot {
  width:12px;
  height:12px;
  border-radius:50%;
  background:#0d6efd;
}
.timeline-line {
  width:2px;
  background:#dee2e6;
}
.btn-invoice {
  color: #f59e0b !important;
  border: 2px solid #f59e0b !important;
  background-color: transparent !important;
  font-weight: 600;
  transition: all 0.2s ease-in-out;
}
.btn-invoice:hover,
.btn-invoice:focus {
  background-color: #f59e0b !important;
  color: #111 !important;
  border-color: #f59e0b !important;
  box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.35);
}
.btn-invoice:active {
  background-color: #d97706 !important;
  border-color: #d97706 !important;
  color: #111 !important;
}
.btn-invoice i {
  color: inherit !important;
}
.btn-invoice:hover {
  transform: translateY(-1px);
}
</style>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container py-4">

  <!-- ORDER HEADER -->
  <div class="order-hero p-4 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h4 class="mb-1">Order #<?= e($order['order_code'] ?? $order['id']) ?></h4>
        <small class="text-muted">
          Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
        </small>
      </div>
      <div class="text-end">
        <div class="fw-bold fs-5 text-primary">₹ <?= number_format($order['amount'],2) ?></div>
        <span class="badge bg-success"><?= ucfirst($order['payment_status']) ?></span>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- LEFT -->
    <div class="col-lg-8">

      <!-- ITEMS -->
      <div class="card mb-4">
        <div class="card-header fw-semibold">Order Items</div>
        <div class="card-body">
          <?php foreach ($items as $it): ?>
            <div class="d-flex gap-3 align-items-center border-bottom pb-3 mb-3">
              <div class="bg-light rounded d-flex align-items-center justify-content-center"
                   style="width:60px;height:60px">
                <?php if($it['product_image']): ?>
                  <img src="<?= e($it['product_image']) ?>" class="img-fluid rounded">
                <?php else: ?>
                  <strong><?= e(substr($it['product_name'],0,2)) ?></strong>
                <?php endif; ?>
              </div>

              <div class="flex-grow-1">
                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                <small class="text-muted">
                  Bank: <?= e($it['bank']) ?> · Qty: <?= (int)$it['quantity'] ?>
                </small>

                <?php if (!empty($seriesByItem[$it['order_item_id']])): ?>
                  <div class="mt-1">
                    <?php foreach ($seriesByItem[$it['order_item_id']] as $s): ?>
                      <small class="text-muted d-block">
                        Series: <?= e($s['start']) ?> – <?= e($s['end']) ?>
                      </small>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="fw-bold">
                ₹ <?= number_format($it['price'],2) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- SHIPPING + PAYMENT -->
      <div class="card mb-4">
        <div class="card-header fw-semibold">Shipping & Payment</div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-5 text-muted">Address</div>
            <div class="col">
              <?= e(($address['house_no'] ?? '') . ', ' . ($address['city'] ?? '') . ' - ' . ($address['pincode'] ?? '')) ?>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-5 text-muted">Payment</div>
            <div class="col"><?= e($order['payment_method']) ?> (<?= e($order['payment_status']) ?>)</div>
          </div>
          <div class="row">
            <div class="col-5 text-muted">Transaction ID</div>
            <div class="col"><?= e($order['transaction_id'] ?: '—') ?></div>
          </div>
        </div>
      </div>

      <!-- TRACKING -->
      <div class="card">
        <div class="card-header fw-semibold">Tracking Timeline</div>
        <div class="card-body">
          <?php if ($awb_missing): ?>
            <div class="text-muted">Tracking will be available once shipment is created.</div>
          <?php else: ?>
            <?php foreach ($merged_history as $h): ?>
              <div class="d-flex gap-3 mb-3">
                <div class="timeline-dot mt-2"></div>
                <div>
                  <div class="fw-semibold"><?= e($h['status']) ?></div>
                  <small class="text-muted">
                    <?= date('d M Y, h:i A', strtotime($h['date'])) ?>
                    <?= $h['location'] ? ' · '.$h['location'] : '' ?>
                  </small>
                  <?php if($h['note']): ?>
                    <div class="small text-muted"><?= e($h['note']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top:90px">
        <div class="card-header fw-semibold">Actions</div>
        <div class="card-body d-grid gap-2">

  <!-- Invoice -->
<a href="invoice.php?order_id=<?= $order_id ?>"
   target="_blank"
   class="btn btn-invoice d-flex align-items-center justify-content-center gap-2">
  <i class="fa-solid fa-file-invoice"></i>
  View Invoice
</a>

  <!-- Support -->
  <a href="contact.php?order_id=<?= $order_id ?>"
     class="btn btn-outline-primary d-flex align-items-center justify-content-center gap-2">
    <i class="fa-solid fa-headset"></i>
    Contact Support
  </a>

  <?php if(!$return_exists): ?>
    <a href="returns.php?order_id=<?= $order_id ?>"
       class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2">
      <i class="fa-solid fa-rotate-left"></i>
      Request Return
    </a>
  <?php endif; ?>

  <a href="track_orders.php"
     class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
    <i class="fa-solid fa-arrow-left"></i>
    Back to Orders
  </a>

</div>
      </div>
    </div>
  </div>
</div>
<script>
function copyText(id){
  const el = document.getElementById(id);
  if (!el) return;
  navigator.clipboard.writeText(el.innerText);
  alert('Copied');
}
</script>