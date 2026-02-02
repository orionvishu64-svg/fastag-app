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

?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container py-4">

  <!-- HEADER -->
  <div class="mb-4">
    <h2 class="fw-bold mb-1">Request Return</h2>
    <p class="text-muted">
      Order <strong>#<?= e($order['order_code']) ?></strong> ·
      Status: <strong><?= e(ucfirst($order['status'])) ?></strong>
    </p>
  </div>

  <div class="row g-4">

    <!-- LEFT -->
    <div class="col-lg-8">

      <!-- ORDER SUMMARY -->
      <div class="card mb-4">
        <div class="card-header fw-semibold">Order Summary</div>
        <div class="card-body">

          <div class="d-flex justify-content-between mb-3">
            <div>
              <div class="text-muted small">Placed on</div>
              <div><?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></div>
            </div>
            <div class="text-end">
              <div class="text-muted small">Order Total</div>
              <div class="fs-5 fw-bold text-primary">
                <?= money($order['amount']) ?>
              </div>
            </div>
          </div>

          <hr>

          <!-- ITEMS -->
          <h6 class="fw-semibold mb-3">Items</h6>

          <?php foreach ($items as $it): ?>
            <div class="d-flex align-items-center border-bottom py-2">
              <div class="rounded bg-light d-flex align-items-center justify-content-center me-3"
                   style="width:48px;height:48px;">
                <strong><?= e(substr($it['product_name'], 0, 2)) ?></strong>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= e($it['product_name']) ?></div>
                <div class="small text-muted">
                  Bank: <?= e($it['bank']) ?> · Qty: <?= (int)$it['quantity'] ?>
                </div>
              </div>
              <div class="fw-semibold">
                ₹<?= number_format($it['price'],2) ?>
              </div>
            </div>
          <?php endforeach; ?>

        </div>
      </div>

      <!-- SHIPPING & PAYMENT -->
      <div class="card mb-4">
        <div class="card-header fw-semibold">Shipping & Payment</div>
        <div class="card-body">

          <div class="row mb-2">
            <div class="col-4 text-muted">Address</div>
            <div class="col">
              <?= e(($address['house_no'] ?? '') . ', ' . ($address['city'] ?? '') . ' - ' . ($address['pincode'] ?? '')) ?>
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-4 text-muted">Payment</div>
            <div class="col">
              <?= e($order['payment_method']) ?> (<?= e($order['payment_status']) ?>)
            </div>
          </div>

          <div class="row">
            <div class="col-4 text-muted">Transaction ID</div>
            <div class="col"><?= e($order['transaction_id']) ?></div>
          </div>

        </div>
      </div>

      <!-- TRACKING PREVIEW -->
      <div class="card mb-4">
        <div class="card-header fw-semibold">Tracking Preview</div>
        <div class="card-body">

          <?php if (empty($tracking)): ?>
            <div class="text-muted">No tracking updates available.</div>
          <?php else: ?>
            <?php foreach ($tracking as $t): ?>
              <div class="border-start ps-3 mb-3">
                <div class="fw-semibold"><?= e($t['event_status'] ?? 'Update') ?></div>
                <div class="small text-muted">
                  <?= e($t['location'] ?? '') ?> ·
                  <?= e($t['occurred_at'] ?? $t['updated_at']) ?>
                </div>
                <?php if (!empty($t['note'])): ?>
                  <div class="small text-muted"><?= e($t['note']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>

      <!-- RETURN FORM -->
      <div class="card">
        <div class="card-header fw-semibold">Return Request</div>
        <div class="card-body">

          <?php if ($return_exists): ?>
            <div class="alert alert-success">
              Return already requested.<br>
              <strong>Status:</strong> <?= e($existing_return['status']) ?>
            </div>

            <a href="track_orders.php" class="btn btn-primary">
              Back to Orders
            </a>

          <?php else: ?>
            <form id="returnForm">

              <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

              <div class="mb-3">
                <label class="form-label fw-semibold">
                  Reason for return
                </label>
                <textarea class="form-control"
                          name="reason"
                          rows="4"
                          required
                          placeholder="Explain why you want to return this order"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-danger" id="submitBtn">
                  Submit Return
                </button>
                <a href="order_details.php?order_id=<?= (int)$order['id'] ?>"
                   class="btn btn-outline-secondary">
                  Cancel
                </a>
              </div>

              <div id="result" class="mt-3"></div>
            </form>
          <?php endif; ?>

        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top:90px">
        <div class="card-header fw-semibold">Order Info</div>
        <div class="card-body">
          <p class="mb-1"><strong>Order #</strong> <?= e($order['id']) ?></p>
          <p class="mb-1"><strong>Status:</strong> <?= e($order['status']) ?></p>
          <p class="mb-3"><strong>Placed:</strong> <?= e(date('d M Y', strtotime($order['created_at']))) ?></p>

          <a href="contact.php?order_id=<?= (int)$order['id'] ?>"
             class="btn btn-outline-primary w-100">
            Contact Support
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
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