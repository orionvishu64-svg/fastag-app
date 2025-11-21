// conversation.js
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

  // small helper to escape values used inside attribute selectors
  function escapeAttr(s) {
    return String(s || '').replace(/(["\\])/g, '\\$1');
  }

  // normalize text for fuzzy comparison (strip HTML entities, collapse whitespace)
  function normalizeTextForCompare(s) {
    if (!s) return '';
    const decoded = String(s)
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'");
    return decoded.replace(/\s+/g, ' ').trim().toLowerCase();
  }

  // fuzzy duplicate detector:
  // finds message node in container with same sender + text + close timestamp (within secondsTol)
  function existsSimilarMessage(container, { senderLabel, text, tsISO, secondsTol = 3 } = {}) {
    if (!container) return false;
    const normText = normalizeTextForCompare(text);
    const nodes = container.querySelectorAll('.message');
    if (!nodes || !nodes.length) return false;

    let tsMs = null;
    if (tsISO) {
      const d = new Date(tsISO);
      if (!isNaN(d.getTime())) tsMs = d.getTime();
      else {
        const n = Number(tsISO);
        if (!isNaN(n)) tsMs = n * (String(n).length === 10 ? 1000 : 1);
      }
    }

    for (let i = 0; i < nodes.length; i++) {
      const n = nodes[i];
      const existingSender = (n.getAttribute('data-sender') || '').toLowerCase();
      const existingText = (n.getAttribute('data-norm-text') || '').toLowerCase();
      const existingTs = n.getAttribute('data-ts-ms') ? Number(n.getAttribute('data-ts-ms')) : null;

      if (existingText && existingText === normText && existingSender && existingSender === (senderLabel || '').toLowerCase()) {
        if (tsMs && existingTs) {
          if (Math.abs(existingTs - tsMs) <= (secondsTol * 1000)) return true;
        } else {
          return true;
        }
      } else {
        // fallback DOM parse (if attributes missing)
        const body = n.querySelector('.message-body') || null;
        const header = n.querySelector('.message-header strong');
        const timeEl = n.querySelector('.message-header .time') || n.querySelector('.meta') || null;
        const existingHeader = header ? header.textContent.trim().toLowerCase() : '';
        const existingBody = body ? normalizeTextForCompare(body.textContent || '') : '';
        let existingTs2 = null;
        if (timeEl) {
          const t = new Date((timeEl.textContent || '').trim());
          if (!isNaN(t.getTime())) existingTs2 = t.getTime();
        }
        if (existingHeader === (senderLabel || '').toLowerCase() && existingBody === normText) {
          if (tsMs && existingTs2) {
            if (Math.abs(existingTs2 - tsMs) <= (secondsTol * 1000)) return true;
          } else {
            return true;
          }
        }
      }
    }
    return false;
  }

  // ===== Centralized, idempotent message renderer (with fuzzy dedupe) =====
  function renderMessage(msg = {}, { containerId = 'openThread', prepend = false } = {}) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const replyId = msg.id ?? msg.inserted_id ?? msg.reply_id ?? null;
    const localId = msg.local_id ?? msg.localId ?? msg.client_msg_id ?? null;
    const text = (msg.reply_text ?? msg.message ?? msg.content ?? '') + '';
    const isAdmin = (typeof msg.is_admin !== 'undefined') ? (Number(msg.is_admin) === 1) : Boolean(msg.admin_identifier);
    const senderLabelRaw = isAdmin ? (msg.admin_identifier || 'Admin') : (msg.sender_name || 'You');
    const senderLabel = String(senderLabelRaw || 'Unknown').trim();
    const ts = msg.created_at ?? msg.replied_at ?? msg.created_at_ts ?? null;
    const tsISO = ts ? (new Date(ts)).toISOString() : null;
    const tsText = fmtTimeToIST(ts);

    // 1) id/local-id dedupe (fast)
    if (replyId && container.querySelector(`[data-reply-id="${escapeAttr(replyId)}"]`)) return null;
    if (!replyId && localId && container.querySelector(`[data-local-id="${escapeAttr(localId)}"]`)) return null;

    // 2) fuzzy dedupe
    if (existsSimilarMessage(container, { senderLabel, text, tsISO, secondsTol: 3 })) {
      return null;
    }

    // Build DOM node
    const div = document.createElement('div');
    div.className = `message ${isAdmin ? 'admin' : 'user'}`;
    if (replyId) div.setAttribute('data-reply-id', String(replyId));
    if (localId) div.setAttribute('data-local-id', String(localId));

    // store searchable attributes to speed later duplicate checks
    div.setAttribute('data-sender', senderLabel.toLowerCase());
    div.setAttribute('data-norm-text', normalizeTextForCompare(text));
    if (tsISO) {
      const tsms = (new Date(tsISO)).getTime();
      if (!isNaN(tsms)) div.setAttribute('data-ts-ms', String(tsms));
    }

    div.innerHTML = `<div class="message-header"><strong>${escapeHtml(senderLabel)}</strong> <span class="time">${escapeHtml(tsText)}</span></div>
                     <div class="message-body">${escapeHtml(text)}</div>`;

    if (prepend) container.prepend(div); else container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  // render array of replies into a container
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
        sender_name: r.sender_name ?? null,
        sender_email: r.sender_email ?? null,
        user_id: r.user_id
      }, { containerId: container.id, prepend: false });
    });
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

      // Submit Handler (Optimistic Append)
      const form = document.getElementById('replyForm');
      if (form) {
        if (form.__replyHandler) form.removeEventListener('submit', form.__replyHandler);

        const handler = async (e) => {
          e.preventDefault();
          const msgEl = document.getElementById('replyMessage');
          const msg = (msgEl && msgEl.value || '').trim();
          if (!msg) return;

          const localId = genLocalId();
          const nowIso = new Date().toISOString();
          const optimisticNode = renderMessage({
            local_id: localId,
            reply_text: msg,
            is_admin: 0,
            created_at: nowIso
          }, { containerId: 'openThread', prepend: false });

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
                if (optimisticNode) {
                  if (res.inserted_id) optimisticNode.setAttribute('data-reply-id', String(res.inserted_id));
                  if (res.local_id || localId) optimisticNode.setAttribute('data-local-id', String(res.local_id || localId));
                }
              } catch (err) {}
            } else {
              console.error('Reply API failed', res);
              alert('Failed to send reply. Please try again.');
            }
          } catch (err) {
            console.error('Send reply failed', err);
            alert('Failed to send reply. Please try again later.');
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
      const requestedTicket = (window.TICKET_PUBLIC_ID || '').toString().trim();
      if (requestedTicket) {
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

        const t = data.query || (Array.isArray(data) ? data[0] : (data.ticket || data));
        if (!t) {
          container.innerHTML = `<div class="ticket"><p>No conversation found for #${escapeHtml(requestedTicket)}</p></div>`;
          return;
        }

        const div = document.createElement('div');
        div.className = 'ticket';
        let inner = '';
        inner += `<div class="status-badge">${t.status ? (t.status.charAt(0).toUpperCase() + t.status.slice(1)) : 'Closed'}</div>`;
        inner += `<h4>Ticket #${escapeHtml(t.ticket_id || t.id || t.query_id)} - ${escapeHtml(t.subject || '')}</h4>`;
        inner += `<p><strong>Message:</strong> ${escapeHtml(t.message || '')}</p>`;
        inner += `<div id="openThread" class="replies"></div>`;
        div.innerHTML = inner;
        container.appendChild(div);

        const threadEl = document.getElementById('openThread');
        renderRepliesContainer(threadEl, data.replies || t.replies || []);
        return;
      }

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
          renderRepliesContainer(repliesContainer, q.replies || []);
        });
      } else {
        container.innerHTML = '<div class="ticket"><p>No closed tickets. Please check back later.</p></div>';
      }
    } catch (err) {
      console.error('Failed to load closed tickets', err);
    }
  }

  // ===== Post Reply (generic) =====
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

  // ===== Socket / Real-time handling =====
  let socket = null;
  function setupSocketIfNeeded() {
    if (typeof io === 'undefined') return;
    const contactQueryId = window.CONTACT_QUERY_ID || null;
    if (!contactQueryId) return;

    try {
      socket = io({ path: '/socket.io', transports: ['polling', 'websocket'] });

      const ROOM = 'ticket_' + contactQueryId;
      socket.on('connect', () => {
        try { socket.emit('join', { room: ROOM }); } catch (e) {}
        console.log('[socket] connected', socket.id, 'joining', ROOM);
      });

socket.on('new_reply', (payload) => {
  try {
    // Normalize payload
    const normalized = {
      id: payload.id ?? null,
      local_id: payload.local_id ?? payload.localId ?? null,
      reply_text: payload.reply_text ?? payload.message ?? payload.content ?? '',
      is_admin: typeof payload.is_admin !== 'undefined' ? Number(payload.is_admin) : (payload.admin_identifier ? 1 : 0),
      created_at: payload.replied_at ?? payload.created_at ?? payload.timestamp ?? new Date().toISOString(),
      admin_identifier: payload.admin_identifier ?? payload.sender_email ?? null,
      sender_name: payload.sender_name ?? null,
      user_id: payload.user_id ?? null
    };

    // If payload includes local_id and we already have that optimistic node,
    // update the node and DO NOT append a duplicate.
    const localId = normalized.local_id;
    if (localId) {
      // Use CSS.escape if available to safely use in selector (fallback below)
      const esc = (s) => {
        if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(s);
        return String(s).replace(/(["\\])/g,'\\$1');
      };
      const selector = `#openThread [data-local-id="${esc(localId)}"]`;
      const existing = document.querySelector(selector);
      if (existing) {
        // Attach authoritative id, timestamp and (optionally) admin_identifier
        if (normalized.id) existing.setAttribute('data-reply-id', String(normalized.id));
        if (normalized.created_at) {
          const tms = (new Date(normalized.created_at)).getTime();
          if (!isNaN(tms)) existing.setAttribute('data-ts-ms', String(tms));
        }
        // optionally update sender label if server provided it
        if (normalized.admin_identifier || normalized.sender_name) {
          const header = existing.querySelector('.message-header strong');
          if (header) header.textContent = (normalized.admin_identifier || normalized.sender_name || (normalized.is_admin ? 'Admin' : 'You'));
        }
        // also update stored searchable attrs for fuzzy dedupe
        existing.setAttribute('data-norm-text', (function(){ 
          try { 
            // reuse your normalize method if present; simple fallback:
            return (normalized.reply_text || '').replace(/\s+/g,' ').trim().toLowerCase();
          } catch(e){ return (normalized.reply_text||'').toLowerCase(); }
        })());
        return; // *** important: do not render another node
      }
    }

    // If no optimistic node found, just render normally
    renderMessage(normalized, { containerId: 'openThread', prepend: false });

  } catch (e) {
    console.error('socket new_reply handler error', e);
  }
});


      socket.on('disconnect', (reason) => {
        console.log('[socket] disconnected', reason);
      });
    } catch (e) {
      console.warn('Could not initialize socket.io client', e);
    }
  }

  // ===== Initialize =====
  document.addEventListener('DOMContentLoaded', () => {
    // **Important:** start socket immediately so user joins room before admin may emit
    setupSocketIfNeeded();

    // Load UI after socket started (so incoming emits arrive)
    loadOpenTicket().then(() => {
      // nothing else required here
    }).catch((e) => {
      console.error('loadOpenTicket error', e);
    });

    loadClosedTickets();
  });

  window.CONVERSATION = {
    reloadOpen: loadOpenTicket,
    reloadClosed: loadClosedTickets,
    postReply,
    renderMessage
  };
})();