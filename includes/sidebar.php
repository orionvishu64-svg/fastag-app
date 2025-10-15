<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<aside class="sidebar">
<nav>
    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"><i class="fa fa-home"></i> Dashboard</a>
    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>"><i class="fas fa-id-badge"></i> Personal Details</a>
    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Products</a>
    <a href="cart.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Cart</a>
    <a href="track_orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'track_orders.php' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i>Track Orders</a>
    <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>"><i class="fa-solid fa-comment"></i> Contact Us</a>
    <a href="blog.php" class="<?= basename($_SERVER['PHP_SELF']) === 'blog.php' ? 'active' : '' ?>"><i class="far fa-image"></i> Blog</a>
    <a href="about.php" class="<?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"><i class="fa fa-info-circle"></i> About US</a>
    <a id="nav-logout" href="/config/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>
</aside>