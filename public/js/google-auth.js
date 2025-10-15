// google-auth.js
// Load when Google script is ready
window.onload = function () {
  google.accounts.id.initialize({
    client_id: "#",
    callback: handleGoogleResponse
  });

  const googleSignup = document.getElementById("google-signup");
  const googleLogin = document.getElementById("google-login");

  if (googleSignup) {
    google.accounts.id.renderButton(googleSignup, {
      theme: "outline",
      size: "large",
      text: "continue_with",
      shape: "rectangular",
    });
  }

  if (googleLogin) {
    google.accounts.id.renderButton(googleLogin, {
      theme: "outline",
      size: "large",
      text: "continue_with",
      shape: "rectangular",
    });
  }
};

// Safe fetch helper (falls back to window.fetch)
async function safeFetch(url, opts = {}) {
  opts.credentials = opts.credentials || 'include';
  try {
    return await fetch(url, opts);
  } catch (e) {
    // Re-throw so callers can handle
    throw e;
  }
}

// Google callback
async function handleGoogleResponse(response) {
  try {
    const decoded = parseJwt(response.credential);
    const name = decoded.name;
    const email = decoded.email;

    // Determine page type
    const isSignup = !!document.getElementById("google-signup");
    const endpoint = isSignup ? "/config/register.php" : "/config/login.php";

    const res = await safeFetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        name: name,
        email: email,
        login_type: "google"
      }),
    });

    if (!res.ok) {
      // try to read JSON error if present
      let errText = 'Google login failed';
      try { const t = await res.json(); if (t && t.error) errText = t.error; } catch(_) {}
      alert(errText);
      return;
    }

    const data = await res.json();

    if (!data.success) {
      alert(data.message || 'Google login failed.');
      return;
    }

    // store a lightweight user snapshot for UI (not source-of-truth)
    const userToStore = data.user || { email, name };
    try { localStorage.setItem("user", JSON.stringify(userToStore)); } catch (e) {}

    // Let client-side listeners know we've logged in
    try { window.dispatchEvent(new CustomEvent('app:login', { detail: data.user || userToStore })); } catch (e) {}

    // If server tells us partner form is required, redirect there; otherwise go to dashboard.
    // The server may return `partner_required: true` (as in your /config/login.php).
    const redirectTo = (data.partner_required ? "partner_form.php" : (data.redirect || "dashboard.php"));
    // Use top-level navigation when possible
    if (window.top && window.top !== window) {
      window.top.location.href = redirectTo;
    } else {
      window.location.href = redirectTo;
    }

  } catch (err) {
    console.error('Google login error', err);
    alert("Google login failed.");
  }
}

// Decode Google JWT
function parseJwt(token) {
  const base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
  const json = decodeURIComponent(atob(base64).split('').map(c =>
    '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join('')
  );
  return JSON.parse(json);
}