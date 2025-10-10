// js/auth-sync.js
(function () {
  // Auto-detect base path: if your site runs under /fastag_website/ keep that,
  // otherwise CHECK_URL will fall back to '/check_login.php'.
  const BASE = (location.pathname.indexOf('/fastag_website/') === 0) ? '/fastag_website' : '';
  const CHECK_URL = BASE + '/check_login.php';

  const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 min
  const FOCUS_DEBOUNCE_MS = 300;

  function safeGet(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }
  function safeSet(key, val) {
    try { localStorage.setItem(key, val); } catch (e) {}
  }
  function safeRemove(key) {
    try { localStorage.removeItem(key); } catch (e) {}
  }

  function isClientLoggedIn() {
    return safeGet('app_logged_in') === '1';
  }
  function setClientLoggedIn(v) {
    safeSet('app_logged_in', v ? '1' : '0');
    document.documentElement.dataset.loggedIn = v ? '1' : '0';
  }
  function clearClientAuth() {
    setClientLoggedIn(false);
    safeRemove('app_user_name');
    try { delete document.documentElement.dataset.userName; } catch (e) {}
  }

  let keepAliveTimer = null;
  function startKeepAlive() {
    if (keepAliveTimer) return;
    keepAliveTimer = setInterval(fetchAuth, POLL_INTERVAL_MS);
  }
  function stopKeepAlive() {
    if (keepAliveTimer) { clearInterval(keepAliveTimer); keepAliveTimer = null; }
  }

  // remember last server state so we only emit events on change
  let lastServerLoggedIn = null;

  async function fetchAuth() {
    try {
      const res = await fetch(CHECK_URL, { credentials: 'include', cache: 'no-store' });
      if (!res.ok) {
        // treat non-2xx as logged out (but don't blow up)
        handleServerState(false, null);
        return;
      }
      const data = await res.json();

      const serverLoggedIn = !!(data && data.logged_in);
      const uid  = serverLoggedIn && data.user ? data.user.id   : null;
      const name = serverLoggedIn && data.user ? data.user.name : null;

      // update client-visible state
      setClientLoggedIn(serverLoggedIn);
      if (serverLoggedIn && name) {
        safeSet('app_user_name', name);
        document.documentElement.dataset.userName = name;
      } else {
        safeRemove('app_user_name');
        try { delete document.documentElement.dataset.userName; } catch (e) {}
      }

      handleServerState(serverLoggedIn, { id: uid, name });

    } catch (e) {
      console.warn('check_login failed', e);
      // don't change logged-in state on transient network errors
    }
  }

  // emit events only when server state changes
  function handleServerState(serverLoggedIn, user) {
    if (lastServerLoggedIn === null) {
      // first-run: just set value and emit if logged in
      lastServerLoggedIn = serverLoggedIn;
      if (serverLoggedIn) {
        window.dispatchEvent(new CustomEvent('app:login', { detail: user }));
        startKeepAlive();
      } else {
        window.dispatchEvent(new CustomEvent('app:logout'));
        stopKeepAlive();
      }
      return;
    }

    if (serverLoggedIn && !lastServerLoggedIn) {
      // became logged in
      lastServerLoggedIn = true;
      window.dispatchEvent(new CustomEvent('app:login', { detail: user }));
      startKeepAlive();
    } else if (!serverLoggedIn && lastServerLoggedIn) {
      // became logged out
      lastServerLoggedIn = false;
      window.dispatchEvent(new CustomEvent('app:logout'));
      clearClientAuth();
      stopKeepAlive();
    }
    // no change -> do nothing
  }

  let focusTimer = null;
  window.addEventListener('focus', () => {
    if (focusTimer) clearTimeout(focusTimer);
    focusTimer = setTimeout(fetchAuth, FOCUS_DEBOUNCE_MS);
  });

  // Expose manual reconcile for other scripts
  window.AppAuthSync = {
    reconcileAuth: fetchAuth,
    setClientLoggedIn,
    clearClientAuth
  };

  // initial check
  fetchAuth();
  // keepalive will be started after first successful login detection
})();
