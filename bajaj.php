<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bajaj FASTag - Vehicle Categories | Apna Payment Services</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="bank-pages.css">
    <link rel="stylesheet" href="products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-bank="Bajaj">
<?php include __DIR__ . '/includes/header.php'; ?>
    <!-- Bank Header -->
    <section class="bank-header bajaj-theme">
        <div class="container">
            <div class="bank-header-content">
                <div class="bank-logo-large">
                    <img loading="lazy" src="https://www.easemydeal.com/assets/image/recharge/fastag-recharge-inditab-min.jpg" alt="Bajaj Finserv">
                </div>
                <div class="bank-info">
                    <h1>Bajaj FASTag</h1>
                    <p>Choose your vehicle category for Bajaj Finserv FASTag. Quick processing and instant digital delivery.</p>
                    <div class="bank-features">
                        <div class="feature-item">
                            <i class="fas fa-clock"></i>
                            <span>Instant activation</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Digital first</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-mobile-alt"></i>
                            <span>Bajaj Finserv app</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<div id="mySidenav" class="sidenav">
    <a href="cart.php" id="cart"><i class="fas fa-shopping-cart"></i>View Cart</a>
</div>

    <!-- Vehicle Categories -->
    <section class="products-section">
      <div class="container">
        <div class="products-header-info">
          <h2>Select Your Vehicle Category</h2>
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

    <!-- Bank Benefits -->
    <section class="benefits-section bajaj-theme">
        <div class="container">
            <h2>Why Choose Bajaj FASTag?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-rocket"></i>
                    <h3>Digital First</h3>
                    <p>Completely digital process from application to delivery</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Bajaj Finserv App</h3>
                    <p>Manage your FASTag through the comprehensive Bajaj Finserv app</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Quick Processing</h3>
                    <p>Fastest processing time with instant digital delivery</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-star"></i>
                    <h3>Premium Service</h3>
                    <p>Premium customer service with dedicated support team</p>
                </div>
            </div>
        </div>
    </section>
    <script src="productdb.js"></script>
<script src="products.js"></script>
<script src="bank-pages.js"></script>   <!-- if bank page needs it -->
<script src="script.js"></script>       <!-- global utilities -->
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>