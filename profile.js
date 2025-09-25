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

// Saved partners - AJAX delete enhancement and flash message
document.addEventListener('DOMContentLoaded', function () {
  const grid = document.getElementById('partners-grid');
  if (!grid) return;

  // Simple flash message helper
  function flash(msg, success = true) {
    let el = document.createElement('div');
    el.className = 'pf-flash ' + (success ? 'pf-success' : 'pf-error');
    el.textContent = msg;
    el.style = 'position:fixed;top:18px;right:18px;padding:10px 14px;border-radius:8px;z-index:99999;background:' + (success ? '#0f9d58' : '#d64545') + ';color:#fff;';
    document.body.appendChild(el);
    setTimeout(()=> el.style.opacity = '0.95', 10);
    setTimeout(()=> el.remove(), 3000);
  }

  // Attach handler to all delete forms
  document.querySelectorAll('.delete-partner-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      // If user wants full page submit (hold Ctrl) let it through
      if (e.ctrlKey || e.metaKey) return;

      e.preventDefault();

      // Build FormData
      const fd = new FormData(form);
      // Include csrf if it's in DOM (some projects put hidden CSRF elsewhere)
      // If a global csrf token variable exists, you can add it here
      // fd.append('csrf_token', window.CSRF_TOKEN || '');

      // Determine which card to remove on success
      const card = form.closest('.partner-card') || form.closest('.gv-card');

      // Send fetch
      fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      }).then(async res => {
        // Try to parse text (partner_form.php might redirect)
        const text = await res.text();

        // If partner_form.php returns a redirect, fetch will follow it and return final HTML.
        // We'll detect success by checking status or presence of keywords.
        // Best-effort: if response ok -> consider success.
        if (res.ok) {
          // Remove card from DOM
          if (card) card.remove();
          flash('Partner deleted', true);
        } else {
          flash('Could not delete partner', false);
        }
      }).catch(err => {
        console.error('Delete error', err);
        flash('Network error: delete failed', false);
      });
    });
  });

  // If profile.php redirected back with ?deleted=1 or ?deleted=0, show message
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('deleted') === '1') flash('Partner deleted', true);
  if (urlParams.get('deleted') === '0') flash('Delete failed', false);
});

document.addEventListener('DOMContentLoaded', function () {
  const overlay = document.getElementById('edit-partner-overlay');
  const form = document.getElementById('edit-partner-form');

  function flash(msg, ok = true) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style = 'position:fixed;right:18px;top:18px;padding:8px 12px;border-radius:8px;background:' + (ok? '#0f9d58' : '#d64545') + ';color:#fff;z-index:10000;';
    document.body.appendChild(el);
    setTimeout(()=> el.remove(), 3000);
  }

  // Open editor with data attributes from button
  document.querySelectorAll('.edit-partner-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const id = btn.dataset.id;
      const table = btn.dataset.table;

      // Hide both field groups then show relevant
      document.getElementById('ep-gv-fields').style.display = 'none';
      document.getElementById('ep-p-fields').style.display = 'none';

      document.getElementById('ep-id').value = id;
      document.getElementById('ep-table').value = table;

      if (table === 'gv_partners') {
        // show GV fields and populate
        document.getElementById('ep-gv-fields').style.display = '';
        document.getElementById('ep-gv_partner_id').value = btn.dataset.gv_partner_id || '';
        document.getElementById('ep-gv-name').value = btn.dataset.name || '';
        document.getElementById('edit-partner-title').textContent = 'Edit GV Partner';
      } else {
        // show normal partner fields
        document.getElementById('ep-p-fields').style.display = '';
        document.getElementById('ep-bank_name').value = btn.dataset.bank_name || '';
        document.getElementById('ep-partner_id').value = btn.dataset.partner_id || '';
        document.getElementById('ep-p-name').value = btn.dataset.name || '';
        document.getElementById('edit-partner-title').textContent = 'Edit Partner';
      }

      overlay.style.display = 'flex';
    });
  });

  // cancel
  document.getElementById('ep-cancel').addEventListener('click', function () {
    overlay.style.display = 'none';
  });

  // submit
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('ep-id').value;
    const table = document.getElementById('ep-table').value;

    const fd = new FormData();
    fd.append('action', 'update_partner');
    fd.append('id', id);
    fd.append('table', table);

    if (table === 'gv_partners') {
      fd.append('gv_partner_id', document.getElementById('ep-gv_partner_id').value.trim());
      fd.append('name', document.getElementById('ep-gv-name').value.trim());
    } else {
      fd.append('bank_name', document.getElementById('ep-bank_name').value.trim());
      fd.append('partner_id', document.getElementById('ep-partner_id').value.trim());
      fd.append('name', document.getElementById('ep-p-name').value.trim());
    }

    // If your app uses a CSRF token in a meta tag or global var, include it here:
    // fd.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('update_profile.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(res => res.json())
      .then(data => {
        if (data && data.success) {
          // Update the card in DOM to reflect new values
          const card = document.querySelector(`.card[data-row-id="${id}"][data-table="${table}"]`);
          if (card) {
            if (table === 'gv_partners') {
              const title = card.querySelector('.card-title');
              title.textContent = 'GV ' + (fd.get('gv_partner_id') || '') + ' — ' + (fd.get('name') || '');
            } else {
              const title = card.querySelector('.card-title');
              title.textContent = (fd.get('bank_name') || '') + ' — ' + (fd.get('partner_id') || '') + ' (' + (fd.get('name') || '') + ')';
            }
            // also update data attributes on the edit button
            const editBtn = card.querySelector('.edit-partner-btn');
            if (editBtn) {
              if (table === 'gv_partners') {
                editBtn.dataset.gv_partner_id = fd.get('gv_partner_id');
                editBtn.dataset.name = fd.get('name');
              } else {
                editBtn.dataset.bank_name = fd.get('bank_name');
                editBtn.dataset.partner_id = fd.get('partner_id');
                editBtn.dataset.name = fd.get('name');
              }
            }
          }
          flash(data.message || 'Updated', true);
          overlay.style.display = 'none';
        } else {
          flash(data.message || 'Update failed', false);
        }
      }).catch(err => {
        console.error(err);
        flash('Network error', false);
      });
  });

});
