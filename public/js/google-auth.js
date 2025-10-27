// /public/js/google-auth.js
// Renders the Google button and posts the ID token to /config/google_login.php

function initGoogle() {
  if (!window.google || !google.accounts || !google.accounts.id) {
    console.error('Google Identity Services not loaded');
    return;
  }

  google.accounts.id.initialize({
    client_id: "451470803008-4elocicg2u7j5ug7m0rutps2k72ln3nh.apps.googleusercontent.com", // your Web client ID
    callback: handleGoogleResponse,
  });

  // Render button into #google-signup (make sure this div exists in the HTML)
  const el = document.getElementById("google-signup");
  if (el) {
    google.accounts.id.renderButton(el, {
      theme: "filled_blue",
      size: "large",
      text: "signup_with",
      shape: "rectangular",
      logo_alignment: "left",
    });
  }
  // One Tap optional (usually requires HTTPS):
  // google.accounts.id.prompt();
}

async function handleGoogleResponse(response) {
  try {
    const res = await fetch('/config/google_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id_token: response.credential })
    });

    // Expect JSON from PHP; if not ok, show message
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      alert((data && data.message) || 'Google sign-in failed.');
      return;
    }

    // Optional: store minimal user snapshot for UI
    if (data.user) {
      try { localStorage.setItem('user', JSON.stringify(data.user)); } catch (_) {}
    }

    window.location.href = data.redirect || '/profile.php';
  } catch (e) {
    console.error(e);
    alert('Network error. Please try again.');
  }
}
