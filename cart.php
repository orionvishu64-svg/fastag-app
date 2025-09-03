<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Apna Payment Services</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="cart.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
   <nav class="navbar">
  <div class="nav-container">
    <div class="nav-logo">
      <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200White.png">
    </div>

    <div class="nav-right">
      <!-- Desktop main nav -->
      <ul class="nav-menu desktop-only">
        <li><a href="index.html" class="nav-link">Home</a></li>
        <li><a href="products.html" class="nav-link">Products</a></li>
        <li><a href="about.html" class="nav-link">About Us</a></li>
        <li><a href="blog.html" class="nav-link">Blog</a></li>
        <li><a href="contact.html" class="nav-link">Contact Us</a></li>
      </ul>

      <!-- Icons (always visible) -->
      <div class="nav-actions">
        <a href="cart.php" class="cart-btn">
          <i class="fas fa-shopping-cart"></i>
          <span class="cart-count">0</span>
        </a>
        <a href="login.html" class="login-btn">
          <i class="fas fa-user"></i> Login
        </a>
      </div>

      <!-- Hamburger (always visible) -->
      <div class="hamburger">
        <i class="fas fa-bars"></i>
      </div>

      <!-- Hamburger dropdown -->
      <div class="hamburger-menu">
        <ul>
          <!-- Mobile: show all links -->
          <li class="mobile-only"><a href="index.html">Home</a></li>
          <li class="mobile-only"><a href="profile.html">Profile</a></li>
          <li class="mobile-only"><a href="cart.php">Cart</a></li>
          <li class="mobile-only"><a href="products.html">Products</a></li>
          <li class="mobile-only"><a href="contact.html">Contact Us</a></li>

          <!-- Always inside hamburger -->
          <li><a href="track_orders.php">Track Order</a></li>
          <li><a href="#" id="nav-logout">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

    <!-- Cart Content -->
    <section class="cart-section">
        <div class="container">
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                <p>Review your items and proceed to checkout</p>
            </div>

            <!-- Empty Cart State -->
            <div class="empty-cart" id="emptyCart" style="display: none;">
                <div class="empty-cart-content">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your Cart is Empty</h2>
                    <p>Looks like you haven't added any FASTags to your cart yet.</p>
                    <a href="products.html" class="btn btn-primary">Continue Shopping</a> <br> <br>
                    <a href="track_orders.php" class="btn btn-primary">Previous orders</a>
                </div>
            </div>
            
            <!-- Cart Items -->
            <div class="cart-content" id="cartContent">
                <div class="cart-grid">
                    <!-- Cart Items List -->
                    <div class="cart-items">
                        <div class="cart-items-header">
                            <h2>Your Items (<span id="itemCount">0</span>)</h2>
                            <button class="clear-cart-btn" id="clearCartBtn">
                                <i class="fas fa-trash"></i>
                                Clear Cart
                            </button>
                        </div>
                        
                        <div class="items-list" id="itemsList">
                            <!-- Cart items will be dynamically inserted here -->
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="order-summary">
                       <div class="sticky-summary">
                        <div class="summary-card">
                            <h3>Order Summary</h3>
                            
                            <div class="summary-details">
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="subtotal">₹0</span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping</span>
                                    <span id="shipping">₹0</span>
                                </div>
                                <div class="summary-row discount-row" id="discountRow" style="display: none;">
                                    <span>Discount</span>
                                    <span id="discount">-₹0</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-row total-row">
                                    <span>Total</span>
                                    <span id="total">₹0</span>
                                </div>
                            </div>

                            <div class="shipping-info" id="shippingInfo">
                                <p><i class="fas fa-info-circle"></i> Add ₹<span id="freeShippingAmount">0</span> more for free shipping</p>
                            </div>

                            <button class="checkout-btn" id="checkoutBtn">
                                <i class="fas fa-credit-card"></i>
                                Proceed to Checkout
                            </button>

                            <div class="trust-badges">
                                <div class="trust-item">
                                    <i class="fas fa-truck"></i>
                                    <span>Fast Delivery</span>
                                </div>
                                <div class="trust-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Secure Payment</span>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Information -->
                        <div class="delivery-card">
                            <h3>Delivery Information</h3>
                            <div id="phone-container" style="display: none;">
                                <h3>Phone Number:</h3>
                                    <input type="tel" name="phone" id="cart-user-phone" class="cart-quantity-input" placeholder="Enter 10-digit number">
                                    <button id="save-phone-btn" class="cart-save-btn">Save</button>
                            </div>
                            <div class="delivery-options" id="deliveryOptions">
                                <div class="delivery-option">
                                    <i class="fas fa-shipping-fast"></i>
                                    <div>
                                        <strong>Delivered In</strong>
                                        <p>2-4 business days</p>
                                    </div>
                                </div>
                                <div class="delivery-option">
                                    <i class="fas fa-phone"></i>
                                    <div>
                                        <strong>Customer Support</strong>
                                        <p>Available 24*7</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                      </div> 
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200White.png" alt="Apna Payment Logo" style="max-width: 180px; margin-bottom: 1rem;">
                    </div>
                    <p>Apna Payment Services Private Limited -  A-40, KARDHANI, GOVINDPURA,<br>
          JAIPUR, RAJASTHAN, 302012,<br>
          KARDHANI, Rajasthan, PIN: 302012<br>
          GSTIN : 08AAVCA0650L1ZA<br></p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/apnapayment/"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.youtube.com/@ApnaPayment"><i class="fab fa-youtube"></i></a>
                        <a href="https://www.instagram.com/apnapayment/"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="https://play.google.com/store/apps/details?id=com.aps.agent">GV Partner</a></li>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="blog.html">Blog</a></li>
                        <li><a href="terms-conditions.html">Terms &amp;condition</a></li>
                        <li><a href="privacy-policy.html">Privacy Policy</a></li>
                        <li><a href="refund-cancel.html">Refund Policy</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li>Kotak FASTag</li>
                        <li>SBI FASTag</li>
                        <li>Bajaj FASTag</li>
                        <li>IDFC FASTag</li>
                        <li>FASTag recharge</li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>+91 9509807591</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>admin@apnapayment.com</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Jaipur, Rajasthan, India</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2020-2025 Apna payments services pvt.ltd.. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
    <script src="cart.js"></script>
</body>
</html>