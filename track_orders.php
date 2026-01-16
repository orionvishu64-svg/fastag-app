<?php
// track_orders.php
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}

$user_id = (int) $_SESSION['user']['id'];

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ===== SEARCH ===== */
$search = trim($_GET['q'] ?? '');

$params = [
    ':uid' => $user_id
];

$sql = "
  SELECT id, user_id, order_code, address_id, payment_method, transaction_id,
         amount, shipping_amount, awb, label_url, delhivery_status,
         manifest_id, payment_status, status, created_at, updated_at,
         expected_delivery_date
  FROM orders
  WHERE user_id = :uid
    AND payment_status = 'paid'
";

if ($search !== '') {
    $sql .= " AND order_code LIKE :search ";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY created_at DESC LIMIT 200";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log('track_orders.php error: '.$ex->getMessage());
    $orders = [];
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
body { background:#f5f7fb; }

.orders-page {
  background:#fff;
  border-radius:14px;
  padding:28px;
  box-shadow:0 10px 30px rgba(0,0,0,.06);
}

.page-title {
  font-size:28px;
  font-weight:700;
  margin-bottom:4px;
}

.page-subtitle {
  color:#6c757d;
  font-size:14px;
}

.search-box {
  margin-top:16px;
  max-width:420px;
}

.search-box input {
  border-radius:10px;
  padding:10px 14px;
}

.layout {
  display:grid;
  grid-template-columns:2.2fr 1fr;
  gap:24px;
  margin-top:24px;
}

@media(max-width:768px){
  .layout { grid-template-columns:1fr; }
}

.order-card {
  background:#fff;
  border:1px solid #e9ecef;
  border-radius:12px;
  padding:16px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  margin-bottom:14px;
  transition:.2s;
}

.order-card:hover {
  box-shadow:0 8px 20px rgba(0,0,0,.08);
  transform:translateY(-2px);
}

.order-meta { flex:2; }
.order-id { font-weight:600; font-size:15px; }
.order-date { font-size:13px; color:#6c757d; }

.order-info {
  flex:2;
  display:flex;
  flex-direction:column;
  gap:4px;
}

.order-amount { font-weight:700; font-size:16px; }

.order-status {
  font-size:12px;
  padding:4px 10px;
  border-radius:20px;
  width:fit-content;
}

.order-status.created { background:#fff3cd; color:#856404; }
.order-status.paid { background:#d4edda; color:#155724; }
.order-status.cancelled { background:#f8d7da; color:#721c24; }

.order-actions .btn {
  padding:8px 16px;
  font-size:14px;
  border-radius:8px;
}

.no-orders {
  text-align:center;
  padding:40px;
  color:#6c757d;
}
</style>

<main class="container py-4">
  <div class="orders-page">

    <header>
      <h1 class="page-title">My Orders</h1>
      <p class="page-subtitle">Track your orders, shipping & payment status</p>

      <form class="search-box" method="get">
        <input
          type="text"
          name="q"
          class="form-control"
          placeholder="Search by Order ID (e.g. AFT260113_16110)"
          value="<?= e($search) ?>"
        >
      </form>
    </header>

    <div class="layout">

      <section>
        <?php if (empty($orders)): ?>
          <div class="no-orders">
            No orders found<?= $search ? ' for "' . e($search) . '"' : '' ?>.
          </div>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <article class="order-card">

              <div class="order-meta">
                <div class="order-id"><?= e($o['order_code']) ?></div>
                <div class="order-date">
                  <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?>
                </div>
              </div>

              <div class="order-info">
                <div class="order-amount">₹ <?= number_format($o['amount'], 2) ?></div>
                <div class="order-status <?= strtolower($o['status']) ?>">
                  <?= ucfirst($o['status']) ?>
                </div>
                <div class="small text-muted">
                  Shipping: <?= e($o['delhivery_status'] ?? 'N/A') ?>
                </div>
              </div>

              <div class="order-actions">
                <a class="btn btn-primary"
                   href="order_details.php?order_id=<?= (int)$o['id'] ?>">
                  View Details →
                </a>
              </div>

            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <aside>
        <div class="card p-3 mb-3">
          <h5>Quick Actions</h5>
          <a class="btn btn-outline-secondary w-100 mb-2" href="contact.php">
            Contact Support
          </a>
          <a class="btn btn-outline-secondary w-100" href="products.php">
            Explore Products
          </a>
        </div>

        <div class="card p-3">
          <h6>Shipping Info</h6>
          <p class="small text-muted mb-0">
            Courier details, AWB number and tracking status are available inside
            each order’s detail page.
          </p>
        </div>
      </aside>

    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>