// public/js/site.js

/* ---------- small UI helpers ---------- */
function showToast(message, type = 'info', ttl = 3500) {
  if (!document.body) return;
  let container = document.getElementById('global-toast');
  if (!container) {
    container = document.createElement('div');
    container.id = 'global-toast';
    container.style.position = 'fixed';
    container.style.right = '12px';
    container.style.top = '12px';
    container.style.zIndex = 99999;
    document.body.appendChild(container);
  }
  const el = document.createElement('div');
  el.textContent = message;
  el.style.padding = '10px 14px';
  el.style.marginTop = '8px';
  el.style.borderRadius = '8px';
  el.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
  el.style.color = '#fff';
  el.style.maxWidth = '320px';
  el.style.fontWeight = '600';
  el.style.fontSize = '0.95rem';
  if (type === 'error') {
    el.style.background = '#c62828';
  } else if (type === 'warn') {
    el.style.background = '#f57c00';
  } else {
    el.style.background = '#2e7d32';
  }
  container.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, ttl);
}

/* Escape HTML for safe insertion */
function escapeHtml(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, function (m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

/* ---------- PINCODE CHECK ---------- */
/**
 * Check pincode serviceability.
 * - Calls /api/pincode_check.php?pin=XXXXXX
 * - Expects JSON: { success:true, serviceable:bool, shipping_cost:number|null, min_tat_days:int|null, max_tat_days:int|null, message, source }
 * - Updates element with id="pincode-status" if present and enables/disables #checkout-btn
 */
async function checkPincode(pin) {
  if (!pin || typeof pin !== 'string' || pin.trim().length !== 6) {
    showToast('Enter a valid 6-digit pincode', 'error');
    return { success: false, error: 'invalid_pincode' };
  }
  const statusEl = document.getElementById('pincode-status');
  if (statusEl) {
    statusEl.textContent = 'Checking pincode…';
    statusEl.style.color = '#666';
  }
  try {
    const res = await fetch(`/api/pincode_check.php?pin=${encodeURIComponent(pin.trim())}`, { cache: 'no-store' });
    const j = await res.json();
    if (!j || !j.success) {
      if (statusEl) statusEl.textContent = 'Not serviceable';
      showToast('Pincode check failed', 'error');
      const checkoutBtn = document.querySelector('#checkout-btn');
      if (checkoutBtn) checkoutBtn.disabled = true;
      return j || { success: false };
    }
    if (!j.serviceable) {
      if (statusEl) {
        statusEl.textContent = 'Not serviceable to this pincode';
        statusEl.style.color = '#c62828';
      }
      const checkoutBtn = document.querySelector('#checkout-btn');
      if (checkoutBtn) checkoutBtn.disabled = true;
      return j;
    }
    // serviceable
    const tat = (j.min_tat_days && j.max_tat_days) ? `${j.min_tat_days}-${j.max_tat_days} day(s)` : (j.min_tat_days ? `${j.min_tat_days} day(s)` : '—');
    if (statusEl) {
      statusEl.textContent = `Serviceable — ETA: ${tat}${j.shipping_cost ? ' — Shipping: ₹' + j.shipping_cost : ''}`;
      statusEl.style.color = '#2e7d32';
    }
    const checkoutBtn = document.querySelector('#checkout-btn');
    if (checkoutBtn) checkoutBtn.disabled = false;
    return j;
  } catch (err) {
    console.error('checkPincode error', err);
    if (statusEl) statusEl.textContent = 'Pincode check error';
    showToast('Pincode lookup failed', 'error');
    const checkoutBtn = document.querySelector('#checkout-btn');
    if (checkoutBtn) checkoutBtn.disabled = true;
    return { success: false, error: 'exception' };
  }
}

/* ---------- TRACKING TIMELINE ---------- */
/**
 * Render timeline into #tracking-area
 * Response shape from /api/get_order_tracking.php:
 * { success:true, order: { id, awb, label_url, latest_status, expected_delivery_date }, timeline: [ { id, awb, status, location, created_at } ... ] }
 */
function renderTimeline(resp) {
  const container = document.getElementById('tracking-area');
  if (!container) return;
  if (!resp || !resp.success) {
    container.innerHTML = '<p class="small-muted">No tracking data available.</p>';
    return;
  }

  const order = resp.order || {};
  const timeline = Array.isArray(resp.timeline) ? resp.timeline : [];

  if (timeline.length === 0) {
    container.innerHTML = '<p class="small-muted">No tracking events yet.</p>';
    return;
  }

  const ul = document.createElement('div');
  ul.className = 'tracking-timeline';

  timeline.forEach((ev, idx) => {
    const step = document.createElement('div');
    step.className = 'tracking-step' + (idx === timeline.length - 1 ? ' active' : '');

    const icon = document.createElement('div');
    icon.className = 'icon';
    icon.textContent = (idx === timeline.length - 1) ? '🚚' : '●';
    step.appendChild(icon);

    const details = document.createElement('div');
    details.className = 'details';

    const statusText = ev.status || ev.location || (idx === 0 && order.latest_status) || 'Update';
    const p = document.createElement('p');
    p.innerHTML = escapeHtml(statusText);
    details.appendChild(p);

    const meta = document.createElement('small');
    meta.className = 'small-muted';
    const ts = ev.created_at || (idx === 0 ? order.created_at : '');
    meta.textContent = ts ? ts : '';
    details.appendChild(meta);

    if (ev.location && ev.status) {
      const loc = document.createElement('div');
      loc.className = 'small-muted';
      loc.textContent = ev.location;
      details.appendChild(loc);
    } else if (ev.location && !ev.status) {
      const loc = document.createElement('div');
      loc.className = 'small-muted';
      loc.textContent = ev.location;
      details.appendChild(loc);
    }

    // show AWB on first/last events if present
    if (ev.awb || order.awb) {
      const aw = document.createElement('div');
      aw.className = 'small-muted';
      aw.textContent = 'AWB: ' + (ev.awb || order.awb);
      details.appendChild(aw);
    }

    step.appendChild(details);
    ul.appendChild(step);
  });

  container.innerHTML = '';
  container.appendChild(ul);
}

/**
 * Load tracking timeline for an order and render it.
 * @param {number|string} orderId
 */
async function loadTracking(orderId) {
  const container = document.getElementById('tracking-area');
  if (container) container.innerHTML = '<p class="small-muted">Loading timeline…</p>';
  try {
    const res = await fetch(`/api/get_order_tracking.php?order_id=${encodeURIComponent(orderId)}`, { cache: 'no-store' });
    const j = await res.json();
    renderTimeline(j);
    return j;
  } catch (err) {
    console.error('loadTracking error', err);
    if (container) container.innerHTML = '<p class="small-muted">Error loading tracking.</p>';
    return { success: false, error: 'exception' };
  }
}

/* ---------- RETURN REQUEST ---------- */
/**
 * Submit a return request from the order details modal.
 * Expects global ORDER_ID and textarea#return-reason and elements:
 *  - #return-feedback for messages
 *  - #return-modal to hide on success
 */
async function submitReturnRequest() {
  if (typeof ORDER_ID === 'undefined' || !ORDER_ID) {
    showToast('Order ID missing', 'error');
    return;
  }
  const btn = document.getElementById('submit-return');
  const feedback = document.getElementById('return-feedback');
  const reasonEl = document.getElementById('return-reason');
  if (!reasonEl) {
    showToast('Return reason field missing', 'error');
    return;
  }
  const reason = reasonEl.value.trim();
  if (!reason) {
    feedback && (feedback.textContent = 'Please enter a reason for return.');
    showToast('Enter a reason for return', 'error');
    return;
  }

  if (btn) btn.disabled = true;
  feedback && (feedback.textContent = 'Submitting return request…');

  try {
    const res = await fetch('/api/create_return.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: ORDER_ID, reason })
    });
    const j = await res.json();
    if (j && j.success) {
      feedback && (feedback.textContent = 'Return request submitted. Admin will review.');
      showToast('Return requested', 'info', 3000);
      // close modal after short delay
      setTimeout(() => {
        const modal = document.getElementById('return-modal');
        if (modal) modal.style.display = 'none';
        // clear reason box
        reasonEl.value = '';
      }, 900);

      // refresh timeline & order status
      await loadTracking(ORDER_ID);

      return j;
    } else {
      console.warn('create_return failed', j);
      feedback && (feedback.textContent = 'Failed to submit return. Please try again later.');
      showToast('Return request failed', 'error');
      return j || { success: false };
    }
  } catch (err) {
    console.error('submitReturnRequest error', err);
    feedback && (feedback.textContent = 'Error submitting return.');
    showToast('Return request error', 'error');
    return { success: false, error: 'exception' };
  } finally {
    if (btn) btn.disabled = false;
  }
}

/* ---------- Export to window for pages to call ---------- */
window.checkPincode = checkPincode;
window.loadTracking = loadTracking;
window.submitReturnRequest = submitReturnRequest;
window.showToast = showToast;
