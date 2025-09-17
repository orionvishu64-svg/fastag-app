<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOTAK FASTag - Vehicle Categories | Apna Payment Services</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="bank-pages.css">
    <link rel="stylesheet" href="products.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-bank="Kotak">
<?php include __DIR__ . '/includes/header.php'; ?>
    <!-- Bank Header -->
    <section class="bank-header kotak-theme">
        <div class="container">
            <div class="bank-header-content">
                <div class="bank-logo-large">
                    <img src="https://images.goodreturns.in/webp/common_dynamic/images/social_share/fastag_18.jpg" alt="Kotak Bank">
                </div>
                <div class="bank-info">
                    <h1>KOTAK FIRST FASTag</h1>
                    <p>Choose your vehicle category for KOTAK FIRST Bank FASTag. Modern banking with customer-first approach.</p>
                    <div class="bank-features">
                        <div class="feature-item">
                            <i class="fas fa-clock"></i>
                            <span>24-hour activation</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Advanced security</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-mobile-alt"></i>
                            <span>FIRST Mobile app</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
    <section class="benefits-section kotak-theme">
        <div class="container">
            <h2>Why Choose KOTAK FIRST FASTag?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-users"></i>
                    <h3>Customer First</h3>
                    <p>Customer-first approach with personalized banking solutions</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>FIRST Mobile</h3>
                    <p>Advanced mobile banking app with comprehensive FASTag management</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-cog"></i>
                    <h3>Modern Technology</h3>
                    <p>Latest technology stack for seamless digital experience</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-headset"></i>
                    <h3>24/7 Support</h3>
                    <p>Round-the-clock customer support with quick resolution</p>
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