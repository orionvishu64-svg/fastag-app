<?php
require_once __DIR__ . '/../config/common_start.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_name = $_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? 'Guest';
$cart_count = isset($_SESSION['cart_count']) ? (int) $_SESSION['cart_count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apna Payment Services</title>
    <link rel="stylesheet" href="/../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/uploads/images/favicon.ico">
</head>
<body>
<nav class="navbar ap-header fixed-top">
  <div class="container-fluid d-flex align-items-center justify-content-between">

    <!-- LEFT: Sidebar toggle + Logo -->
    <div class="d-flex align-items-center gap-3">
      <button id="sidebarToggle"
              class="btn btn-outline-secondary btn-sm"
              aria-label="Open sidebar">
        ☰
      </button>

      <a href="dashboard.php" class="navbar-brand m-0">
        <img
          src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
          alt="ApnaPayment"
          height="40"
        />
      </a>
    </div>

    <!-- RIGHT -->
    <div class="d-flex align-items-center gap-2 gap-md-3 flex-nowrap">

      <!-- Greeting (desktop only) -->
      <a href="profile.php"
         class="text-decoration-none text-dark fw-semibold d-none d-md-inline">
          Hello, <?= htmlspecialchars($user_name ?? 'Guest'); ?>
      </a>

      <!-- Cart -->
      <a href="cart.php"
         class="position-relative text-decoration-none fs-5 text-dark">
          🛒
            <?php if (!empty($cart_count)): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
              <?= (int)$cart_count ?>
              </span>
            <?php endif; ?>
      </a>

      <!-- Help -->
      <a href="contact.php"
         class="text-decoration-none text-dark fs-5">
          <i class="far fa-comments"></i>
      </a>
    </div>
  </div>

  <?php include __DIR__ . '/sidebar.php'; ?>
</nav>
<script>
(function () {
  'use strict';

  // ---------- AUTH CHECK ----------
  (async function(){
    try {
      const res = await fetch('/config/check_login.php', {
        credentials: 'same-origin',
        cache: 'no-store'
      });

      if (res.ok) {
        const json = await res.json();
        if (json.logged_in) {
          localStorage.setItem('app_logged_in', '1');
          if (json.name) localStorage.setItem('app_user', json.name);
        } else {
          localStorage.removeItem('app_logged_in');
          localStorage.removeItem('app_user');
        }
      }
    } catch (e) {
      console.warn('Auth check failed', e);
    }
  })();

})();
</script>