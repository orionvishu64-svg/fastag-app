// js/auth-sync.js
(function () {
  // If your site runs under /fastag_website/, you can use just 'check_login.php'
  const CHECK_URL = '/check_login.php';
  const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 min
  const FOCUS_DEBOUNCE_MS = 300;

  function isClientLoggedIn() {
    try { return localStorage.getItem('app_logged_in') === '1'; } catch (e) { return false; }
  }
  function setClientLoggedIn(v) {
    try { localStorage.setItem('app_logged_in', v ? '1' : '0'); } catch (e) {}
    document.documentElement.dataset.loggedIn = v ? '1' : '0';
  }
  function clearClientAuth() {
    setClientLoggedIn(false);
    try { localStorage.removeItem('app_user_name'); } catch (e) {}
  }

  let keepAliveTimer = null;
  function startKeepAlive() {
    if (keepAliveTimer) return;
    keepAliveTimer = setInterval(fetchAuth, POLL_INTERVAL_MS);
  }
  function stopKeepAlive() {
    if (keepAliveTimer) { clearInterval(keepAliveTimer); keepAliveTimer = null; }
  }

  async function fetchAuth() {
    try {
      const res = await fetch(CHECK_URL, { credentials: 'include' });
      const data = await res.json();

      const serverLoggedIn = !!(data && data.logged_in);
      const uid  = serverLoggedIn && data.user ? data.user.id   : null;
      const name = serverLoggedIn && data.user ? data.user.name : null;

      setClientLoggedIn(serverLoggedIn);
      if (serverLoggedIn && name) {
        try { localStorage.setItem('app_user_name', name); } catch (e) {}
        document.documentElement.dataset.userName = name;
      }
    } catch (e) {
      console.warn('check_login failed', e);
    }
  }

  let focusTimer = null;
  window.addEventListener('focus', () => {
    if (focusTimer) clearTimeout(focusTimer);
    focusTimer = setTimeout(fetchAuth, FOCUS_DEBOUNCE_MS);
  });

  fetchAuth();
  startKeepAlive();

  window.addEventListener('app:login',  () => { setClientLoggedIn(true);  startKeepAlive();  });
  window.addEventListener('app:logout', () => { clearClientAuth();        stopKeepAlive();   });

  window.AppAuthSync = { reconcileAuth: fetchAuth, setClientLoggedIn, clearClientAuth };
})();
