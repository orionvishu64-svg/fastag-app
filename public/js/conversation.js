(() => {
  const ENDPOINT_GET_CONV = '/config/get_conversation.php';
  const ENDPOINT_GET_CLOSED = '/config/get_closed_conversation.php';
  const ENDPOINT_ADD_REPLY = '/config/contact_replies.php';

  // ===== Utility Functions =====
  async function safeFetchJson(url, opts = {}) {
    opts.credentials = opts.credentials ?? 'include';
    opts.headers = opts.headers ?? {};
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status} ${res.statusText} ${t}`);
    }
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (ct.includes('application/json')) return res.json();
    const txt = await res.text().catch(() => '');
    try { return txt ? JSON.parse(txt) : txt; } catch { return txt; }
  }

  function fmtTimeToIST(ts) {
    if (!ts) return '';
    let d = new Date(ts);
    if (isNaN(d.getTime())) {
      const n = Number(ts);
      if (!isNaN(n)) d = new Date(n * (String(n).length === 10 ? 1000 : 1));
      else return '';
    }
    try {
      const dtf = new Intl.DateTimeFormat('en-IN', {
        hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Kolkata'
      });
      return dtf.format(d);
    } catch {
      const hh = String(d.getHours()).padStart(2, '0');
      const mm = String(d.getMinutes()).padStart(2, '0');
      return `${hh}:${mm}`;
    }
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function genLocalId() {
    if (window.crypto && window.crypto.randomUUID) return 'local_' + crypto.randomUUID();
    return 'local_' + Date.now().toString(36) + '_' + Math.floor(Math.random() * 90000 + 10000).toString(36);
  }

  // ===== Centralized, idempotent message renderer =====
  // msg: { id, local_id, content/message/reply_text, is_admin (0/1), created_at, admin_identifier, user_id }
  function renderMessage(msg = {}, { containerId = 'openThread', prepend = false } = {}) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    // normalized fields
    const replyId = msg.id ?? msg.inserted_id ?? msg.reply_id ?? null;
    const localId = msg.local_id ?? msg.localId ?? msg.client_msg_id ?? null;
    const text = msg.reply_text ?? msg.message ?? msg.content ?? '';
    const isAdmin = (typeof msg.is_admin !== 'undefined') ? (Number(msg.is_admin) === 1) : Boolean(msg.admin_identifier);
    const who = isAdmin ? (msg.admin_identifier || 'Admin') : 'You';
    const ts = msg.created_at ?? msg.replied_at ?? msg.created_at_ts ?? null;
    const tsText = fmtTimeToIST(ts);

    // Deduplication: if a node with same replyId or localId exists -> don't render again
    if (replyId && container.querySelector(`[data-reply-id="${escapeAttr(replyId)}"]`)) return null;
    if (!replyId && localId && container.querySelector(`[data-local-id="${escapeAttr(localId)}"]`)) return null;

    // Build DOM node (consistent with history rendering)
    const div = document.createElement('div');
    div.className = `message ${isAdmin ? 'admin' : 'user'}`;
    if (replyId) div.setAttribute('data-reply-id', String(replyId));
    if (localId) div.setAttribute('data-local-id', String(localId));
    div.innerHTML = `<div><strong>${escapeHtml(who)}:</strong> ${escapeHtml(text)}</div>
                     <div class="meta">${escapeHtml(tsText)}</div>`;

    if (prepend) container.prepend(div); else container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  // small helper to escape values used inside attribute selectors
  function escapeAttr(s) {
    return String(s || '').replace(/(["\\])/g, '\\$1');
  }

  // render an array of replies into a container element
  function renderRepliesContainer(container, replies) {
    if (!container) return;
    container.innerHTML = '';
    (replies || []).forEach(r => {
      renderMessage({
        id: r.id ?? r.inserted_id ?? r.reply_id,
        local_id: r.local_id ?? r.localId,
        reply_text: r.reply_text ?? r.message ?? '',
        is_admin: (typeof r.is_admin !== 'undefined') ? Number(r.is_admin) : Boolean(r.admin_identifier),
        created_at: r.replied_at ?? r.created_at ?? r.created_at_ts,
        admin_identifier: r.admin_identifier,
        user_id: r.user_id
      }, { containerId: container.id, prepend: false });
    });
  }

  // small helper: get ticket_id from URL ?ticket_id=... (fallback to window.TICKET_PUBLIC_ID)
  function getRequestedTicketId() {
    const fromGlobal = (window.TICKET_PUBLIC_ID || '').toString().trim();
    try {
      const params = new URLSearchParams(window.location.search);
      const q = params.get('ticket_id') || params.get('query_id') || null;
      if (q && String(q).trim()) return String(q).trim();
    } catch (e) {}
    return fromGlobal || null;
  }

  // ===== Load Open Ticket =====
  async function loadOpenTicket() {
    const ticketPublic = (window.TICKET_PUBLIC_ID || '').trim();
    let url = ENDPOINT_GET_CONV;
    if (ticketPublic) url += '?ticket_id=' + encodeURIComponent(ticketPublic);

    try {
      const data = await safeFetchJson(url, { method: 'GET' });
      const container = document.getElementById('openTicketContainer');
      if (!container) return;
      container.innerHTML = '';

      if (!data || (Array.isArray(data) && data.length === 0) ||
          (typeof data === 'object' && Object.keys(data).length === 0)) {
        container.innerHTML = '<div class="ticket"><p>No open ticket found.</p></div>';
        return;
      }

      if (data.success === false && data.message) {
        container.innerHTML = `<div class="ticket"><p>Error: ${escapeHtml(data.message)}</p></div>`;
        return;
      }

      const t = data.query || (Array.isArray(data) ? data[0] : (data.ticket || data));
      if (!t) {
        container.innerHTML = '<div class="ticket"><p>No open ticket found.</p></div>';
        return;
      }

      const div = document.createElement('div');
      div.className = 'ticket';
      let inner = '';
      inner += `<div class="status-badge">${data.open ? 'Open' :
        (t.status ? (t.status.charAt(0).toUpperCase() + t.status.slice(1)) : 'Unknown')}</div>`;
      inner += `<h4>Ticket #${escapeHtml(t.ticket_id || t.id || t.query_id)} - ${escapeHtml(t.subject || '')}</h4>`;
      inner += `<p><strong>Message:</strong> ${escapeHtml(t.message || '')}</p>`;
      inner += `<div id="openThread" class="replies"></div>`;
      inner += `<form id="replyForm"><textarea id="replyMessage" placeholder="Type your reply..." required></textarea><button type="submit">Reply</button></form>`;
      div.innerHTML = inner;
      container.appendChild(div);

      // populate existing replies using centralized renderer
      const threadEl = document.getElementById('openThread');
      renderRepliesContainer(threadEl, data.replies || []);

      // ===== Submit Handler (Optimistic Append) =====
      const form = document.getElementById('replyForm');
      if (form) {
        // remove previously attached handler if any (prevents double-binding)
        if (form.__replyHandler) form.removeEventListener('submit', form.__replyHandler);

        const handler = async (e) => {
          e.preventDefault();
          const msgEl = document.getElementById('replyMessage');
          const msg = (msgEl && msgEl.value || '').trim();
          if (!msg) return;

          // Prepare local_id
          const localId = genLocalId();

          // Optimistic append (use centralized renderer)
          const nowIso = new Date().toISOString();
          const optimisticNode = renderMessage({
            local_id: localId,
            reply_text: msg,
            is_admin: 0,
            created_at: nowIso
          }, { containerId: 'openThread', prepend: false });

          // send to server
          try {
            const fd = new FormData();
            fd.append('ticket_id', t.id || t.query_id || t.ticket_id || '');
            fd.append('query_id', t.id || t.query_id || t.ticket_id || '');
            fd.append('message', msg);
            fd.append('reply_text', msg);
            fd.append('local_id', localId);

            const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
              method: 'POST',
              body: fd,
              credentials: 'include'
            });

            if (res && res.success) {
              try {
                // update optimistic node attributes with authoritative IDs returned by server
                if (optimisticNode) {
                  if (res.inserted_id) optimisticNode.setAttribute('data-reply-id', String(res.inserted_id));
                  if (res.local_id || localId) optimisticNode.setAttribute('data-local-id', String(res.local_id || localId));
                }
              } catch (err) { /* ignore DOM update errors */ }
            } else {
              console.error('Reply API failed', res);
              alert('Failed to send reply. Please try again.');
              // optionally remove optimisticNode or mark as failed
            }
          } catch (err) {
            console.error('Send reply failed', err);
            alert('Failed to send reply. Please try again later.');
            // optionally remove optimisticNode or mark as failed
          }

          if (msgEl) msgEl.value = '';
        };

        form.addEventListener('submit', handler);
        form.__replyHandler = handler;
      }
    } catch (err) {
      console.error('Failed to load open ticket', err);
      const container = document.getElementById('openTicketContainer');
      if (container)
        container.innerHTML = `<div class="ticket"><p>Error loading ticket: ${escapeHtml(err.message || String(err))}</p></div>`;
    }
  }

  // ===== Load Closed Tickets =====
  async function loadClosedTickets() {
    try {
      // Check if we have a specific ticket requested (URL param or global)
      const requestedTicket = getRequestedTicketId(); // uses URL or window.TICKET_PUBLIC_ID

      // If a specific ticket is requested, fetch full conversation via the "get conversation" endpoint
      if (requestedTicket) {
        // use the same endpoint as open ticket rendering so we get full replies
        const url = ENDPOINT_GET_CONV + '?ticket_id=' + encodeURIComponent(requestedTicket);
        const data = await safeFetchJson(url, { method: 'GET' });

        const container = document.getElementById('closedTicketsContainer');
        if (!container) return;
        container.innerHTML = '';

        if (!data || (Array.isArray(data) && data.length === 0) ||
            (typeof data === 'object' && Object.keys(data).length === 0)) {
          container.innerHTML = `<div class="ticket"><p>No conversation found for #${escapeHtml(requestedTicket)}</p></div>`;
          return;
        }

        // Normalize to ticket object (same as loadOpenTicket)
        const t = data.query || (Array.isArray(data) ? data[0] : (data.ticket || data));
        if (!t) {
          container.innerHTML = `<div class="ticket"><p>No conversation found for #${escapeHtml(requestedTicket)}</p></div>`;
          return;
        }

        // Render single ticket (same markup as open ticket)
        const div = document.createElement('div');
        div.className = 'ticket';
        let inner = '';
        inner += `<div class="status-badge">${t.status ? (t.status.charAt(0).toUpperCase() + t.status.slice(1)) : 'Closed'}</div>`;
        inner += `<h4>Ticket #${escapeHtml(t.ticket_id || t.id || t.query_id)} - ${escapeHtml(t.subject || '')}</h4>`;
        inner += `<p><strong>Message:</strong> ${escapeHtml(t.message || '')}</p>`;
        inner += `<div id="openThread" class="replies"></div>`;
        // We usually do not render the reply form for closed tickets, but keep the thread
        div.innerHTML = inner;
        container.appendChild(div);

        // populate replies using centralized renderer (same as open)
        const threadEl = document.getElementById('openThread');
        renderRepliesContainer(threadEl, data.replies || t.replies || []);

        return;
      }

      // No specific ticket requested: fallback to listing *all* closed tickets via the closed endpoint
      const data = await safeFetchJson(ENDPOINT_GET_CLOSED, { method: 'GET' });
      const container = document.getElementById('closedTicketsContainer');
      if (!container) return;
      container.innerHTML = '';

      if (data && data.success && Array.isArray(data.queries) && data.queries.length) {
        data.queries.forEach(q => {
          const tdiv = document.createElement('div');
          tdiv.className = 'ticket';
          let html = '';
          html += `<div class="status-badge">Closed</div>`;
          html += `<h4>${escapeHtml(q.ticket_id || q.id)} - ${escapeHtml(q.subject || '')}</h4>`;
          html += `<p><strong>Message:</strong> ${escapeHtml(q.message || '')}</p>`;
          html += `<div class="replies"></div>`;
          html += `<div class="meta">Closed: ${escapeHtml(fmtTimeToIST(q.closed_at ?? q.submitted_at))}</div>`;
          tdiv.innerHTML = html;
          container.appendChild(tdiv);
          const repliesContainer = tdiv.querySelector('.replies');
          renderClosedReplies(repliesContainer, q.replies || []);
        });
      } else {
        container.innerHTML = '<div class="ticket"><p>No closed tickets. Please check back later.</p></div>';
      }
    } catch (err) {
      console.error('Failed to load closed tickets', err);
    }
  }

  // ===== Post Reply (generic utility if needed elsewhere) =====
  async function postReply(queryIdOrTicketId, message, opts = {}) {
    if (!queryIdOrTicketId || !message) throw new Error('Invalid params');
    const localId = opts.local_id || genLocalId();
    const form = new FormData();
    form.append('ticket_id', queryIdOrTicketId);
    form.append('query_id', queryIdOrTicketId);
    form.append('message', message);
    form.append('reply_text', message);
    form.append('local_id', localId);

    const res = await safeFetchJson(ENDPOINT_ADD_REPLY, { method: 'POST', body: form, credentials: 'include' });
    if (!res || (res.success === false)) throw new Error(res && res.message ? res.message : 'Server rejected reply');
    return res;
  }

  // ===== Initialize =====
  document.addEventListener('DOMContentLoaded', () => {
    loadOpenTicket();
    loadClosedTickets();
  });

  window.CONVERSATION = {
    reloadOpen: loadOpenTicket,
    reloadClosed: loadClosedTickets,
    postReply,
    renderMessage
  };
})();