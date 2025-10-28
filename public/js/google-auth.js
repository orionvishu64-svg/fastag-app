// /public/js/google-auth.js
// Renders the Google button and posts the ID token to /config/google_login.php

function initGoogle() {
  if (!window.google || !google.accounts || !google.accounts.id) {
    console.error('Google Identity Services not loaded');
    return;
  }

  google.accounts.id.initialize({
    client_id: "305867100147-ifebl6o2q5kqqrcauc6vv9t5n92h6bvf.apps.googleusercontent.com",
    callback: handleGoogleResponse,
  });

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
  // google.accounts.id.prompt(); // optional
}

async function handleGoogleResponse(response) {
  try {
    // debug: show the credential object in console
    console.log('google response:', response);

    const res = await fetch('/config/google_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id_token: response.credential })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      alert((data && data.message) || 'Google sign-in failed.');
      return;
    }

    if (data.user) {
      try { localStorage.setItem('user', JSON.stringify(data.user)); } catch (_) {}
    }

    window.location.href = data.redirect || '/partner_form.php';
  } catch (e) {
    console.error(e);
    alert('Network error. Please try again.');
  }
}
