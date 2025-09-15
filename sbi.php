<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SBI FASTag - Vehicle Categories | Apna Payment Services</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="bank-pages.css">
    <link rel="stylesheet" href="products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-bank="SBI">
<?php include __DIR__ . '/includes/header.php'; ?>
    <!-- Bank Header -->
    <section class="bank-header sbi-theme">
        <div class="container">
            <div class="bank-header-content">
                <div class="bank-logo-large">
                    <img src="https://images.goodreturns.in/webp/common_dynamic/images/social_share/fastag_32.jpg" alt="SBI Bank">
                </div>
                <div class="bank-info">
                    <h1>SBI FASTag</h1>
                    <p>Choose your vehicle category for State Bank of India FASTag. India's largest bank with nationwide acceptance.</p>
                    <div class="bank-features">
                        <div class="feature-item">
                            <i class="fas fa-clock"></i>
                            <span>24-hour activation</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure payments</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-mobile-alt"></i>
                            <span>YONO app integration</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="mySidenav" class="sidenav">
    <a href="cart.php" id="cart"><i class="fas fa-shopping-cart"></i> View Cart</a>
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
    <section class="benefits-section sbi-theme">
        <div class="container">
            <h2>Why Choose SBI FASTag?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-university"></i>
                    <h3>India's Largest Bank</h3>
                    <p>State Bank of India is the country's largest public sector bank</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>YONO Integration</h3>
                    <p>Seamless integration with SBI's YONO app for easy management</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-network-wired"></i>
                    <h3>Nationwide Network</h3>
                    <p>Extensive branch and ATM network across India</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-award"></i>
                    <h3>Trusted Brand</h3>
                    <p>Over 200 years of banking excellence and customer trust</p>
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
