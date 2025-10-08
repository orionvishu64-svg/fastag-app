(() => {
  const ENDPOINT_GET_CONV = '/get_conversation.php';          // GET ?ticket_id=...
  const ENDPOINT_GET_CLOSED = '/get_closed_conversation.php';
  const ENDPOINT_ADD_REPLY = '/contact_replies.php';         // POST { ticket_id|query_id, message }

  // Utility: safe fetch JSON/text
  async function safeFetchJson(url, opts = {}) {
    // Ensure cookies are sent by default
    opts.credentials = opts.credentials ?? 'include';
    opts.headers = opts.headers ?? {};

    const bodyIsForm = opts.body instanceof FormData;

    // do the request
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text().catch(()=>'');
      throw new Error(`HTTP ${res.status} ${res.statusText} ${t}`);
    }

    const ct = (res.headers.get('content-type') || '').toLowerCase();

    // Prefer JSON parsing when content-type says so
    if (ct.includes('application/json')) {
      return res.json();
    }

    // Fallback: try to parse body as JSON if possible, otherwise return text
    const txt = await res.text().catch(()=>'');

    try {
      return txt ? JSON.parse(txt) : txt;
    } catch (e) {
      return txt;
    }
  }

  // Format timestamp to HH:MM IST
  function fmtTimeToIST(ts) {
    if (!ts) return '';
    let d = new Date(ts);
    if (isNaN(d.getTime())) {
      const n = Number(ts);
      if (!isNaN(n)) {
        d = new Date(n * (String(n).length === 10 ? 1000 : 1));
      } else return '';
    }
    try {
      const dtf = new Intl.DateTimeFormat('en-IN', {
        hour: '2-digit', minute: '2-digit',
        hour12: false, timeZone: 'Asia/Kolkata'
      });
      return dtf.format(d);
    } catch (e) {
      const hh = String(d.getHours()).padStart(2,'0');
      const mm = String(d.getMinutes()).padStart(2,'0');
      return `${hh}:${mm}`;
    }
  }

  // Escape helper
  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  // Add message DOM into #openThread (keeps .message.user/.admin classes)
  function addMessageToThread(text, is_admin, timestamp, admin_identifier, user_id) {
    const thread = document.getElementById('openThread');
    if (!thread) return;
    const div = document.createElement('div');
    div.className = `message ${Number(is_admin) === 1 ? 'admin' : 'user'}`;
    const who = Number(is_admin) === 1 ? (admin_identifier || 'Admin') : 'You';
    const ts = fmtTimeToIST(timestamp);
    div.innerHTML = `<div><strong>${escapeHtml(who)}:</strong> ${escapeHtml(text)}</div><div class="meta">${escapeHtml(ts)}</div>`;
    thread.appendChild(div);
    thread.scrollTop = thread.scrollHeight;
  }

  // Render closed ticket replies into .replies container (stack vertically)
  function renderClosedReplies(container, replies) {
    if (!container) return;
    container.innerHTML = '';
    (replies || []).forEach(r => {
      const div = document.createElement('div');
      const is_admin = (typeof r.is_admin !== 'undefined') ? Number(r.is_admin) === 1 : Boolean(r.admin_identifier);
      div.className = `message ${is_admin ? 'admin' : 'user'}`;
      const text = r.reply_text ?? r.message ?? '';
      const ts = fmtTimeToIST(r.replied_at ?? r.created_at ?? r.created_at_ts);
      div.innerHTML = `<div>${escapeHtml(text)}</div><div class="meta">${escapeHtml(ts)}</div>`;
      container.appendChild(div);
    });
  }

  // Build ticket display and replies
  async function loadOpenTicket() {
    const ticketPublic = (window.TICKET_PUBLIC_ID || '').trim();
    let url = ENDPOINT_GET_CONV;
    if (ticketPublic) url += '?ticket_id=' + encodeURIComponent(ticketPublic);

    try {
      const data = await safeFetchJson(url, { method: 'GET' });
      const container = document.getElementById('openTicketContainer');
      if (!container) return;
      container.innerHTML = '';

      // If backend returned empty array or empty string, treat as no open ticket
      if (!data || (Array.isArray(data) && data.length === 0) || (typeof data === 'object' && Object.keys(data).length === 0)) {
        container.innerHTML = '<div class="ticket"><p>No open ticket found.</p></div>';
        return;
      }

      if (data.success === false && data.message) {
        container.innerHTML = `<div class="ticket"><p>Error: ${escapeHtml(data.message)}</p></div>`;
        return;
      }

      // If backend used older shape (just array), try to be flexible
      const t = data.query || (Array.isArray(data) ? data[0] : (data.ticket || data));
      if (!t) {
        container.innerHTML = '<div class="ticket"><p>No open ticket found.</p></div>';
        return;
      }

      // build ticket HTML but preserve existing IDs
      const div = document.createElement('div');
      div.className = 'ticket';
      let inner = '';
      inner += `<div class="status-badge">${data.open ? 'Open' : (t.status ? (t.status.charAt(0).toUpperCase()+t.status.slice(1)) : 'Unknown')}</div>`;
      inner += `<h4>Ticket #${escapeHtml(t.ticket_id || t.id || t.query_id)} - ${escapeHtml(t.subject || '')}</h4>`;
      inner += `<p><strong>Message:</strong> ${escapeHtml(t.message || '')}</p>`;
      inner += `<div id="openThread" class="replies"></div>`;
      inner += `<form id="replyForm"><textarea id="replyMessage" placeholder="Type your reply..." required></textarea><button type="submit">Reply</button></form>`;
      div.innerHTML = inner;
      container.appendChild(div);

      // populate replies (if provided)
      const threadEl = document.getElementById('openThread');
      (data.replies || []).forEach(r => {
        const text = r.reply_text ?? r.message ?? '';
        const is_admin = (typeof r.is_admin !== 'undefined') ? Number(r.is_admin) === 1 : Boolean(r.admin_identifier);
        const ts = r.replied_at ?? r.created_at;
        const replyDiv = document.createElement('div');
        replyDiv.className = `message ${is_admin ? 'admin' : 'user'}`;
        replyDiv.innerHTML = `<div>${escapeHtml(text)}</div><div class="meta">${escapeHtml(fmtTimeToIST(ts))}</div>`;
        threadEl.appendChild(replyDiv);
      });

      // attach submit handler for replyForm — remove previous to avoid double binding
      const form = document.getElementById('replyForm');
      if (form) {
        if (form.__replyHandler) form.removeEventListener('submit', form.__replyHandler);
        const handler = async (e) => {
          e.preventDefault();
          const msgEl = document.getElementById('replyMessage');
          const msg = (msgEl && msgEl.value || '').trim();
          if (!msg) return;
          try {
            await postReply(t.id || t.query_id || t.ticket_id, msg);
            addMessageToThread(msg, 0, new Date().toISOString());
            if (msgEl) msgEl.value = '';
          } catch (err) {
            console.error('Send reply failed', err);
            alert('Failed to send reply. Please try again after some time.');
          }
        };
        form.addEventListener('submit', handler);
        form.__replyHandler = handler;
      }

    } catch (err) {
      console.error('Failed to load open ticket', err);
      const container = document.getElementById('openTicketContainer');
      if (container) container.innerHTML = `<div class="ticket"><p>Error loading ticket: ${escapeHtml(err.message || String(err))}</p></div>`;
    }
  }

  // Load closed tickets — ensure replies stack vertically
  async function loadClosedTickets() {
    try {
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
        container.innerHTML = '<div class="ticket"><p>No closed tickets. Please check back later</p></div>';
      }
    } catch (err) {
      console.error('Failed to load closed tickets', err);
    }
  }

  // Post reply to server — include both ticket_id and query_id to be compatible
  async function postReply(queryIdOrTicketId, message) {
    if (!queryIdOrTicketId || !message) throw new Error('Invalid params');
    // try to post as form data (many PHP endpoints expect form-data)
    const form = new FormData();
    // include both names (server will use whichever it expects)
    form.append('ticket_id', queryIdOrTicketId);
    form.append('query_id', queryIdOrTicketId);
    form.append('message', message);
    form.append('reply_text', message);

    const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
      method: 'POST',
      body: form,
      credentials: 'include'
    });
    if (!res || (res.success === false)) {
      throw new Error(res && res.message ? res.message : 'Server rejected reply');
    }
    return res;
  }

  // initialize: load once, do not poll
  document.addEventListener('DOMContentLoaded', () => {
    loadOpenTicket();
    loadClosedTickets();
  });

  // Expose small API if you want to refresh manually from console
  window.CONVERSATION = {
    reloadOpen: loadOpenTicket,
    reloadClosed: loadClosedTickets,
    postReply
  };
})();
