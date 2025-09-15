<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<aside class="sidebar">
<nav>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"> <i class="fa fa-home"></i>Dashboard</a>
    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>"> <i class="fas fa-id-badge">Personal Details</a>
    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>"> <i class="fas fa-money-check">Products</a>
    <a href="cart.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>"> <i class="fas fa-shopping-cart"></i> Cart</a>
    <a href="track_orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'track_orders.php' ? 'active' : '' ?>"> <i class="">Track Orders</a>
    <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>"> <i class="fa fa-home">Contact Us</a>
    <a href="blog.php" class="<?= basename($_SERVER['PHP_SELF']) === 'blog.php' ? 'active' : '' ?>"> <i class="fa fa-home">Blog</a>
    <a href="about.php" class="<?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"> <i class="fa fa-home">About US</a>
    <a href="logout.php">Logout</a>
</nav>
</aside>