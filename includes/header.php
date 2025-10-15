<?php
require_once __DIR__ . '/../config/common_start.php'; 
// Ensure session is started before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read user name and cart count from session (safe defaults)
$user_name = $_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? 'Guest';
$cart_count = isset($_SESSION['cart_count']) ? (int) $_SESSION['cart_count'] : 0;
?>

 <!-- Put preloader at the very first element inside <body> -->
<div id="preloader" aria-hidden="false">
  <div id="ctn-preloader" class="ctn-preloader" role="status" aria-label="Loading">
    <div class="round_spinner">
      <div class="spinner" aria-hidden="true"></div>
      <div class="text">
        <!-- Use a small logo here. Avoid loading="lazy" for this image -->
        <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
             alt="ApnaPayment" class="preloader-logo" />
      </div>
    </div>
    <h3 class="head" style="color: #222222ff">Get Your Fastag</h3>
    <p class="preloader-fact"></p>
  </div>
</div>

 <nav class="navbar">
  <div class="nav-container">
    <!-- Left side: Logo -->
    <div class="nav-logo">
      <a href="dashboard.php">
        <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200White.png" alt="ApnaPayment">
      </a>
    </div>
  </div>

  <!-- Toolbar row under logo -->
  <div class="nav-toolbar">
    <!-- Hamburger -->
    <button id="sidebarToggle" class="sidebar-toggle" aria-label="Open sidebar" aria-expanded="false">
      <span class="hamburger" aria-hidden="true">
        <span></span><span></span><span></span>
      </span>
    </button>

    <!-- Right side -->
    <div class="nav-right">
      <div class="nav-greeting">
        Hello, <strong><?php echo htmlspecialchars($user_name ?? 'Guest'); ?></strong>
      </div>
      <a href="cart.php" class="nav-cart" data-count="<?php echo $cart_count ?? 0; ?>">Cart 🛒</a>
    </div>
  </div>

  <!-- Sidebar -->
  <?php include __DIR__ . '/sidebar.php'; ?>
</nav>

<script>
/* Robust preloader + header offset fix
   Replace old fragile app.js preloader code with this.
   Put this file after other libs (jquery/bootstrap) in footer.php before </body>.
*/
(function () {
  'use strict';

  // ---------- PRELOADER ----------
  try {
    const preloader = document.getElementById('preloader');

    if (preloader) {
      // Ensure visible immediately
      document.documentElement.classList.add('preloader-active');
      document.body.classList.add('preloader-active');
      preloader.style.display = 'flex';
      preloader.style.opacity = '1';
      preloader.style.visibility = 'visible';
      preloader.style.pointerEvents = 'auto';
    }

    const HIDE_MS = 2000; // always hide after 2s
    const FORCED_FALLBACK_MS = HIDE_MS + 600;

    function hidePreloaderGracefully() {
      const p = document.getElementById('preloader');
      if (!p) return;
      // add class for CSS transition
      p.classList.add('preloader-hidden');

      // also set inline styles to override stubborn CSS
      p.style.opacity = '0';
      p.style.visibility = 'hidden';
      p.style.pointerEvents = 'none';

      // remove after transition
      setTimeout(() => {
        try { p.remove(); } catch (e) { /* ignore */ }
        document.documentElement.classList.remove('preloader-active');
        document.body.classList.remove('preloader-active');
        console.info('Preloader removed (graceful).');
      }, 420);
    }

    // schedule hide
    setTimeout(hidePreloaderGracefully, HIDE_MS);

    // final forced removal fallback
    setTimeout(() => {
      const p = document.getElementById('preloader');
      if (p) {
        try { p.remove(); } catch (e) {}
        document.documentElement.classList.remove('preloader-active');
        document.body.classList.remove('preloader-active');
        console.warn('Preloader forced removal (fallback).');
      }
    }, FORCED_FALLBACK_MS);

  } catch (err) {
    console.error('Preloader script fatal error:', err);
  }

  // ---------- HEADER FIX: make header fixed + reserve space ----------
  try {
    const header = document.querySelector('.header');
    if (header) {
      // ensure header is fixed so it stays on top
      header.style.position = 'fixed';
      header.style.top = '0';
      header.style.left = '0';
      header.style.right = '0';
      header.style.zIndex = '99998'; // below preloader (preloader uses 999999 in CSS)
      // add a class to mark it's fixed (for future CSS)
      header.classList.add('header-fixed-by-script');
    }

    // function to update body padding-top to equal header height
    function syncBodyPaddingForHeader() {
      const h = document.querySelector('.header');
      if (!h) return;
      // compute header's rendered height
      const height = h.getBoundingClientRect().height;
      // apply to body as padding-top to avoid content jumping under header
      document.body.style.paddingTop = height + 'px';
    }

    // run now and on resize
    syncBodyPaddingForHeader();
    window.addEventListener('resize', function () {
      // small debounce
      clearTimeout(window.__headerPaddingTimeout);
      window.__headerPaddingTimeout = setTimeout(syncBodyPaddingForHeader, 100);
    });

    // Also run after DOMContentLoaded in case header images/fonts change height
    document.addEventListener('DOMContentLoaded', syncBodyPaddingForHeader);
    // And after window.load for any late images
    window.addEventListener('load', syncBodyPaddingForHeader);

  } catch (err) {
    console.error('Header layout fix error:', err);
  }

})();

(async function(){
  try {
    const res = await fetch('/../config/check_login.php', { credentials: 'same-origin', cache: 'no-store' });
    if (res.ok) {
      const json = await res.json();
      if (json.logged_in) {
        // server says you're logged in -> keep local UI
        localStorage.setItem('app_logged_in', '1');
        // optionally set user display name
        if (json.name) localStorage.setItem('app_user', json.name);
      } else {
        // server says you're not logged in -> clear local UI state
        localStorage.removeItem('app_logged_in');
        localStorage.removeItem('app_user');
        // ensure profile links show login overlay instead of direct access
      }
    }
  } catch (e) {
    // network error - keep local UI but prevent protected actions until server reachable
    console.warn('Auth check failed', e);
  }
})();
</script>