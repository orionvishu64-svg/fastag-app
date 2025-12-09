/* public/js/site.js (updated) */

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

function escapeHtml(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, function (m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

async function fetchJson(url, opts = {}) {
  opts.credentials = opts.credentials || 'same-origin';
  if (!opts.headers) opts.headers = {};
  try {
    const res = await fetch(url, opts);
    const text = await res.text();
    if (!text) {
      return { ok: res.ok, status: res.status, json: null, raw: '' };
    }
    try {
      const j = JSON.parse(text);
      return { ok: res.ok, status: res.status, json: j, raw: text };
    } catch (e) {
      console.error('Invalid JSON from', url, 'raw:', text.slice(0,300));
      throw new Error('Invalid JSON response');
    }
  } catch (err) {
    console.error('fetchJson error', err);
    throw err;
  }
}

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
    const res = await fetchJson(`/api/pincode_check.php?pincode=${encodeURIComponent(pin.trim())}`, { cache: 'no-store' });
    const j = res.json;
    if (!j || !j.success) {
      if (statusEl) statusEl.textContent = 'Not serviceable';
      showToast('Pincode check failed', 'error');
      const checkoutBtn = document.querySelector('#checkout-btn');
      if (checkoutBtn) checkoutBtn.disabled = true;
      return j || { success: false };
    }

    if (!j.serviceable) {
      if (statusEl) {
        statusEl.textContent = j.message || 'Not serviceable to this pincode';
        statusEl.style.color = '#c62828';
      }
      const checkoutBtn = document.querySelector('#checkout-btn');
      if (checkoutBtn) checkoutBtn.disabled = true;
      return j;
    }

    const tat = (j.min_tat_days && j.max_tat_days) ? `${j.min_tat_days}-${j.max_tat_days} day(s)` : (j.min_tat_days ? `${j.min_tat_days} day(s)` : '—');
    if (statusEl) {
      const costText = (j.shipping_cost || j.shipping_cost === 0) ? ` — Shipping: ₹${j.shipping_cost}` : '';
      const src = j.source ? ` (${j.source})` : '';
      statusEl.textContent = `Serviceable — ETA: ${tat}${costText}${src}`;
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

/* TRACKING TIMELINE */
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

  const wrap = document.createElement('div');
  wrap.className = 'tracking-timeline';

  timeline.forEach((ev, idx) => {
    const isLast = (idx === timeline.length - 1);

    const step = document.createElement('div');
    step.className = 'tracking-step' + (isLast ? ' active' : '');

    const icon = document.createElement('div');
    icon.className = 'icon';
    icon.textContent = isLast ? '🚚' : '●';
    step.appendChild(icon);

    const details = document.createElement('div');
    details.className = 'details';

    const statusText = ev.event || ev.event_status || ev.status || ev.location || (idx === 0 && order.latest_status) || 'Update';
    const p = document.createElement('p');
    p.innerHTML = escapeHtml(statusText);
    details.appendChild(p);

    const meta = document.createElement('small');
    meta.className = 'small-muted';
    const ts = ev.occurred_at || ev.created_at || (idx === 0 ? order.created_at : '');
    meta.textContent = ts ? ts : '';
    details.appendChild(meta);

    if (ev.location) {
      const loc = document.createElement('div');
      loc.className = 'small-muted';
      loc.textContent = ev.location;
      details.appendChild(loc);
    }

    if (ev.note) {
      const noteEl = document.createElement('div');
      noteEl.className = 'small-muted';
      noteEl.textContent = typeof ev.note === 'string' ? ev.note : JSON.stringify(ev.note);
      details.appendChild(noteEl);
    }

    if (ev.awb || order.awb) {
      const aw = document.createElement('div');
      aw.className = 'small-muted';
      aw.textContent = 'AWB: ' + (ev.awb || order.awb);
      details.appendChild(aw);
    }

    if (ev.event_source) {
      const src = document.createElement('div');
      src.className = 'small-muted';
      src.textContent = 'Source: ' + ev.event_source;
      details.appendChild(src);
    }

    step.appendChild(details);
    wrap.appendChild(step);
  });

  container.innerHTML = '';
  container.appendChild(wrap);
}

async function loadTracking(orderId, opts = {}) {
  const container = document.getElementById('tracking-area');
  if (container) container.innerHTML = '<p class="small-muted">Loading timeline…</p>';
  try {
    let url = `/api/get_order_tracking.php?order_id=${encodeURIComponent(orderId)}`;
    if (opts.refresh) url += '&refresh=1';
    const res = await fetchJson(url, { cache: 'no-store' });
    renderTimeline(res.json || {});
    return res.json || {};
  } catch (err) {
    console.error('loadTracking error', err);
    if (container) container.innerHTML = '<p class="small-muted">Error loading tracking.</p>';
    showToast('Failed to load tracking', 'error');
    return { success: false, error: 'exception' };
  }
}

 /* RETURN REQUEST */
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
    const res = await fetchJson('/api/create_return.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: ORDER_ID, reason })
    });

    const j = res.json || {};
    if (j && j.success) {
      feedback && (feedback.textContent = 'Return request submitted. Admin will review.');
      showToast('Return requested', 'info', 3000);
      setTimeout(() => {
        const modal = document.getElementById('return-modal');
        if (modal) modal.style.display = 'none';
        reasonEl.value = '';
      }, 900);

      await loadTracking(ORDER_ID, { refresh: true });

      return j;
    } else {
      console.warn('create_return failed', j);
      feedback && (feedback.textContent = j?.message || 'Failed to submit return. Please try again later.');
      showToast(j?.message || 'Return request failed', 'error');
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
window.checkPincode = checkPincode;
window.loadTracking = loadTracking;
window.submitReturnRequest = submitReturnRequest;
window.showToast = showToast;