
// conversation.js

// ------------------ Token-based socket connect + auto-refresh ------------------
const SOCKET_SERVER = window.location.origin;  // e.g. http://15.207.50.101
const SOCKET_PATH = '/socket.io/';
const TOKEN_REFRESH_MARGIN = 20; // seconds before expiry to refresh

let socket = null;            // single socket instance
let socketToken = null;       // current token string
let tokenExpiresAt = 0;       // epoch seconds when token expires
let refreshTimer = null;      // timer id

async function fetchSocketToken() {
  const res = await fetch('/socket_auth.php?action=issue', {
    credentials: 'include'
  });
  if (!res.ok) {
    const text = await res.text().catch(()=> '');
    throw new Error('Token fetch failed: ' + res.status + ' ' + text);
  }
  const data = await res.json();
  if (!data.token || !data.expires) throw new Error('Invalid token response');
  return data;
}

function scheduleTokenRefresh() {
  if (refreshTimer) {
    clearTimeout(refreshTimer);
    refreshTimer = null;
  }
  const now = Math.floor(Date.now() / 1000);
  const expires = tokenExpiresAt || (now + 60);
  const refreshAt = Math.max(now + 1, expires - TOKEN_REFRESH_MARGIN);
  const ms = Math.max(1000, (refreshAt - now) * 1000);

  refreshTimer = setTimeout(async () => {
    try {
      const data = await fetchSocketToken();
      socketToken = data.token;
      tokenExpiresAt = data.expires;
      // update socket auth and reconnect if needed
      if (socket) {
        socket.auth = { token: socketToken };
        if (!socket.connected) socket.connect();
      }
      // schedule next refresh
      scheduleTokenRefresh();
      console.log('[socket] token refreshed, expires at', tokenExpiresAt);
    } catch (err) {
      console.warn('[socket] token refresh failed:', err);
      // retry soon
      refreshTimer = setTimeout(scheduleTokenRefresh, 10000);
    }
  }, ms);
}

function bindSocketListeners() {
  if (!socket) return;

  // ensure we don't double-bind
  socket.off('conversationData');
  socket.on('conversationData', (data) => {
    if (data.success) loadOpenTicket();
  });

  socket.off('newReplyAdded');
  socket.on('newReplyAdded', (data) => {
    if (data.success) {
      loadOpenTicket();
    }
  });

  socket.off('new_message');
  socket.on('new_message', (data) => {
    if (data.ticket_id === currentOpenTicketId && data.is_admin === 1) {
      addMessageToThread(data.text, 1);
    }
    scrollToMostRecent({ smooth: true });
  });

  socket.off('reply');
  socket.on('reply', (r) => {
    if (!r) return;
    appendReplyToConversationDOM_user(r);
  });

  // join room on connection if we already know ticket id
  socket.off('connect');
  socket.on('connect', () => {
    console.log('user socket connected', socket.id);
    if (currentOpenTicketId) {
      socket.emit('openTicketRoom', { ticket_id: String(currentOpenTicketId) });
      console.log('user socket joined room', currentOpenTicketId);
    }
  });

  socket.off('connect_error');
  socket.on('connect_error', async (err) => {
    console.warn('socket connect_error', err && err.message);
    if (err && err.message && err.message.toLowerCase().includes('auth')) {
      try {
        const data = await fetchSocketToken();
        socketToken = data.token;
        tokenExpiresAt = data.expires;
        socket.auth = { token: socketToken };
        socket.connect();
      } catch (e) {
        console.error('reconnect token fetch failed', e);
      }
    }
  });

  socket.off('disconnect');
  socket.on('disconnect', (reason) => {
    console.log('socket disconnected', reason);
  });
}

async function connectSocket() {
  try {
    // fetch token if missing or near expiry
    const now = Math.floor(Date.now()/1000);
    if (!socketToken || (tokenExpiresAt && tokenExpiresAt <= now + TOKEN_REFRESH_MARGIN)) {
      const data = await fetchSocketToken();
      socketToken = data.token;
      tokenExpiresAt = data.expires;
    }

    // if socket exists, update auth and connect
    if (socket) {
      socket.auth = { token: socketToken };
      if (!socket.connected) socket.connect();
      return socket;
    }

    // create socket
    socket = io(SOCKET_SERVER, {
  path: SOCKET_PATH,
  transports: ['websocket'],
  auth: { token: socketToken },
  withCredentials: true
});


    window.userSocket = socket; // keep global reference if other scripts use it

    // attach listeners
    bindSocketListeners();

    // schedule refresh
    scheduleTokenRefresh();

    return socket;
  } catch (err) {
    console.error('connectSocket failed:', err);
    throw err;
  }
}
// ------------- keep currentOpenTicketId global -------------
let currentOpenTicketId = null;

// ------------- existing functions (unchanged) -------------
// --- Load open ticket ---
async function loadOpenTicket() {
  try {
    const res = await fetch("get_conversation.php", { credentials: "include" });
    const data = await res.json();
    const container = document.getElementById("openTicketContainer");
    container.innerHTML = "";

    if (data.success && data.query) {
      const t = data.query;
      currentOpenTicketId = t.id;

      if (data.open) {
        // --- Show open/in-progress ticket ---
        container.innerHTML = `<div class="ticket">
          <h4>Open Ticket: ${t.ticket_id} - ${t.subject}</h4>
          <p><b>Message:</b> ${t.message}</p>
          <div id="openThread"></div>
          <form id="replyForm">
            <textarea id="replyMessage" placeholder="Type your reply..." required></textarea>
            <button type="submit">Reply</button>
          </form>
        </div>`;

        const thread = document.getElementById("openThread");
        // clear any previous thread (defensive)
        thread.innerHTML = '';
        (data.replies || []).forEach(r => addMessageToThread(r.reply_text, r.is_admin));

        // --- Handle user reply ---
        document.getElementById("replyForm").addEventListener("submit", (e) => {
          e.preventDefault();
          const msgEl = document.getElementById("replyMessage");
          const msg = msgEl.value.trim();
          if (!msg) return;

          // emit reply via socket (ensure socket exists)
          if (socket && socket.connected) {
            socket.emit("sendReply", { query_id: t.id, message: msg });
          } else {
            // fallback: you might want to POST to PHP endpoint if socket unavailable
            console.warn('Socket not connected — reply will be attempted locally and will not be sent to server in real-time.');
            // optional: POST fallback
          }

          msgEl.value = "";
          addMessageToThread(msg, 0); // show immediately in UI
        });

      } else {
        // --- Ticket exists but it's closed ---
        container.innerHTML = `<p>You have a closed ticket (#${t.ticket_id}).</p>`;
        currentOpenTicketId = null;
      }
    } else {
      container.innerHTML = `<p>No tickets found.</p>`;
      currentOpenTicketId = null;
    }
  } catch (err) {
    console.error("Failed to load open ticket:", err);
  }
}

// --- Load closed tickets ---
async function loadClosedTickets() {
  try {
    const res = await fetch("get_closed_conversation.php", { credentials: "include" });
    const data = await res.json();
    const container = document.getElementById("closedTicketsContainer");
    container.innerHTML = "<h3>Closed Tickets</h3>";

    if (data.success && Array.isArray(data.queries) && data.queries.length > 0) {
      data.queries.forEach(t => {
        let html = `<div class="ticket">
          <h4>${t.ticket_id} - ${t.subject}</h4>
          <p><b>Message:</b> ${t.message}</p>`;

        (t.replies || []).forEach(r => {
          html += `<div class="message ${r.is_admin==1 ? "admin" : "user"}">
                     ${escapeHtml(r.reply_text)}<div class="meta">${r.replied_at}</div>
                   </div>`;
        });

        html += `<div class="meta">Closed on: ${t.closed_at || t.submitted_at}</div></div>`;
        container.innerHTML += html;
      });
    } else {
      container.innerHTML += "<p>No closed tickets.</p>";
    }
  } catch (err) {
    console.error("Failed to load closed tickets:", err);
  }
}

// --- Append message to open thread ---
function addMessageToThread(text, is_admin) {
  const thread = document.getElementById("openThread");
  if (!thread) return;

  const div = document.createElement("div");
  div.className = `message ${is_admin==1 ? "admin" : "user"}`;
  div.innerHTML = `${escapeHtml(text)}<div class="meta">just now</div>`;
  thread.appendChild(div);
  thread.scrollTop = thread.scrollHeight;
}

// --- Escape helper used above and elsewhere ---
function escapeHtml(s) {
  return String(s || '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

// ---------------- Socket listeners binding function ----------------
function bindSocketListeners() {
  if (!socket) return;

  // Refresh open ticket when server sends updated conversation
  socket.off('conversationData');
  socket.on("conversationData", (data) => {
    if (data.success) loadOpenTicket();
  });

  // Add reply confirmation (optional)
  socket.off('newReplyAdded');
  socket.on("newReplyAdded", (data) => {
    if (data.success) {
      // reload open ticket to show all messages
      loadOpenTicket();
    }
  });

  // Listen for live admin replies
  socket.off('new_message');
  socket.on("new_message", (data) => {
    if (data.ticket_id === currentOpenTicketId && data.is_admin === 1) {
      addMessageToThread(data.text, 1);
    }
    // also trigger scroll
    scrollToMostRecent({ smooth: true });
  });

  // Old reply event handling
  socket.off('reply');
  socket.on('reply', (r) => {
    console.log('user: live reply event', r);
    if (!r) return;
    appendReplyToConversationDOM_user(r);
  });

  // connect join room if already connected and have ticket id
  socket.off('connect');
  socket.on('connect', () => {
    console.log('user socket connected', socket.id);
    if (currentOpenTicketId) {
      socket.emit('openTicketRoom', { ticket_id: String(currentOpenTicketId) });
      console.log('user socket joined room', currentOpenTicketId);
    }
  });
}

// --- Append reply DOM helper from your original code ---
function appendReplyToConversationDOM_user(r) {
  const container = document.querySelector('#conversationList');
  if (!container) return;
  const text = r.reply_text ?? r.message ?? '';
  const isAdmin = (typeof r.is_admin !== 'undefined') ? Boolean(Number(r.is_admin)) : Boolean(r.admin_identifier);
  const ts = r.created_at ?? r.replied_at ?? new Date().toISOString();

  const wrap = document.createElement('div');
  wrap.className = 'conversation-item ' + (isAdmin ? 'reply-admin' : 'reply-user');

  const meta = document.createElement('div');
  meta.className = 'conv-meta';
  meta.textContent = `${isAdmin ? 'Admin' : 'You'} • ${new Date(ts).toLocaleString()}`;

  const body = document.createElement('div');
  body.className = 'conv-text';
  body.innerHTML = escapeHtml(text).replace(/\n/g,'<br>');

  wrap.appendChild(meta);
  wrap.appendChild(body);
  container.appendChild(wrap);
  setTimeout(()=> container.scrollTop = container.scrollHeight, 20);
}

// --- Scroll utilities (unchanged) ---
function scrollToMostRecent({ smooth = true } = {}) {
  const scrollBehavior = smooth ? "smooth" : "auto";

  // Scroll main conversation area
  const conversationArea = document.getElementById("conversationArea");
  if (conversationArea) {
    conversationArea.scrollTo({
      top: conversationArea.scrollHeight,
      behavior: scrollBehavior
    });
  }

  // Scroll the open ticket thread
  const openThread = document.getElementById("openThread");
  if (openThread) {
    openThread.scrollTo({
      top: openThread.scrollHeight,
      behavior: scrollBehavior
    });
  }

  // Scroll all closed ticket threads
  const closedThreads = document.querySelectorAll("#closedTicketsContainer .ticket");
  closedThreads.forEach(ticket => {
    const messages = ticket.querySelectorAll(".message");
    if (messages.length) {
      const lastMessage = messages[messages.length - 1];
      lastMessage.scrollIntoView({ behavior: scrollBehavior, block: "end" });
    }
  });
}

// --- Initial flow: connect socket THEN load UI data ---
connectSocket().then(() => {
  loadOpenTicket();
  loadClosedTickets();
}).catch(() => {
  loadOpenTicket();
  loadClosedTickets();
});

// cleanup on unload
window.addEventListener('beforeunload', () => {
  if (socket) socket.disconnect();
  if (refreshTimer) clearTimeout(refreshTimer);
});
