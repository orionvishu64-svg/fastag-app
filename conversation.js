/* conversation.js — socket-free, no polling, no refresh button */

(() => {
  const ENDPOINT_GET_CONV = 'get_conversation.php';
  const ENDPOINT_GET_CLOSED = 'get_closed_conversation.php';
  const ENDPOINT_ADD_REPLY = 'contact_replies.php';

  let currentOpenTicketId = null;

  async function safeFetchJson(url, opts = {}) {
    opts.credentials = 'include';
    if (opts.body && !(opts.body instanceof FormData)) {
      opts.headers = { ...(opts.headers || {}), 'Content-Type': 'application/json' };
    }
    const res = await fetch(url, opts);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ct = res.headers.get('content-type') || '';
    return ct.includes('application/json') ? res.json() : res.text();
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function loadOpenTicket() {
    const container = document.getElementById('openTicketContainer');
    if (!container) return;
    container.innerHTML = '<p>Loading...</p>';
    try {
      const data = await safeFetchJson(ENDPOINT_GET_CONV);
      if (!data.success || !data.query) {
        container.innerHTML = '<p>No tickets found.</p>';
        return;
      }

      const t = data.query;
      currentOpenTicketId = t.id;
      const replies = data.replies || [];

      container.innerHTML = `
        <div class="ticket">
          <h4>Ticket #${escapeHtml(t.ticket_id || t.id)} - ${escapeHtml(t.subject || '')}</h4>
          <p><b>Message:</b> ${escapeHtml(t.message || '')}</p>
          <div id="openThread" class="thread"></div>
          ${data.open ? `
            <form id="replyForm">
              <textarea id="replyMessage" placeholder="Type your reply..." required></textarea>
              <button type="submit">Reply</button>
            </form>
          ` : `<p><i>This ticket is closed.</i></p>`}
        </div>
      `;

      const thread = document.getElementById('openThread');
      replies.forEach(r => addMessageToThread(r.reply_text || r.message, r.is_admin, r.replied_at));

      if (data.open) {
        const form = document.getElementById('replyForm');
        form.addEventListener('submit', async e => {
          e.preventDefault();
          const msgEl = document.getElementById('replyMessage');
          const msg = msgEl.value.trim();
          if (!msg) return;
          try {
            await postReply(currentOpenTicketId, msg);
            addMessageToThread(msg, 0);
            msgEl.value = '';
          } catch (err) {
            alert('Failed to send reply: ' + err.message);
          }
        });
      }
    } catch (err) {
      container.innerHTML = `<p>Error: ${err.message}</p>`;
    }
  }

  async function loadClosedTickets() {
    const container = document.getElementById('closedTicketsContainer');
    if (!container) return;
    container.innerHTML = '<h3>Closed Tickets</h3>';
    try {
      const data = await safeFetchJson(ENDPOINT_GET_CLOSED);
      if (!data.success || !data.queries || !data.queries.length) {
        container.innerHTML += '<p>No closed tickets.</p>';
        return;
      }
      data.queries.forEach(t => {
        const replies = t.replies || [];
        let html = `<div class="ticket"><h4>${escapeHtml(t.ticket_id || t.id)} - ${escapeHtml(t.subject || '')}</h4>
                    <p><b>Message:</b> ${escapeHtml(t.message || '')}</p>`;
        replies.forEach(r => {
          html += `<div class="message ${Number(r.is_admin)===1?'admin':'user'}">
                     ${escapeHtml(r.reply_text)}<div class="meta">${escapeHtml(r.replied_at)}</div>
                   </div>`;
        });
        html += `<div class="meta">Closed: ${escapeHtml(t.closed_at || '')}</div></div>`;
        container.innerHTML += html;
      });
    } catch (err) {
      container.innerHTML += `<p>Error: ${err.message}</p>`;
    }
  }

  async function postReply(queryId, message) {
    const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
      method: 'POST',
      body: JSON.stringify({ query_id: queryId, message })
    });
    if (!res.success) throw new Error(res.message || 'Server rejected reply');
    return res;
  }

  function addMessageToThread(text, is_admin, ts) {
    const thread = document.getElementById('openThread');
    if (!thread) return;
    const div = document.createElement('div');
    div.className = `message ${Number(is_admin)===1?'admin':'user'}`;
    div.innerHTML = `<div class="bubble">${escapeHtml(text)}</div>
                     <div class="meta">${ts || 'just now'}</div>`;
    thread.appendChild(div);
    thread.scrollTop = thread.scrollHeight;
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadOpenTicket();
    loadClosedTickets();
  });
})();
