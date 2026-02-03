<?php
require_once 'config/common_start.php';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<style>
.cart-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

@media (max-width: 768px) {
  .cart-item {
    flex-direction: column;
    align-items: flex-start;
  }
}

.item-image {
  min-width: 80px;
  height: 80px;
  border-radius: 10px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: #2563eb;
}

.item-details {
  flex: 1;
}

.item-name {
  font-weight: 600;
  font-size: 1.05rem;
}

.item-badges {
  display: flex;
  gap: 8px;
  margin: 6px 0;
}

.item-badge {
  font-size: 0.75rem;
  background: #eef2ff;
  color: #3730a3;
  padding: 3px 8px;
  border-radius: 6px;
}

.item-price {
  font-weight: 700;
  font-size: 1.1rem;
  margin-top: 6px;
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.remove-btn {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 1.1rem;
}

.remove-btn:hover {
  color: #b91c1c;
}

#emptyCart {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
}

@media (max-width: 768px) {
  #emptyCart {
    min-height: auto;
    padding-top: 40px;
    padding-bottom: 40px;
  }
}

</style>
<main class="py-5 bg-light">
  <div class="container">
    <!-- PAGE HEADER -->
    <div class="text-center mb-5">
      <h1 class="fw-bold">
        <i class="fas fa-shopping-cart me-2 text-primary"></i>
        Shopping Cart
      </h1>
      <p class="text-muted mb-0">
        Review your items and proceed to checkout
      </p>
    </div>

    <!-- EMPTY CART -->
    <div id="emptyCart">
      <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
          <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
          <h3 class="fw-bold">Your Cart is Empty</h3>
          <p class="text-muted">
            Looks like you haven't added any FASTags to your cart yet.
          </p>

          <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
            <a href="products.php" class="btn btn-primary">
              Continue Shopping
            </a>
            <a href="track_orders.php" class="btn btn-outline-primary">
              Previous Orders
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- CART CONTENT -->
    <div id="cartContent">
      <div class="row g-4">

        <!-- CART ITEMS -->
        <div class="col-lg-8">

          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
              <h5 class="mb-0 fw-bold">
                Your Items (<span id="itemCount">0</span>)
              </h5>
              <button id="clearCartBtn" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash me-1"></i> Clear Cart
              </button>
            </div>
          </div>

          <!-- ITEMS LIST (JS injects here) -->
          <div id="itemsList" class="d-flex flex-column gap-3"></div>

        </div>

        <!-- ORDER SUMMARY -->
        <div class="col-lg-4">
          <div class="position-sticky" style="top:100px">

            <!-- SUMMARY CARD -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-body">
                <h5 class="fw-bold mb-3">Order Summary</h5>

                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Subtotal</span>
                  <strong id="subtotal">₹0</strong>
                </div>

                <hr>

                <div class="d-flex justify-content-between fs-5">
                  <span class="fw-bold">Total</span>
                  <span class="fw-bold text-primary" id="total">₹0</span>
                </div>

                <button id="checkoutBtn" class="btn btn-primary w-100 mt-4">
                  <i class="fas fa-credit-card me-2"></i>
                  Proceed to Checkout
                </button>

                <!-- TRUST BADGES -->
                <div class="row text-center mt-4">
                  <div class="col-6">
                    <i class="fas fa-truck text-success fs-4"></i>
                    <div class="small text-muted">Fast Delivery</div>
                  </div>
                  <div class="col-6">
                    <i class="fas fa-shield-alt text-success fs-4"></i>
                    <div class="small text-muted">Secure Payment</div>
                  </div>
                </div>

              </div>
            </div>

            <!-- DELIVERY INFO -->
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h6 class="fw-bold mb-3">Delivery Information</h6>

                <div class="d-flex align-items-start gap-3 mb-3">
                  <i class="fas fa-shipping-fast fs-5 text-primary"></i>
                  <div>
                    <strong>Delivered In</strong>
                    <p class="mb-0 text-muted small">2–4 business days</p>
                  </div>
                </div>

                <div class="d-flex align-items-start gap-3">
                  <i class="fas fa-phone fs-5 text-primary"></i>
                  <div>
                    <strong>Customer Support</strong>
                    <p class="mb-0 text-muted small">Available 24×7</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="/public/js/auth-sync.js"></script>
<script src="/public/js/cart.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>