<body data-page="products" data-bank="SBI" class="bg-light">

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container py-4">

  <!-- BANK HEADER -->
  <section class="mb-5">
    <div class="card border-0 shadow-sm overflow-hidden">
      <div class="row g-0 align-items-center">

        <div class="col-md-4 text-center bg-light p-4">
          <img
            src="/uploads/images/sbi-fastag.webp"
            alt="SBI FASTag"
            class="img-fluid rounded"
            style="max-height:140px"
            loading="lazy"
          />
        </div>

        <div class="col-md-8 p-4">
          <h1 class="fw-bold text-primary mb-2">
            SBI FASTag
          </h1>

          <p class="text-muted mb-3">
            Choose your vehicle category for State Bank of India FASTag.
            India’s largest bank with nationwide acceptance.
          </p>

          <div class="d-flex flex-wrap gap-3 small">
            <div class="d-flex align-items-center gap-2">
              <i class="fas fa-clock text-primary"></i>
              <span>24-hour activation</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <i class="fas fa-shield-alt text-primary"></i>
              <span>Secure payments</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <i class="fas fa-mobile-alt text-primary"></i>
              <span>YONO app integration</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- PRODUCTS HEADER -->
  <section class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Select Your Vehicle Category</h4>
    <span class="text-muted">
      <span id="resultsCount">0</span> products found
    </span>
  </section>

  <!-- PRODUCTS GRID -->
  <div
    id="products-container"
    class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4"
    aria-live="polite"
  ></div>

  <!-- NO RESULTS -->
  <div id="noResults" class="text-center py-5 d-none">
    <i class="fas fa-search fa-3x text-muted mb-3"></i>
    <h5>No products found</h5>
    <p class="text-muted">No SBI FASTag products available.</p>
  </div>

  <!-- BANK BENEFITS -->
  <section class="mt-5">
    <h3 class="fw-bold mb-4">Why Choose SBI FASTag?</h3>

    <div class="row g-4">
      <div class="col-md-3 col-6">
        <div class="card h-100 text-center shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-university fa-2x text-primary mb-2"></i>
            <h6 class="fw-bold">India’s Largest Bank</h6>
            <p class="text-muted small mb-0">
              Trusted public sector bank
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="card h-100 text-center shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-mobile-alt fa-2x text-primary mb-2"></i>
            <h6 class="fw-bold">YONO Integration</h6>
            <p class="text-muted small mb-0">
              Easy FASTag management
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="card h-100 text-center shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-network-wired fa-2x text-primary mb-2"></i>
            <h6 class="fw-bold">Nationwide Network</h6>
            <p class="text-muted small mb-0">
              PAN-India acceptance
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="card h-100 text-center shadow-sm border-0">
          <div class="card-body">
            <i class="fas fa-award fa-2x text-primary mb-2"></i>
            <h6 class="fw-bold">Trusted Brand</h6>
            <p class="text-muted small mb-0">
              Decades of customer trust
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
<!-- PRODUCT MODAL (BOTTOM SLIDE) -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">

      <div class="modal-header modal-gradient">
        <h5 class="modal-title text-white">Product Details</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-4">

          <div class="col-md-5 text-center">
            <img class="img-fluid rounded bg-light p-3 product-img" style="max-height:260px">
          </div>

          <div class="col-md-7">
            <h4 class="fw-bold product-title"></h4>
            <div class="mb-2 product-bank-cat"></div>

            <div class="border rounded p-3 bg-light mb-3 small">
              <div>Activation: <strong class="p-activation"></strong></div>
              <div>Balance: <strong class="p-balance"></strong></div>
              <div>Security: <strong class="p-security"></strong></div>
              <div>Tag Cost: <strong class="p-tagcost"></strong></div>
              <div>Payout: <strong class="p-payout"></strong></div>
            </div>

            <div class="fs-3 fw-bold text-primary p-price"></div>
            <p class="text-muted p-desc"></p>

            <div class="d-flex gap-2">
              <input type="number" class="form-control qty-input" value="1" min="1" style="max-width:80px">
              <button class="btn btn-primary add-btn w-100">Add to Cart</button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="/public/js/productdb.js" defer></script>
<script src="/public/js/products.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
