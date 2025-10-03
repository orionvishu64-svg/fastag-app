/* conversation.js — socket-free, no-poll version
   Keeps IDs/classes unchanged; formats timestamps to HH:MM IST;
   stacks closed ticket replies vertically; avoids auto-refresh.
*/

(() => {
  const ENDPOINT_GET_CONV = 'get_conversation.php';          // GET ?ticket_id=...
  const ENDPOINT_GET_CLOSED = 'get_closed_conversation.php';
  const ENDPOINT_ADD_REPLY = 'contact_replies.php';         // POST { query_id, message }

  // Utility: safe fetch JSON/text
  async function safeFetchJson(url, opts = {}) {
    opts.credentials = opts.credentials || 'include';
    opts.headers = opts.headers || {};
    if (opts.body && !(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
    }
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status} ${res.statusText} ${t}`);
    }
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) return res.json();
    return res.text();
  }

  // Format timestamp to HH:MM (24-hour) in India timezone
  function fmtTimeToIST(ts) {
    if (!ts) return '';
    // Accept both ISO strings and unix timestamps
    let d = new Date(ts);
    if (isNaN(d.getTime())) {
      // try numeric seconds
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
      // fallback
      const hh = String(d.getHours()).padStart(2,'0');
      const mm = String(d.getMinutes()).padStart(2,'0');
      return `${hh}:${mm}`;
    }
  }

  // Add message DOM into #openThread (keeps .message.user/.admin classes)
  function addMessageToThread(text, is_admin, timestamp) {
    const thread = document.getElementById('openThread');
    if (!thread) return;
    const div = document.createElement('div');
    div.className = `message ${Number(is_admin) === 1 ? 'admin' : 'user'}`;
    const ts = fmtTimeToIST(timestamp);
    div.innerHTML = `<div>${escapeHtml(text)}</div><div class="meta">${escapeHtml(ts)}</div>`;
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

  // Escape helper
  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  // Load open ticket and insert HTML (keeps same IDs so CSS works)
  async function loadOpenTicket() {
    try {
      const data = await safeFetchJson(ENDPOINT_GET_CONV, { method: 'GET' });
      const container = document.getElementById('openTicketContainer');
      if (!container) return;
      container.innerHTML = '';

      if (data && data.success && data.query) {
        const t = data.query;
        // build ticket HTML but preserve existing IDs
        const div = document.createElement('div');
        div.className = 'ticket';
        let inner = '';
        inner += `<div class="status-badge">${data.open ? 'Open' : 'Closed'}</div>`;
        inner += `<h4>Ticket #${escapeHtml(t.ticket_id || t.id)} - ${escapeHtml(t.subject || '')}</h4>`;
        inner += `<p><strong>Message:</strong> ${escapeHtml(t.message || '')}</p>`;
        inner += `<div id="openThread" class="replies"></div>`;
        inner += `<form id="replyForm"><textarea id="replyMessage" placeholder="Type your reply..." required></textarea><button type="submit">Reply</button></form>`;
        div.innerHTML = inner;
        container.appendChild(div);

        // populate replies
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
              // server expects query_id/message (if your backend is different, change these keys only)
              await postReply(t.id || t.query_id || t.ticket_id, msg);
              // append locally
              addMessageToThread(msg, 0, new Date().toISOString());
              if (msgEl) msgEl.value = '';
            } catch (err) {
              console.error('Send reply failed', err);
              alert('Failed to send reply. Please try again.');
            }
          };
          form.addEventListener('submit', handler);
          form.__replyHandler = handler;
        }
      } else {
        container.innerHTML = '<div class="ticket"><p>No open ticket found.</p></div>';
      }
    } catch (err) {
      console.error('Failed to load open ticket', err);
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
        container.innerHTML = '<div class="ticket"><p>No closed tickets.</p></div>';
      }
    } catch (err) {
      console.error('Failed to load closed tickets', err);
    }
  }

  // Post reply to server — keep param names that matched your backend earlier
  async function postReply(queryId, message) {
    if (!queryId || !message) throw new Error('Invalid params');
    const payload = { query_id: queryId, message: message };
    const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    if (!res || !res.success) {
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
