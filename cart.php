<?php
require_once 'config/common_start.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Apna Payment Services</title>
    <link rel="stylesheet" href="/public/css/styles.css">
    <link rel="stylesheet" href="/public/css/theme.css">
    <link rel="stylesheet" href="/public/css/cart.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
    <!-- Cart Content all items will populate here -->
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
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a> <br> <br>
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
    <script src="/public/js/auth-sync.js"></script>
    <script src="/public/js/script.js"></script>
    <script src="/public/js/cart.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>