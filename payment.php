<?php
require_once __DIR__ . '/config/common_start.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Gateway</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/payment.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
  <div class="payment-container">
    <a href="cart.php" class="back-btn">⬅ Go Back</a>
    <h2>Checkout</h2>
    <!-- Order Summary -->
    <div class="order-summary">
      <h3>Order Summary</h3>
      <div id="order-items"></div>
      <p><strong>Total:</strong> ₹<span id="order-total">0</span></p>
    </div>

    <!-- Phone number block (shown only if user has no phone) -->
    <div id="phone-container" style="display:none; margin-top: 16px;">
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
    </div>
  
    <!-- Delivery Address Section -->
    <div class="address-section">
      <h3 class="deliver_address">Select Delivery Address</h3>
      <div id="saved-addresses"></div>

      <button id="add-address-btn">+ Add New Address</button>

      <div id="new-address-form" style="display:none;">
        <input type="text" id="payment-house-no" placeholder="House No.">
        <input type="text" id="payment-landmark" placeholder="Area/Locality">
        <input type="text" id="payment-city" placeholder="City/place">
        <input type="text" id="payment-pincode" placeholder="Pincode">
        <button id="save-address">Save Address</button>
      </div>
    </div>
<!-- Payment Method -->
<div class="payment-section">
  <h2>Payment Method</h2>
  
  <!-- UPI Option -->
  <div class="payment-option">
    <label>
      <input type="radio" name="payment_method" value="upi" required>
      UPI Payment
    </label>
  </div>

  <!-- Agent ID Option -->
  <div class="payment-option">
    <label>
      <input type="radio" name="payment_method" value="agent-id">
      Pay using Agent ID
    </label>
    
    <!-- Hidden until "Agent ID" is selected -->
    <div id="agent-id-box" style="display:none; margin-top:8px;">
      <input type="text" id="agentid" name="agentid"
             placeholder="Enter your partner ID"
             pattern="[A-Za-z0-9]+"
             title="Only letters and numbers allowed">
    </div>
  </div>

  <button id="proceed-btn">Proceed to Pay</button>
</div>

    </div>
    <script src="/public/js/payment.js"></script>
    <script src="/public/js/script.js"></script>
  </body>
</html>