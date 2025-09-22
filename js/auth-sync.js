// auth-sync.js
// Include on pages that maintain client-side "logged in" state.
// Usage: <script src="/js/auth-sync.js"></script>

(function () {
  const CHECK_URL = '/check_login.php';
  const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 minutes keep-alive (adjust)
  const FOCUS_DEBOUNCE_MS = 300; // small debounce on focus events

  // helpers to manage client-side auth flag
  function isClientLoggedIn() {
    try { return localStorage.getItem('app_logged_in') === '1'; }
    catch (e) { return false; }
  }
  function setClientLoggedIn(v) {
    try { localStorage.setItem('app_logged_in', v ? '1' : '0'); }
    catch (e) { /* ignore storage errors */ }
  }
  function clearClientAuth() {
    try {
      localStorage.removeItem('app_logged_in');
      localStorage.removeItem('app_user'); // remove any application user data you store
    } catch (e) {}
  }

  // call server to reconcile
  async function checkServerAuth() {
    try {
      const res = await fetch(CHECK_URL, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) {
        // if server returns 401/403, treat as logged out
        if (res.status === 401 || res.status === 403) {
          return { logged_in: false };
        }
        // network error - don't change client state
        return null;
      }
      const json = await res.json();
      return json;
    } catch (err) {
      console.warn('checkServerAuth failed', err);
      return null;
    }
  }

  // reconcile client with server
  async function reconcileAuth() {
    const server = await checkServerAuth();
    if (server === null) return; // couldn't reach server — keep local state
    if (server.logged_in) {
      // server says ok — ensure localStorage reflects this
      setClientLoggedIn(true);
      // optionally store simple user info (non-sensitive)
      if (server.name) {
        try { localStorage.setItem('app_user', server.name); } catch (e) {}
      }
    } else {
      // server says logged out — clear client state and update UI
      clearClientAuth();
      // optional UI handling: redirect to login or show overlay
      if (window.location.pathname.indexOf('login') === -1) {
        // redirect to login page (uncomment if desired)
        // window.location.href = '/login.php';
        // or if you have an overlay function, call it:
        if (typeof showLoginOverlay === 'function') showLoginOverlay();
      }
    }
  }

  // keep session alive while user is active (light ping)
  let keepAliveTimer = null;
  function startKeepAlive() {
    if (keepAliveTimer) return;
    keepAliveTimer = setInterval(() => {
      fetch(CHECK_URL, { credentials: 'same-origin', cache: 'no-store' }).catch(() => {});
    }, POLL_INTERVAL_MS);
  }
  function stopKeepAlive() {
    if (!keepAliveTimer) return;
    clearInterval(keepAliveTimer);
    keepAliveTimer = null;
  }

  // On focus, reconcile quickly (debounced)
  let focusTimer = null;
  window.addEventListener('focus', () => {
    if (focusTimer) clearTimeout(focusTimer);
    focusTimer = setTimeout(() => {
      reconcileAuth();
      startKeepAlive();
    }, FOCUS_DEBOUNCE_MS);
  });

  // On blur (or when user navigates away), stop keep-alive
  window.addEventListener('blur', () => {
    stopKeepAlive();
  });

  // Also reconcile when page becomes visible after being hidden (tab switch)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      reconcileAuth();
      startKeepAlive();
    } else {
      stopKeepAlive();
    }
  });

  // At load, reconcile once
  window.addEventListener('load', () => {
    reconcileAuth();
    // start keepAlive only if client thinks it's logged in
    if (isClientLoggedIn()) startKeepAlive();
  });

  // Optional: listen for explicit login/logout events and keep localStorage updated.
  window.addEventListener('app:login', (e) => {
    setClientLoggedIn(true);
    if (e.detail && e.detail.name) {
      try { localStorage.setItem('app_user', e.detail.name); } catch (err) {}
    }
    startKeepAlive();
  });
  window.addEventListener('app:logout', () => {
    clearClientAuth();
    stopKeepAlive();
  });

  // Expose small API
  window.AppAuthSync = {
    reconcileAuth,
    setClientLoggedIn,
    clearClientAuth
  };

})();
