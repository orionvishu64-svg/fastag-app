// public/js/google-auth.js
(async function (global) {
  'use strict';

  async function fetchClientId() {
    try {
      const res = await fetch('/config/google_login.php', { credentials: 'include' });
      if (!res.ok) throw new Error('Failed to fetch client_id');
      const json = await res.json();
      if (json && typeof json.client_id === 'string' && json.client_id) {
        return json.client_id;
      }
      throw new Error('client_id missing in response');
    } catch (err) {
      console.warn('google-auth: using fallback client_id; error:', err);
      return '214217458731-2fjk2nbmk1m4ifpgdgbpssqiv8f5m38u.apps.googleusercontent.com';
    }
  }

  global.initGoogle = async function initGoogle() {
    if (!window.google || !google.accounts || !google.accounts.id) {
      console.error('Google Identity Services not loaded');
      return;
    }
    const clientId = await fetchClientId();

    try {
      google.accounts.id.initialize({
        client_id: clientId,
        callback: handleGoogleResponse,
      });
    } catch (e) {
      console.error('google-auth: initialize failed', e);
      return;
    }

    const el = document.getElementById('google-signup');
    if (el) {
      try {
        google.accounts.id.renderButton(el, {
          theme: 'filled_blue',
          size: 'large',
          text: 'signup_with',
          shape: 'rectangular',
          logo_alignment: 'left',
        });
      } catch (e) {
        console.error('google-auth: renderButton error', e);
      }
    }

  };

  async function handleGoogleResponse(response) {
    try {
      if (!response || !response.credential) {
        alert('Google sign-in failed: no credential returned');
        return;
      }

      console.debug('google-auth: id_token obtained');

      const res = await fetch('/config/google_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id_token: response.credential })
      });

      let data = null;
      try {
        data = await res.json();
      } catch (e) {
        data = null;
      }

      if (!res.ok || !data || !data.success) {
        const msg = (data && data.message) ? data.message : 'Google sign-in failed';
        alert(msg);
        console.error('google-auth: server response:', res.status, data);
        return;
      }

      if (data.user) {
        try { localStorage.setItem('user', JSON.stringify(data.user)); } catch (_) {}
      }

      window.location.href = data.redirect || '/../partner_form.php';
    } catch (err) {
      console.error('google-auth: network error', err);
      alert('Network error — please try again.');
    }
  }
  global.handleGoogleResponse = handleGoogleResponse;
})(window);