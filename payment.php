<?php
// payment.php (Bootstrap-based checkout page)
require_once __DIR__ . '/config/common_start.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Checkout — Payment</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">

  <style>
    body {
      background-color: #f4f6f8;
    }
    .card {
      border-radius: 14px;
      box-shadow: 0 10px 28px rgba(0,0,0,0.08);
      border: none;
    }
    .checkout-title {
      font-weight: 700;
      margin-bottom: 4px;
    }
    .checkout-sub {
      color: #6c757d;
      font-size: 0.95rem;
    }
    .muted {
      color: #6c757d;
      font-size: 0.9rem;
    }
    .btn-primary {
      background: linear-gradient(90deg, #ffb84d, #e85c41);
      border: none;
      color: #111;
      font-weight: 700;
    }
    .btn-primary:disabled {
      opacity: 0.6;
    }
    .address-item {
      border: 1px solid #dee2e6;
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 8px;
      cursor: pointer;
    }
    .address-item.active {
      border-color: #ffb84d;
      background-color: #fff8ec;
    }
    .summary-line {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
    }
    .summary-total {
      font-weight: 700;
      border-top: 1px dashed #dee2e6;
      padding-top: 8px;
    }

    /* UPI Sheet */
    .upi-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      z-index: 1040;
    }
    .upi-sheet {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 100%;
      max-width: 420px;
      background: #fff;
      border-radius: 16px 16px 0 0;
      padding: 16px;
      z-index: 1050;
    }
    .upi-handle {
      width: 50px;
      height: 5px;
      background: #ccc;
      border-radius: 3px;
      margin: 0 auto 10px;
    }
    .upi-primary {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      background: linear-gradient(90deg, #ffb84d, #e85c41);
      font-weight: 700;
    }
    .upi-secondary {
      width: 100%;
      margin-top: 8px;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid #ccc;
      background: #fff;
    }
    .hidden { display: none; }
  </style>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-4">
  <a href="cart.php" class="text-decoration-none mb-3 d-inline-block">
    ← Back to cart
  </a>

  <div class="row g-4">

    <!-- LEFT -->
    <div class="col-lg-8">

      <!-- STEP 1 -->
      <div class="card p-4 mb-4" id="step1-card">
        <h2 class="checkout-title">Checkout</h2>
        <div class="checkout-sub">
          Review items and choose delivery address
        </div>

        <!-- Order Summary -->
        <section class="mt-4">
          <h5>Order Summary</h5>
          <div id="order-items"></div>
          <div class="summary-line mt-2">
            <strong>Subtotal</strong>
            <span>₹ <span id="order-total">0.00</span></span>
          </div>
        </section>

        <!-- Phone -->
        <section id="phone-container" class="mt-4" style="display:none;">
          <h5>Add Your Phone Number</h5>
          <p class="muted">We may contact you regarding your order.</p>
          <div class="d-flex gap-2">
            <input
              type="tel"
              id="payment-user-phone"
              class="form-control"
              placeholder="10-digit mobile number"
              maxlength="10"
            />
            <button id="save-phone-btn" class="btn btn-primary">
              Save
            </button>
          </div>
        </section>

        <!-- Address -->
        <section class="mt-4">
          <h5>Select Delivery Address</h5>
          <div id="saved-addresses"></div>

          <button
            id="add-address-btn"
            class="btn btn-outline-secondary mt-2"
          >
            + Add New Address
          </button>

          <div id="new-address-form" class="mt-3" style="display:none;">
            <div class="row g-2">
              <div class="col-md-6">
                <input id="payment-house-no" class="form-control" placeholder="House / Flat No.">
              </div>
              <div class="col-md-6">
                <input id="payment-landmark" class="form-control" placeholder="Area / Locality">
              </div>
              <div class="col-md-6">
                <input id="payment-city" class="form-control" placeholder="City">
              </div>
              <div class="col-md-6">
                <input id="payment-pincode" class="form-control" placeholder="Pincode" maxlength="6">
              </div>
            </div>

            <div class="d-flex gap-2 mt-2">
              <button id="save-address" class="btn btn-primary">
                Save Address
              </button>
              <button id="cancel-address" class="btn btn-outline-secondary">
                Cancel
              </button>
            </div>
          </div>

          <div class="mt-3">
            <div id="pincode-status" class="muted">
              Select address to check pincode
            </div>
            <div id="expected-tat" class="muted"></div>
          </div>
        </section>

        <button
          id="proceed-btn"
          class="btn btn-primary mt-4"
          disabled
        >
          Proceed to Payment →
        </button>
      </div>

      <!-- STEP 2 -->
      <div class="card p-4 hidden" id="step2-card">
        <h3 class="checkout-title">Payment</h3>
        <div class="checkout-sub">
          Choose a payment method
        </div>

        <div class="mt-3">
          <div class="form-check mb-2">
            <input
              class="form-check-input"
              type="radio"
              name="payment_method"
              value="upi"
              id="pay-upi"
            >
            <label class="form-check-label" for="pay-upi">
              <strong>UPI Payment</strong><br>
              <span class="muted">Quick & secure</span>
            </label>
          </div>

          <div class="form-check mb-2">
            <input
              class="form-check-input"
              type="radio"
              name="payment_method"
              value="agent-id"
              id="pay-agent"
            >
            <label class="form-check-label" for="pay-agent">
              <strong>Agent ID</strong><br>
              <span class="muted">Partner / Agent ID</span>
            </label>
          </div>

          <div id="agent-id-box" class="mt-2" style="display:none;">
            <input
              id="agentid"
              class="form-control"
              placeholder="Enter agent ID"
            >
          </div>

          <div id="payment-msg" class="muted mt-2"></div>
        </div>
      </div>
    </div>

    <!-- RIGHT SUMMARY -->
    <div class="col-lg-4">
      <div class="card p-3">
        <div class="d-flex justify-content-between">
          <strong>Order Summary</strong>
          <span id="items-count" class="muted">0 items</span>
        </div>

        <div id="right-selected-address" class="muted mt-2">
          No address selected
        </div>

        <div class="mt-3">
          <div class="summary-line">
            <span>Subtotal</span>
            <span>₹ <span id="right-subtotal">0.00</span></span>
          </div>
          <div class="summary-line">
            <span>Delivery</span>
            <span id="right-delivery">—</span>
          </div>
          <div class="summary-line summary-total">
            <span>Total</span>
            <span>₹ <span id="right-total">0.00</span></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- UPI BACKDROP -->
<div id="upi-backdrop" class="upi-backdrop hidden"></div>

<!-- UPI SHEET -->
<div id="upi-sheet" class="upi-sheet hidden">
  <div id="upi-confirm-view">
    <div class="upi-handle"></div>
    <h5 class="text-center">Confirm UPI Payment</h5>
    <div class="mt-2">
      Items: <strong id="upi-items">0</strong><br>
      Amount: <strong>₹ <span id="upi-amount">0.00</span></strong>
    </div>
    <button id="upiPayNow" class="upi-primary mt-3">Pay Now</button>
    <button id="upiCancel" class="upi-secondary">Cancel</button>
  </div>
</div>

<script src="/public/js/script.js"></script>
<script src="/public/js/payment.js" defer></script>
</body>
</html>
