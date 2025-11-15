<?php
require_once __DIR__ . '/../config/common_start.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Read user name and cart count from session (safe defaults)
$user_name = $_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? 'Guest';
$cart_count = isset($_SESSION['cart_count']) ? (int) $_SESSION['cart_count'] : 0;
?>

<!-- Preloader -->
<div id="preloader" aria-hidden="false" aria-busy="true">
  <div id="ctn-preloader" class="ctn-preloader" role="status" aria-label="Loading, getting your Fastag">
    <div class="logo-spinner" aria-hidden="true">
      <div class="spinner-ring" aria-hidden="true"></div>
      <img
        src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
        alt="ApnaPayment"
        class="preloader-logo"
        width="140" height="140"
        loading="eager"
      />
    </div>

    <h3 class="head">Get Your Fastag</h3>
    <p class="preloader-fact">Fast, secure and instant activation</p>
  </div>
</div>

<nav class="navbar">
  <div class="nav-container">
    <!-- Left group: hamburger + Logo -->
    <div class="nav-left">
      <button id="sidebarToggle" class="sidebar-toggle" aria-label="Open sidebar" aria-expanded="false">
        <span class="hamburger" aria-hidden="true"><span></span><span></span><span></span></span>
      </button>
      <div class="nav-logo">
        <a href="dashboard.php" title="Dashboard" aria-label="Apna Payment">
        <!-- Dark theme logo (white) -->
          <img class="logo-dark"
             src="https://www.apnapayment.com/website/img/logo/ApnaPayment200White.png"
             alt="ApnaPayment" height="70">

        <!-- Light theme logo (black) -->
          <img class="logo-light"
             src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
             alt="ApnaPayment" height="70">
        </a>
      </div>
    </div>

    <!-- Right group: greeting, cart, help -->
    <div class="nav-right">
      <a href="profile.php" class="nav-greeting">
        Hello, <strong><?php echo htmlspecialchars($user_name ?? 'Guest'); ?></strong>
      </a>

      <div class="nav-actions">
        <!-- THEME TOGGLE BUTTON (integrated) -->
        <button id="theme-toggle" class="theme-toggle" aria-pressed="false" title="Toggle light / dark theme" type="button">
          <span class="theme-icon" aria-hidden="true">
            <!-- moon icon (shown when dark) -->
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" fill="currentColor"/>
            </svg>
            <!-- sun icon (shown when light) -->
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none">
              <path d="M12 4V2M12 22v-2M4.93 4.93L3.51 3.51M20.49 20.49l-1.42-1.42M2 12H4M20 12h2M4.93 19.07l-1.42 1.42M20.49 3.51l-1.42 1.42" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="3" fill="currentColor"/>
            </svg>
          </span>
          <span class="theme-label">Light</span>
        </button>

        <a href="cart.php" class="nav-cart" data-count="<?php echo $cart_count ?? 0; ?>" title="Cart" aria-label="Cart">🛒</a>
        <a href="contact.php" class="nav-help" title="Help" aria-label="Help"><i class="fa fa-comments" aria-hidden="true"></i> Help</a>
      </div>
    </div>
  </div>

  <!-- Sidebar include stays the same -->
  <?php include __DIR__ . '/sidebar.php'; ?>
</nav>
<style>
.theme-toggle{
  display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;
  background:transparent;border:1px solid var(--border-1);color:var(--text);
  transition: background .18s ease, color .18s ease, transform .12s ease;
}
.theme-toggle:hover{ transform: translateY(-2px); background: rgba(255,255,255,0.02); }
.theme-toggle .theme-icon svg{ width:18px; height:18px; display:block; color:var(--accent-warm); }
.theme-toggle .theme-label{ font-size:13px; }
@media (max-width:480px){ .theme-toggle .theme-label{ display:none; } }
.theme-light .theme-toggle{ border:1px solid var(--border-1); color:var(--text); background: rgba(0,0,0,0.02); }
</style>

<script>
(function () {
  'use strict';

  // ---------- PRELOADER ----------
  try {
    const preloader = document.getElementById('preloader');

    if (preloader) {
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
      try {
        p.setAttribute('aria-busy', 'false');
        p.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('preloader-active');
        document.body.classList.remove('preloader-active');
      } catch(e){ /* ignore */ }
      p.classList.add('preloader-hidden');
      setTimeout(() => { try { p.remove(); } catch (e) {} }, 420);
    }

    setTimeout(hidePreloaderGracefully, HIDE_MS);

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

  // ---------- HEADER FIX ----------
  try {
    const header = document.querySelector('.header');
    if (header) {
      header.style.position = 'fixed';
      header.style.top = '0';
      header.style.left = '0';
      header.style.right = '0';
      header.style.zIndex = '99998';
      header.classList.add('header-fixed-by-script');
    }

    function syncBodyPaddingForHeader() {
      const h = document.querySelector('.header');
      if (!h) return;
      const height = h.getBoundingClientRect().height;
      document.body.style.paddingTop = height + 'px';
    }
    syncBodyPaddingForHeader();
    window.addEventListener('resize', function () {
      clearTimeout(window.__headerPaddingTimeout);
      window.__headerPaddingTimeout = setTimeout(syncBodyPaddingForHeader, 100);
    });
    document.addEventListener('DOMContentLoaded', syncBodyPaddingForHeader);
    window.addEventListener('load', syncBodyPaddingForHeader);
  } catch (err) {
    console.error('Header layout fix error:', err);
  }

  // ---------- AUTH CHECK (unchanged) ----------
  (async function(){
    try {
      const res = await fetch('/config/check_login.php', { credentials: 'same-origin', cache: 'no-store' });
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

    // ---------- THEME INIT & TOGGLE (UI sync) ----------
    const root = document.documentElement;
    const KEY = 'site-theme'; // 'dark' or 'light'
    const btn = document.getElementById('theme-toggle');

    // core setTheme function (exposed later)
    function setTheme(isLight){
  const root = document.documentElement;
  if (isLight) {
    root.classList.add('theme-light');            // keep existing class (optional)
    root.setAttribute('data-theme', 'light');     // add attribute for CSS
    localStorage.setItem(KEY, 'light');
  } else {
    root.classList.remove('theme-light');
    root.removeAttribute('data-theme');           // remove attribute
    localStorage.setItem(KEY, 'dark');
  }
  syncToggleUI();
}


    // read stored preference and apply
    const stored = localStorage.getItem(KEY);
    if (stored === 'light') {
      root.classList.add('theme-light');
    } else {
      root.classList.remove('theme-light');
    }

    // UI sync helper: updates icons/label/aria-pressed
    function syncToggleUI(){
      if(!btn) return;
      const isLight = root.classList.contains('theme-light');
      const iconMoon = btn.querySelector('.icon-moon');
      const iconSun  = btn.querySelector('.icon-sun');
      const label    = btn.querySelector('.theme-label');
      if(iconMoon) iconMoon.style.display = isLight ? 'none' : 'block';
      if(iconSun)  iconSun.style.display  = isLight ? 'block' : 'none';
      if(label)    label.textContent      = isLight ? 'Light' : 'Dark';
      btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
    }

    // attach click handler to toggle
    if (btn) {
      btn.addEventListener('click', function(e){
        const currentlyLight = root.classList.contains('theme-light');
        setTheme(!currentlyLight);
      });
      // keyboard accessibility
      btn.addEventListener('keydown', function(e){
        if(e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    }

    // expose helpers (if other scripts use toggleTheme/setTheme)
    window.toggleTheme = function(){ setTheme(!root.classList.contains('theme-light')); };
    window.setTheme = setTheme;

    // final sync to update the button UI on load
    syncToggleUI();

  })();

})(); // end main IIFE
</script>
