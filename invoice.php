<?php
// invoice.php - robust invoice rendering (joins users + addresses)
require_once __DIR__ . '/config/common_start.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    http_response_code(400);
    echo "Missing order_id";
    exit;
}

// get current user id (allow admin)
$currentUserId = null;
if (function_exists('get_current_user_id')) $currentUserId = get_current_user_id();
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.user_id, o.amount, o.shipping_amount, o.created_at, o.awb, o.label_url,
            o.payment_method, o.payment_status, o.status AS order_status,
            u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            a.house_no, a.landmark, a.city, a.pincode
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.address_id = a.id
        WHERE o.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("invoice fetch error: " . $e->getMessage());
    http_response_code(500);
    echo "Server error.";
    exit;
}

if (!$order) {
    http_response_code(404);
    echo "Order not found.";
    exit;
}

// Access control: owner or admin
$is_admin = !empty($_SESSION['admin_id']);
if (!$is_admin && ($currentUserId === null || (int)$order['user_id'] !== (int)$currentUserId)) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// fetch items
try {
    $it = $pdo->prepare("SELECT product_name, bank, quantity, price FROM order_items WHERE order_id = :id");
    $it->execute([':id' => $order_id]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("invoice items fetch error: " . $e->getMessage());
    $items = [];
}

// totals
$totalAmount = isset($order['amount']) ? (float)$order['amount'] : 0.0;
$shipping = isset($order['shipping_amount']) ? (float)$order['shipping_amount'] : 0.0;
$grand = $totalAmount + $shipping;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice #<?= esc($order['id']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background: #f4f6f9;
    }

    .invoice-card {
      border-radius: 14px;
      border: none;
    }

    .company-logo {
      max-height: 48px;
    }

    .invoice-title {
      font-weight: 700;
      letter-spacing: .5px;
    }

    .section-title {
      font-weight: 600;
      font-size: .9rem;
      color: #6c757d;
      margin-bottom: 6px;
    }

    .table th {
      background: #f8f9fa;
      font-weight: 600;
    }

    .total-box {
      background: #f8f9fa;
      border-radius: 12px;
      padding: 16px;
    }

    @media print {
      body { background: #fff !important; }
      .no-print { display: none !important; }
      .invoice-card { box-shadow: none !important; }
    }
  </style>
</head>

<body>
<div class="container my-5">
  <div class="card shadow-sm invoice-card">
    <div class="card-body p-4 p-md-5">

      <!-- HEADER -->
      <div class="row align-items-center mb-4">
        <div class="col-md-7 d-flex align-items-center gap-3">
          <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
               alt="Apna Payments"
               class="company-logo">
          <div>
            <h5 class="mb-1 fw-bold">Apna Payments Services Pvt Ltd</h5>
            <div class="text-muted small">
              A-40, Kardhani, Govindpura<br>
              Jaipur, Rajasthan – 302012<br>
              GSTIN: 08AAVCA0650L1ZA
            </div>
          </div>
        </div>

        <div class="col-md-5 text-md-end mt-4 mt-md-0">
          <h4 class="invoice-title mb-2">INVOICE</h4>
          <div class="small">Invoice #: <strong><?= esc($order['id']) ?></strong></div>
          <div class="small">Date: <?= esc($order['created_at']) ?></div>
          <div class="small">Order #: <?= esc($order['id']) ?></div>
        </div>
      </div>

      <hr class="my-4">

      <!-- INFO -->
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="section-title">BILLED TO</div>
          <div class="fw-semibold"><?= esc($order['customer_name']) ?></div>
          <div class="small text-muted"><?= esc($order['customer_email']) ?></div>
          <?php if(!empty($order['customer_phone'])): ?>
            <div class="small text-muted"><?= esc($order['customer_phone']) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-4 mt-3 mt-md-0">
          <div class="section-title">SHIPPING ADDRESS</div>
          <div><?= esc($order['house_no']) ?></div>
          <div class="small text-muted"><?= esc($order['landmark']) ?></div>
          <div class="small text-muted"><?= esc($order['city']) ?> - <?= esc($order['pincode']) ?></div>
          <?php if(!empty($order['awb'])): ?>
            <div class="small mt-1"><strong>AWB:</strong> <?= esc($order['awb']) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-4 mt-3 mt-md-0">
          <div class="section-title">PAYMENT</div>
          <div><?= esc(ucfirst($order['payment_method'])) ?></div>
          <div class="small text-muted">Status: <?= esc($order['payment_status']) ?></div>
          <div class="small text-muted">Order Status: <?= esc($order['order_status']) ?></div>
        </div>
      </div>

      <!-- ITEMS -->
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th>Product</th>
              <th>Bank</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Price</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= esc($it['product_name']) ?></td>
              <td><?= esc($it['bank']) ?></td>
              <td class="text-center"><?= (int)$it['quantity'] ?></td>
              <td class="text-end">₹<?= number_format($it['price'],2) ?></td>
              <td class="text-end">₹<?= number_format($it['price']*$it['quantity'],2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- TOTAL -->
      <div class="row justify-content-end">
        <div class="col-md-5">
          <div class="total-box">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Subtotal</span>
              <span>₹<?= number_format($totalAmount,2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Shipping</span>
              <span>₹<?= number_format($shipping,2) ?></span>
            </div>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5">
              <span>Total</span>
              <span>₹<?= number_format($grand,2) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- ACTION -->
      <div class="text-end mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary px-4">
          🖨 Print Invoice
        </button>
      </div>

    </div>
  </div>
</div>
</body>
</html>