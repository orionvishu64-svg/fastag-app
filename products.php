<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASTag Products - Apna Payment Services</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-page="products">
<?php include __DIR__ . '/includes/header.php'; ?>
<!--products now will be inserted here with dtabase-->
    <main class="container page-products">
    <section class="page-header">
      <h1>FASTag Products</h1>
      <p><b>Choose from our FASTag products across banks & vehicle categories</b></p>
    </section>

    <section class="filters-section">
      <div class="container">
        <div class="filters-card">
          <div class="filters-grid">
            <div class="search-filter">
              <i class="fas fa-search"></i>
              <input type="text" id="searchInput" placeholder="Search products...">
            </div>

            <div class="bank-filter">
              <select id="bankFilter">
                <option value="all">All Banks</option>
                <option value="Kotak">Kotak</option>
                <option value="SBI">SBI</option>
                <option value="Bajaj">Bajaj</option>
                <option value="IDFC">IDFC</option>
              </select>
            </div>

            <div class="category-filter">
              <select id="categoryFilter">
                <option value="all">All Categories</option>
                <option value="VC4">VC4 - Car/Jeep/Van</option>
                <option value="VC4max">VC4max - Car/Jeep/Van</option>
                <option value="VC5">VC5 - LCV</option>
                <option value="VC6">VC6 - Bus/Truck</option>
                <option value="VC7">VC7 - Heavy Vehicle</option>
                <option value="VC8">VC8 - Construction Vehicle</option>
                <option value="VC12">VC12 - Mini Bus</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </section>

<div id="mySidenav" class="sidenav">
    <a href="cart.php" id="cart"><i class="fas fa-shopping-cart"></i>View Cart</a>
</div>

    <section class="products-section">
      <div class="container">
        <div class="products-header-info">
          <h2>Available Products</h2>
          <div class="results-count">
            <span id="resultsCount">0</span> products found
          </div>
        </div>

        <!-- renderer inserts cards here -->
        <div id="products-container" class="products-grid" aria-live="polite"></div>

        <div class="no-results" id="noResults" style="display:none;">
          <i class="fas fa-search"></i>
          <h3>No products found</h3>
          <p>No products match your current filters. Try adjusting your search criteria.</p>
          <button class="btn btn-primary" onclick="clearAllFilters()">Clear All Filters</button>
        </div>
      </div>
    </section>
  
    <!-- Special Notice about kotak vc4 fastag -->
    <section class="special-notice">
        <div class="container">
            <div class="notice-card warning">
                <h3><i class="fas fa-exclamation-triangle"></i> Important Notice:</h3>
                <p><strong>Kotak Bank FASTag</strong> is currently not available for VC4 (Car/Jeep/Van) category. For VC4 vehicles, please choose from SBI, Bajaj, or IDFC banks. Kotak FASTag is available for VC5, VC6, VC7, VC8, and VC12 categories.</p>
            </div>
        </div>
    </section>
</main>
    <script src="script.js"></script>
    <script src="productdb.js"></script>
    <script src="products.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
