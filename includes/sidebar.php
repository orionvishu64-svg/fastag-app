<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<aside id="appSidebar" class="ap-sidebar">
  <nav class="nav flex-column gap-1">

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
      href="dashboard.php">
      <i class="fa fa-home"></i> Home
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>"
      href="profile.php">
      <i class="fas fa-id-badge"></i> Account
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>"
      href="products.php">
      <i class="fa-solid fa-box-open"></i> Products
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>"
      href="cart.php">
      <i class="fas fa-shopping-cart"></i> Cart
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'track_orders.php' ? 'active' : '' ?>"
      href="track_orders.php">
      <i class="fa-solid fa-box"></i> My Orders
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"
      href="about.php">
      <i class="fa fa-info-circle"></i> About Us
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'blog.php' ? 'active' : '' ?>"
      href="blog.php">
      <i class="far fa-image"></i> Blog
    </a>

    <a class="nav-link d-flex align-items-center gap-2
      <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>"
      href="contact.php">
      <i class="fa-solid fa-comment"></i> Help & Support
    </a>

    <hr class="my-2">

    <a class="nav-link d-flex align-items-center gap-2 text-danger"
       href="/../config/logout.php">
      <i class="fa fa-sign-out"></i> Logout
    </a>

  </nav>
</aside>