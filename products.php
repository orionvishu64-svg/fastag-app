<body data-page="products" class="bg-light">

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container py-4">

  <!-- Header -->
  <section class="text-center mb-4">
    <h1 class="fw-bold text-primary">FASTag Products</h1>
    <p class="text-muted fw-semibold">
      Choose from our FASTag products across banks & vehicle categories
    </p>
  </section>

  <!-- Filters -->
  <section class="mb-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="row g-3 align-items-center">

          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text bg-white">
                <i class="fas fa-search text-muted"></i>
              </span>
              <input id="searchInput" class="form-control" placeholder="Search products...">
            </div>
          </div>

          <div class="col-md-3">
            <select id="bankFilter" class="form-select">
              <option value="all">All Banks</option>
              <option value="SBI">SBI</option>
              <option value="Bajaj">Bajaj</option>
              <option value="IDFC">IDFC</option>
              <option value="Kotak">Kotak</option>
            </select>
          </div>

          <div class="col-md-3">
            <select id="categoryFilter" class="form-select">
              <option value="all">All Categories</option>
              <option value="VC4">VC4 - Car</option>
              <option value="VC5">VC5 - LCV</option>
              <option value="VC6">VC6 - Bus/Truck</option>
            </select>
          </div>

          <div class="col-12 text-end">
            <button class="btn btn-outline-secondary clear-filters">
              Clear All Filters
            </button>
          </div>

        </div>
      </div>
    </div>
  </section>

  <!-- Products -->
  <section class="d-flex justify-content-between mb-3">
    <h4 class="fw-bold">Available Products</h4>
    <span class="text-muted"><span id="resultsCount">0</span> products found</span>
  </section>

  <!-- 2 per row on mobile -->
  <div id="products-container" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4"></div>

  <div id="noResults" class="text-center py-5 d-none">
    <h5>No products found</h5>
    <button class="btn btn-primary clear-filters">Clear Filters</button>
  </div>

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