<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<aside class="sidebar">
<nav>
    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"><i class="fa fa-home"></i> Home</a>
    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>"><i class="fas fa-id-badge"></i> Account</a>
    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Products</a>
    <a href="cart.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Cart</a>
    <a href="track_orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'track_orders.php' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i> My Orders</a>
    <a href="about.php" class="<?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"><i class="fa fa-info-circle"></i> About US</a>
    <a href="blog.php" class="<?= basename($_SERVER['PHP_SELF']) === 'blog.php' ? 'active' : '' ?>"><i class="far fa-image"></i> Blog</a>
    <a href="faqs.php" class="<?= basename($_SERVER['PHP_SELF']) === 'faqs.php' ? 'active' : '' ?>"><i class="fa-solid fa-question-circle"></i> FAQs</a>
    <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>"><i class="fa-solid fa-comment"></i> Help & Support</a>
    <a id="nav-logout" href="/../config/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>
</aside>