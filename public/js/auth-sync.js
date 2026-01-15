// public/js/auth-sync.js
(function () {
  const BASE =
    location.pathname.indexOf("/fastag_website/") === 0
      ? "/fastag_website"
      : "";
  const CHECK_URL = BASE + "/config/check_login.php";

  const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 min
  const FOCUS_DEBOUNCE_MS = 300;

  function safeGet(key) {
    try {
      return localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }
  function safeSet(key, val) {
    try {
      localStorage.setItem(key, val);
    } catch (e) {}
  }
  function safeRemove(key) {
    try {
      localStorage.removeItem(key);
    } catch (e) {}
  }

  function isClientLoggedIn() {
    return safeGet("app_logged_in") === "1";
  }
  function setClientLoggedIn(v) {
    safeSet("app_logged_in", v ? "1" : "0");
    document.documentElement.dataset.loggedIn = v ? "1" : "0";
  }
  function clearClientAuth() {
    setClientLoggedIn(false);
    safeRemove("app_user_name");
    try {
      delete document.documentElement.dataset.userName;
    } catch (e) {}
  }

  let keepAliveTimer = null;
  function startKeepAlive() {
    if (keepAliveTimer) return;
    keepAliveTimer = setInterval(fetchAuth, POLL_INTERVAL_MS);
  }
  function stopKeepAlive() {
    if (keepAliveTimer) {
      clearInterval(keepAliveTimer);
      keepAliveTimer = null;
    }
  }

  let lastServerLoggedIn = null;

  async function fetchAuth() {
    try {
      const res = await fetch(CHECK_URL, {
        credentials: "include",
        cache: "no-store",
      });
      if (!res.ok) {
        handleServerState(false, null);
        return;
      }
      const data = await res.json();

      const serverLoggedIn = !!(data && data.logged_in);
      const uid = serverLoggedIn && data.user ? data.user.id : null;
      const name = serverLoggedIn && data.user ? data.user.name : null;

      setClientLoggedIn(serverLoggedIn);
      if (serverLoggedIn && name) {
        safeSet("app_user_name", name);
        document.documentElement.dataset.userName = name;
      } else {
        safeRemove("app_user_name");
        try {
          delete document.documentElement.dataset.userName;
        } catch (e) {}
      }

      handleServerState(serverLoggedIn, { id: uid, name });
    } catch (e) {
      console.warn("check_login failed", e);
    }
  }

  // emit events only when server state changes
  function handleServerState(serverLoggedIn, user) {
    if (lastServerLoggedIn === null) {
      lastServerLoggedIn = serverLoggedIn;
      if (serverLoggedIn) {
        window.dispatchEvent(new CustomEvent("app:login", { detail: user }));
        startKeepAlive();
      } else {
        window.dispatchEvent(new CustomEvent("app:logout"));
        stopKeepAlive();
      }
      return;
    }

    if (serverLoggedIn && !lastServerLoggedIn) {
      lastServerLoggedIn = true;
      window.dispatchEvent(new CustomEvent("app:login", { detail: user }));
      startKeepAlive();
    } else if (!serverLoggedIn && lastServerLoggedIn) {
      lastServerLoggedIn = false;
      window.dispatchEvent(new CustomEvent("app:logout"));
      clearClientAuth();
      stopKeepAlive();
    }
  }

  let focusTimer = null;
  window.addEventListener("focus", () => {
    if (focusTimer) clearTimeout(focusTimer);
    focusTimer = setTimeout(fetchAuth, FOCUS_DEBOUNCE_MS);
  });

  window.AppAuthSync = {
    reconcileAuth: fetchAuth,
    setClientLoggedIn,
    clearClientAuth,
  };

  fetchAuth();
})();
