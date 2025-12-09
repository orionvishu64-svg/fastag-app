<?php
require_once __DIR__ . '/config/common_start.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Gateway</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/payment.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="payment-container" style="max-width:980px;margin:32px auto;padding:16px;">
  <a href="cart.php" class="btn-secondary" style="display:inline-block;margin-bottom:12px;">⬅ Go Back</a>

  <h2>Checkout</h2>

  <section class="order-summary" aria-labelledby="order-summary-heading" style="margin-bottom:18px;">
    <h3 id="order-summary-heading">Order Summary</h3>
    <div id="order-items" aria-live="polite"></div>
    <p><strong>Total:</strong> ₹<span id="order-total">0</span></p>
  </section>

  <section id="phone-container" style="display:none; margin-top: 16px;">
    <h3>Add Your Phone Number</h3>
    <p>We need your number to contact you about your order.</p>
    <div class="input-group">
      <input
        type="tel"
        id="payment-user-phone"
        placeholder="10-digit phone number"
        maxlength="10"
        pattern="[6-9][0-9]{9}"
        title="Enter a valid 10-digit Indian number starting 6-9"
        required
      />
      <button id="save-phone-btn" class="btn">Save</button>
    </div>
  </section>

  <section class="address-section" aria-labelledby="address-heading" style="margin-top:20px;">
    <h3 id="address-heading" class="deliver_address">Select Delivery Address</h3>
    <div id="saved-addresses" aria-live="polite"></div>

    <div style="margin-top:8px;">
      <button id="add-address-btn" class="btn">+ Add New Address</button>
    </div>

    <div id="new-address-form" style="display:none;margin-top:12px;">
      <input type="text" id="payment-house-no" placeholder="House No." />
      <input type="text" id="payment-landmark" placeholder="Area/Locality" />
      <input type="text" id="payment-city" placeholder="City/place" />
      <input type="text" id="payment-pincode" placeholder="Pincode" maxlength="6" />
      <button id="save-address" class="btn">Save Address</button>
    </div>

    <div id="pincode-ui" style="margin-top:12px;">
      <div id="pincode-status" aria-live="polite">Select address to check pincode</div>
      <div id="expected-tat" style="font-size:0.95rem;color:#666;margin-top:6px;"></div>
    </div>
  </section>

  <section class="payment-section" style="margin-top:22px;">
    <h3>Payment Method</h3>

    <div class="payment-option" style="margin:8px 0;">
      <label>
        <input type="radio" name="payment_method" value="upi" required>
        UPI Payment
      </label>
    </div>

    <div class="payment-option" style="margin:8px 0;">
      <label>
        <input type="radio" name="payment_method" value="agent-id">
        Pay using Agent ID
      </label>

      <div id="agent-id-box" style="display:none; margin-top:8px;">
        <input type="text" id="agentid" name="agentid"
               placeholder="Enter your partner ID"
               pattern="[A-Za-z0-9]+"
               title="Only letters and numbers allowed">
      </div>
    </div>

    <div style="margin-top:16px;">
      <button id="proceed-btn" class="btn btn-primary" disabled>Proceed to Pay</button>
    </div>
  </section>
</main>

<noscript>
  <div style="background:#fff8e1;border:1px solid #ffecb3;padding:12px;margin:16px;">
    JavaScript is required to complete checkout — please enable JavaScript or use a modern browser.
  </div>
</noscript>

<script src="/public/js/script.js"></script>
<script src="/public/js/site.js" defer></script>
<script src="/public/js/payment.js" defer></script>

</body>
</html>