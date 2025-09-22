// profile.js — defensive version (works with get_user.php output shown in your screenshot)

function isJsonResponse(res) {
  const ct = res.headers.get('content-type') || '';
  return ct.includes('application/json') || ct.includes('text/json') || ct.includes('application/problem+json');
}

async function ensureLoggedIn() {
  try {
    const res = await fetch('check_login.php', { credentials: 'include' });
    // If the server returned HTML (redirected to login), don't call res.json() directly.
    if (!isJsonResponse(res)) {
      const txt = await res.text();
      console.warn('check_login.php returned non-JSON response (probably an HTML redirect). First 200 chars:', txt.slice(0,200));
      // Treat as not logged in
      window.location.href = 'index.php';
      return false;
    }
    const json = await res.json();
    if (!json || !json.logged_in) {
      window.location.href = 'index.php';
      return false;
    }
    return true;
  } catch (err) {
    console.error('Error checking login status:', err);
    // Be conservative: redirect to homepage
    window.location.href = 'index.php';
    return false;
  }
}

async function loadProfileData() {
  const msgEl = document.getElementById('status-message');
  try {
    const res = await fetch('get_user.php', { credentials: 'include' });

    if (!isJsonResponse(res)) {
      const txt = await res.text();
      console.error('get_user.php returned non-JSON response (HTML or error). First 300 chars:', txt.slice(0,300));
      if (msgEl) msgEl.innerText = 'Server returned HTML instead of JSON for user data. Check console Network tab.';
      return;
    }

    if (!res.ok) {
      const errJson = await res.json().catch(() => null);
      console.error('get_user.php responded with non-OK status', res.status, errJson);
      if (msgEl) msgEl.innerText = 'Failed to load profile (server error).';
      return;
    }

    const data = await res.json();
    if (!data || !data.success) {
      console.warn('get_user.php returned JSON but not success:', data);
      if (msgEl) msgEl.innerText = data && data.message ? data.message : 'Unable to load profile';
      return;
    }

    // Map your JSON field names to form fields (your get_user.php shows firstName/lastName/email/phone)
    const u = data.user || {};
    const displayName = (u.firstName || '') + (u.lastName ? (' ' + u.lastName) : '');

    const setVal = (id, val) => {
      const el = document.getElementById(id);
      if (!el) return;
      if ('value' in el) el.value = val ?? '';
      else el.innerText = val ?? '';
    };

    setVal('profile-name', displayName);
    setVal('profile-firstname', u.firstName || '');
    setVal('profile-lastname', u.lastName || '');
    setVal('profile-email', u.email || '');
    setVal('profile-phone', u.phone || '');
    setVal('profile-house_no', u.house_no || '');
    setVal('profile-landmark', u.landmark || '');
    setVal('profile-city', u.city || '');
    setVal('profile-pincode', u.pincode || '');

    if (msgEl) msgEl.innerText = 'Profile loaded';
  } catch (err) {
    console.error('Error loading profile data:', err);
    if (msgEl) msgEl.innerText = 'Error loading profile data. See console.';
  }
}
// call this inside profile.js (replace previous handleProfileSubmit)
async function handleProfileSubmit(evt) {
  evt.preventDefault();
  const form = evt.currentTarget;
  const msgEl = document.getElementById('status-message');

  try {
    // Build FormData and ensure 'name' exists (compose from firstname/lastname if needed)
    const formData = new FormData(form);

    // If the form doesn't include 'name', try to construct from firstname/lastname fields
    if (!formData.has('name')) {
      const first = (document.getElementById('profile-firstname') && document.getElementById('profile-firstname').value.trim()) || formData.get('firstName') || '';
      const last  = (document.getElementById('profile-lastname') && document.getElementById('profile-lastname').value.trim()) || formData.get('lastName') || '';
      const composed = (first + ' ' + last).trim();
      if (composed) {
        formData.set('name', composed);
      }
    }

    // If user used inputs with other naming (firstName/lastName), also forward them so server can read either
    const firstEl = document.getElementById('profile-firstname');
    if (firstEl && !formData.has('firstName')) formData.set('firstName', firstEl.value);

    const lastEl = document.getElementById('profile-lastname');
    if (lastEl && !formData.has('lastName')) formData.set('lastName', lastEl.value);

    // Debug: print out FormData keys for visibility
    console.log('Submitting profile FormData:');
    for (const pair of formData.entries()) {
      console.log(pair[0], ':', pair[1]);
    }

    const res = await fetch('update_profile.php', {
      method: 'POST',
      body: formData,
      credentials: 'include'
    });

    // Robustly handle non-JSON responses
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const txt = await res.text();
      console.error('update_profile.php returned non-JSON. First 400 chars:', txt.slice(0,400));
      if (msgEl) msgEl.innerText = 'Server returned unexpected response. Check console Network tab.';
      return;
    }

    const data = await res.json();

// If update succeeded, reload the page so user sees the updated profile immediately
if (data && data.success) {
  // prefer the explicit reload flag when present
  if (data.reload === true) {
    location.reload();
  } else {
    // fallback — still reload on success
    location.reload();
  }
} else {
  // on failure, show the message (if any)
  if (msgEl) msgEl.innerText = data && data.message ? data.message : 'Update failed';
}
  } catch (err) {
    console.error('Profile update error:', err);
    if (msgEl) msgEl.innerText = 'Profile update failed. See console.';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('profile-form');
  if (form) {
    form.addEventListener('submit', handleProfileSubmit);
    console.log('Profile form handler attached.');
  } else {
    console.warn('No form with id="profile-form" found.');
  }
});
