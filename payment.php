<?php
require_once __DIR__ . '/config/common_start.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<body class="bg-light payment-page">

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-4">

  <a href="cart.php" class="btn btn-outline-secondary mb-3">
    ← Back to Cart
  </a>

  <div class="row g-4">

    <!-- LEFT -->
    <div class="col-lg-8">

      <!-- STEP 1 -->
      <div class="card shadow-sm mb-4" id="step1-card">
        <div class="card-body">

          <h3 class="fw-bold mb-1">Checkout</h3>
          <p class="text-muted mb-3">Review items and choose delivery address</p>

          <!-- ORDER SUMMARY -->
          <h5>Order Summary</h5>
          <div id="order-items" class="small text-muted"></div>

          <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
            <span>Subtotal</span>
            ₹ <span id="order-total">0.00</span>
          </div>

          <!-- PHONE -->
          <div id="phone-container" class="mt-4" style="display:none;">
            <h6>Add Phone Number</h6>
            <div class="input-group">
              <input type="tel" id="payment-user-phone" class="form-control" placeholder="10-digit mobile">
              <button id="save-phone-btn" class="btn btn-primary">Save</button>
            </div>
          </div>

          <!-- ADDRESS -->
          <h5 class="mt-4">Select Delivery Address</h5>
          <div id="saved-addresses" class="d-grid gap-2"></div>

          <button id="add-address-btn" class="btn btn-outline-primary mt-3">
            + Add New Address
          </button>

          <!-- NEW ADDRESS -->
          <div id="new-address-form" class="mt-3" style="display:none;">
            <div class="row g-2">
              <div class="col-md-6">
                <input id="payment-house-no" class="form-control" placeholder="House / Flat No">
              </div>
              <div class="col-md-6">
                <input id="payment-landmark" class="form-control" placeholder="Area / Landmark">
              </div>
              <div class="col-md-6">
                <input id="payment-city" class="form-control" placeholder="City">
              </div>
              <div class="col-md-6">
                <input id="payment-pincode" class="form-control" placeholder="Pincode">
              </div>
            </div>

            <div class="mt-2 d-flex gap-2">
              <button id="save-address" class="btn btn-success">Save Address</button>
              <button id="cancel-address" class="btn btn-outline-secondary">Cancel</button>
            </div>
          </div>

          <div class="mt-3">
            <small id="pincode-status" class="text-muted"></small>
            <div id="expected-tat" class="small text-muted"></div>
          </div>

          <button id="proceed-btn" class="btn btn-primary w-100 mt-4" disabled>
            Proceed to Payment →
          </button>

        </div>
      </div>

      <!-- STEP 2 -->
      <div class="card shadow-sm d-none" id="step2-card">
        <div class="card-body">

          <h4 class="fw-bold">Payment</h4>
          <p class="text-muted">Choose a payment method</p>

          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" value="upi">
            <label class="form-check-label">
              <strong>UPI</strong> <small class="text-muted">(Google Pay / PhonePe)</small>
            </label>
          </div>

          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment_method" value="agent-id">
            <label class="form-check-label">
              <strong>Agent ID</strong> <small class="text-muted">(Zero amount)</small>
            </label>
          </div>

          <div id="agent-id-box" class="mt-2" style="display:none;">
            <input id="agentid" class="form-control" placeholder="Enter Agent ID">
          </div>

        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div class="col-lg-4">
      <div class="card shadow-sm position-sticky" style="top:90px;">
        <div class="card-body">

          <div class="d-flex justify-content-between">
            <strong>Order Summary</strong>
            <span id="items-count" class="text-muted">0 items</span>
          </div>

          <div id="right-selected-address" class="small text-muted mt-2">
            No address selected
          </div>

          <hr>

          <div class="d-flex justify-content-between">
            <span>Subtotal</span>
            ₹ <span id="right-subtotal">0.00</span>
          </div>

          <div class="d-flex justify-content-between">
            <span>Delivery</span>
            <span id="right-delivery">—</span>
          </div>

          <div class="d-flex justify-content-between fw-bold mt-2">
            <span>Total</span>
            ₹ <span id="right-total">0.00</span>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>

<!-- 🔥 UPI BACKDROP & SHEET (UNCHANGED) -->
<div id="upi-backdrop" class="upi-backdrop hidden"></div>
<div id="upi-sheet" class="upi-sheet hidden">
  <div id="upi-confirm-view">
    <div class="upi-handle"></div>
    <h3>Confirm UPI Payment</h3>
    <div class="upi-summary">
      <div>Items: <strong id="upi-items">0</strong></div>
      <div>Amount: <strong>₹<span id="upi-amount">0.00</span></strong></div>
    </div>
    <button id="upiPayNow" class="upi-primary">Pay Now</button>
    <button id="upiCancel" class="upi-secondary">Cancel</button>
  </div>

  <div id="upi-timer-view" class="hidden">
    <h3>Complete payment in UPI app</h3>
    <div class="timer-ring">
      <svg width="160" height="160">
        <circle cx="80" cy="80" r="70" />
        <circle id="timer-progress" cx="80" cy="80" r="70" />
      </svg>
      <div id="timer-text">05:00</div>
    </div>
    <p class="upi-muted">Do not close this page</p>
  </div>
</div>
<script src="/public/js/payment.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>