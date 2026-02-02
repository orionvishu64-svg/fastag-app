<?php
require_once __DIR__ . '/config/common_start.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<body class="payment-page">
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="payment-wrap" style="max-width:1200px;margin:28px auto;padding:18px;">
  <div style="margin-bottom:12px;">
    <a href="cart.php" class="btn-outline" style="display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;">← Back to cart</a>
  </div>

  <div class="payment-container">
    <div>
      <div class="card" id="step1-card" role="region" aria-label="Order and delivery address">
        <h1 class="checkout-title">Checkout</h1>
        <div class="checkout-sub">Review items and choose delivery address</div>

        <section class="order-summary" aria-labelledby="order-summary-heading">
          <h3 id="order-summary-heading">Order Summary</h3>
          <div id="order-items" aria-live="polite"></div>
          <div class="order-total" style="margin-top:8px;">
            <div><strong>Subtotal</strong></div>
            <div>₹ <span id="order-total">0.00</span></div>
          </div>
        </section>

        <section id="phone-container" style="display:none; margin-top: 16px;">
          <h3>Add Your Phone Number</h3>
          <p class="muted">We need your number to contact you about your order.</p>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="tel" id="payment-user-phone" placeholder="10-digit mobile number" maxlength="10" pattern="[6-9][0-9]{9}" inputmode="tel" autocomplete="tel" style="flex:1;padding:10px;border-radius:8px;border:1px solid var(--border-1)" />
            <button id="save-phone-btn" class="btn">Save</button>
          </div>
        </section>

        <section class="address-section" aria-labelledby="address-heading" style="margin-top:20px;">
          <h3 id="address-heading" class="deliver_address">Select Delivery Address</h3>
          <div id="saved-addresses" class="address-list" aria-live="polite" role="listbox"></div>

          <div style="margin-top:12px;">
            <button id="add-address-btn" class="btn" aria-expanded="false">+ Add New Address</button>
          </div>

          <div id="new-address-form" style="display:none;margin-top:12px;">
            <div class="input-row">
              <input type="text" id="payment-house-no" placeholder="House / Flat No." />
              <input type="text" id="payment-landmark" placeholder="Area / Locality" />
            </div>
            <div class="input-row">
              <input type="text" id="payment-city" placeholder="City / Place" />
              <input type="text" id="payment-pincode" placeholder="Pincode" maxlength="6" inputmode="numeric" />
            </div>
            <div style="display:flex;gap:8px;margin-top:6px;">
              <button id="save-address" class="btn">Save Address</button>
              <button id="cancel-address" type="button" class="btn-outline">Cancel</button>
            </div>
          </div>

          <div id="pincode-ui" style="margin-top:12px;">
            <div id="pincode-status" aria-live="polite" class="muted">Select address to check pincode</div>
            <div id="expected-tat" style="font-size:0.95rem;color:var(--muted);margin-top:6px;"></div>
          </div>
        </section>

        <div style="margin-top:18px;display:flex;gap:12px;">
          <button id="proceed-btn" class="btn btn-primary" disabled>Proceed to Payment →</button>
        </div>
      </div>

      <div class="card hidden" id="step2-card" role="region" aria-label="Payment method" style="margin-top:16px;">
        <h2 class="checkout-title" style="margin-top:0;">Payment</h2>
        <div class="checkout-sub">Choose a payment method and place your order</div>

        <section style="margin-top:12px;">
          <h3 style="margin:0 0 8px 0;">Payment Method</h3>

          <div class="payment-option" style="margin-top:8px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
              <input type="radio" name="payment_method" value="upi">
              <div><strong>UPI Payment</strong><div class="muted" style="font-size:0.9rem">Quick & secure</div></div>
            </label>
          </div>

          <div class="payment-option" style="margin-top:8px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
              <input type="radio" name="payment_method" value="agent-id">
              <div><strong>Agent ID</strong><div class="muted" style="font-size:0.9rem">Use partner/agent ID (if available)</div></div>
            </label>
            <div id="agent-id-box" style="display:none; margin-top:8px;">
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="agentid" name="agentid" placeholder="Enter your partner ID" pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed" style="flex:1;padding:10px;border-radius:8px;border:1px solid var(--border-1)" />
              </div>
            </div>
          </div>
        </section>

        <div id="payment-msg" style="margin-top:12px;color:var(--muted);"></div>
      </div>
    </div>

    <aside class="checkout-summary">
      <div class="card summary-card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong>Order Summary</strong>
          <span id="items-count" class="muted">0 items</span>
        </div>

        <div style="margin-top:6px" id="right-selected-address">No address selected</div>

        <div style="margin-top:12px">
          <div class="summary-line"><span>Subtotal</span><span>₹ <span id="right-subtotal">0.00</span></span></div>
          <div class="summary-line"><span>Delivery</span><span id="right-delivery">—</span></div>
          <div class="summary-line summary-total"><span>Total</span><span>₹ <span id="right-total">0.00</span></span></div>
        </div>
      </div>
    </aside>
  </div>
</div>
<noscript>
</noscript>
<!-- Backdrop -->
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
    <div id="upi-result" class="hidden">
      <div id="upi-result-icon"></div>
      <p id="upi-result-text"></p>
      <button id="upi-retry-btn" class="upi-primary">Retry Payment</button>
    </div>
    <p class="upi-muted">Do not close this page</p>
  </div>
</div>
<script src="/public/js/payment.js" defer></script>