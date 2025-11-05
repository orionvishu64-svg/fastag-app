<?php
// products.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FASTag Products - Apna Payment Services</title>
  <link rel="stylesheet" href="/public/css/styles.css" />
  <link rel="stylesheet" href="/public/css/products.css" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />

  <noscript>
    <style>
      .products-section { display:none; }
      .no-js-warning { margin: 16px 0; padding: 12px; background:#fff3cd; color:#664d03; border-radius:8px; }
    </style>
  </noscript>
</head>
<body data-page="products">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container page-products">

  <section class="page-header">
    <h1>FASTag Products</h1>
    <p><b>Choose from our FASTag products across banks & vehicle categories</b></p>
    <noscript><div class="no-js-warning">Please enable JavaScript to view products.</div></noscript>
  </section>

  <section class="filters-section">
    <div class="container">
      <div class="filters-card">
        <div class="filters-grid">
          <!-- Search -->
          <div class="search-filter">
            <i class="fas fa-search" aria-hidden="true"></i>
            <label class="sr-only" for="searchInput">Search products</label>
            <input type="text" id="searchInput" placeholder="Search products..." />
          </div>

          <!-- Bank filter -->
          <div class="bank-filter">
            <label class="sr-only" for="bankFilter">Filter by bank</label>
            <select id="bankFilter" aria-label="Filter by bank">
              <option value="all">All Banks</option>
              <option value="Kotak">Kotak</option>
              <option value="SBI">SBI</option>
              <option value="Bajaj">Bajaj</option>
              <option value="IDFC">IDFC</option>
            </select>
          </div>

          <!-- Category filter -->
          <div class="category-filter">
            <label class="sr-only" for="categoryFilter">Filter by category</label>
            <select id="categoryFilter" aria-label="Filter by category">
              <option value="all">All Categories</option>
              <option value="VC4">VC4 - Car/Jeep/Van</option>
              <option value="VC4max">VC4max - luxury Car/Jeep/Van</option>
              <option value="VC5">VC5 - LCV</option>
              <option value="VC6">VC6 - Bus/Truck</option>
              <option value="VC7">VC7 - Heavy Vehicle</option>
              <option value="VC8">VC8 - Construction Vehicle</option>
              <option value="VC12">VC12 - Mini Bus</option>
              <option value="VC16">VC16 - Heavy Construction Vehicle</option>
            </select>
          </div>

          <!-- Clear filters button (JS binds to .clear-filters) -->
          <div class="filters-actions">
            <button type="button" class="btn clear-filters" aria-label="Clear all filters">
              Clear All Filters
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="products-section">
    <div class="container">
      <div class="products-header-info">
        <h2>Available Products</h2>
        <div class="results-count">
          <span id="resultsCount">0</span> products found
        </div>
      </div>

      <!-- Renderer inserts cards here -->
      <div id="products-container" class="products-grid" aria-live="polite"></div>

      <!-- Optional legacy modal (kept hidden) -->
      <div id="productDetailModal" class="product-detail-modal" style="display:none;">
        <div class="product-detail-content">
          <div class="product-detail-body"></div>
        </div>
      </div>

      <div class="no-results" id="noResults" style="display:none;">
        <i class="fas fa-search" aria-hidden="true"></i>
        <h3>No products found</h3>
        <p>No products match your current filters. Try adjusting your search criteria.</p>
        <button type="button" class="btn btn-primary clear-filters">Clear All Filters</button>
      </div>
    </div>
  </section>

  <!-- Special Notice about Kotak VC4 FASTag -->
  <section class="special-notice">
    <div class="container">
      <div class="notice-card warning">
        <h3><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Important Notice:</h3>
        <p><strong>Kotak Bank FASTag</strong> is currently not available for VC4 (Car/Jeep/Van) category.
        For VC4 vehicles, please choose from SBI, Bajaj, or IDFC banks.
        Kotak FASTag is available for VC5, VC6, VC7, VC8, and VC12 categories.</p>
      </div>
    </div>
  </section>

</main>

<!-- Defer scripts to ensure DOM is ready -->
<script src="/public/js/script.js" defer></script>
<script src="/public/js/productdb.js" defer></script>
<script src="/public/js/products.js" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
