// chat-socket.js (improved)
// Place at /var/www/html/js/chat-socket.js (or your existing path)
// - Uses window.SOCKET_SERVER_URL if set (no trailing slash).
// - Falls back to same-origin proxied path "/socket.io" when SOCKET_SERVER_URL === "".
// - Prevents duplicate messages using a small recent-message cache.

(function () {
  // --- Config ---
  const SOCKET_SERVER =
    (typeof window.SOCKET_SERVER_URL !== "undefined" && window.SOCKET_SERVER_URL)
      ? String(window.SOCKET_SERVER_URL).replace(/\/$/, "")
      : ""; // "" means use same-origin proxied path (nginx location /socket.io/ -> node)

  const PUBLIC_TICKET = (window.TICKET_PUBLIC_ID || "").trim();
  const CURRENT_USER_ID = window.CURRENT_USER_ID || null;

  if (!PUBLIC_TICKET) {
    console.error("❌ chat-socket: Missing PUBLIC_TICKET (window.TICKET_PUBLIC_ID)");
    const c = document.getElementById("openTicketContainer");
    if (c) {
      c.innerHTML =
        '<div class="ticket"><p><em>Ticket ID missing. Open with ?ticket_id=...</em></p></div>';
    }
    return;
  }

  // --- Utilities ---
  function log(...args) { console.log("chat-socket:", ...args); }
  function warn(...args) { console.warn("chat-socket:", ...args); }
  function err(...args) { console.error("chat-socket:", ...args); }

  function escapeHtml(s) {
    return String(s || "").replace(/[&<>"']/g, (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m])
    );
  }

  // Simple dedupe: keep last 200 message keys (id|text|ts)
  const seen = new Set();
  function markSeenKey(k) {
    seen.add(k);
    if (seen.size > 200) {
      // drop earliest — Set doesn't expose order removal, so recreate with last 100
      const arr = Array.from(seen).slice(-100);
      seen.clear();
      arr.forEach(x => seen.add(x));
    }
  }
  function isSeenKey(k) { return seen.has(k); }

  function messageKey(msg) {
    // prefer unique id; fallback to reply_text + ts
    const id = msg.id || msg.reply_id || msg.reply_db_id || '';
    const text = (msg.reply_text || msg.message || "").slice(0, 200);
    const ts = msg.replied_at || msg.created_at || msg.timestamp || '';
    return `${id}|${text}|${ts}`;
  }

  // Append to UI (simple). You can override window.onSocketNewMessage for custom rendering
  function appendToChat(msg) {
    // avoid duplicates
    const key = messageKey(msg);
    if (isSeenKey(key)) {
      // duplicate — skip
      return;
    }
    markSeenKey(key);

    // If the page has a handler, call it and return
    if (typeof window.onSocketNewMessage === "function") {
      try {
        window.onSocketNewMessage(msg);
        return;
      } catch (e) {
        // fall through to default if custom handler errors
        err("onSocketNewMessage threw:", e);
      }
    }

    // Default simple renderer
    const container = document.getElementById("chat-messages");
    if (!container) {
      warn("chat-socket: #chat-messages container not found");
      return;
    }
    const el = document.createElement("div");
    const isAdmin = Number(msg.is_admin) === 1;
    el.className = isAdmin ? "msg admin" : "msg user";

    const who = isAdmin ? escapeHtml(msg.admin_identifier || "Admin") : "You";
    const txt = escapeHtml(msg.reply_text || msg.message || "");
    const ts = escapeHtml(msg.replied_at || msg.created_at || new Date().toISOString());

    el.innerHTML = `
      <div class="bubble">
        <strong>${who}:</strong> ${txt}
      </div>
      <div class="ts">${ts}</div>
    `;
    container.appendChild(el);
    // auto-scroll
    container.scrollTop = container.scrollHeight;
  }

  // --- Lookup internal numeric contact_query_id (if needed) ---
  async function lookupId() {
    const url = `/lookup_ticket_id.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}`;
    try {
      const res = await fetch(url, { credentials: "include" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const j = await res.json();
      if (j.error) throw new Error(j.error || "lookup_ticket_id returned error");
      if (!j.contact_query_id) throw new Error("No contact_query_id in response");
      return j.contact_query_id;
    } catch (e) {
      err("lookup_ticket_id failed:", e);
      const c = document.getElementById("openTicketContainer");
      if (c) c.innerHTML = `<div class="ticket"><p>Error looking up ticket: ${escapeHtml(e.message)}</p></div>`;
      throw e;
    }
  }

  // --- Main init ---
  async function init() {
    let contact_query_id;
    try {
      contact_query_id = await lookupId();
      log("Resolved contact_query_id:", contact_query_id);
    } catch (e) {
      return; // lookup failed, error already displayed
    }

    const ioOpts = {
      transports: ["websocket", "polling"],
      reconnection: true,
      reconnectionAttempts: Infinity,
      reconnectionDelay: 2000,
      withCredentials: true
    };

    // Set 'path' when using proxied same-origin socket.io; leave undefined when connecting
    // directly to SOCKET_SERVER (it will default). If SOCKET_SERVER === "" we rely on same-origin
    // path (/socket.io) which is typical when nginx proxies /socket.io/ to Node.
    if (!SOCKET_SERVER) {
      ioOpts.path = "/socket.io";
    } else {
      // when using a direct Node host, don't force path here
    }

    const socket = SOCKET_SERVER ? io(SOCKET_SERVER, ioOpts) : io(ioOpts);

    socket.on("connect", () => {
      log("CONNECTED -> socket.id:", socket.id);
      const joinPayload = {
        contact_query_id: contact_query_id, // numeric id returned by lookup
        ticket_public_id: PUBLIC_TICKET,   // TCK-...
        user_type: "user",
        user_id: CURRENT_USER_ID || null
      };
      socket.emit("join_ticket", joinPayload);
      log("Emitted join_ticket for:", joinPayload);
    });

    socket.on("reconnect_attempt", (n) => log("reconnect attempt:", n));
    socket.on("reconnect", (n) => log("reconnected:", n));
    socket.on("connect_error", (err) => warn("connect_error:", err && err.message ? err.message : err));
    socket.on("disconnect", (reason) => warn("socket disconnected:", reason));

    // Load initial thread (server returns structure you currently use)
    try {
      const t = await fetch(`/get_conversation.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}`, { credentials: "include" });
      if (!t.ok) throw new Error("Load conversation HTTP " + t.status);
      const json = await t.json().catch(() => null);
      if (!json) {
        warn("get_conversation returned no JSON");
      } else {
        // if json.replies is array
        const replies = Array.isArray(json.replies) ? json.replies : (Array.isArray(json) ? json : []);
        replies.forEach(r => appendToChat(r));
        if (json.hasOwnProperty("can_reply") && !json.can_reply) {
          const input = document.getElementById("chat-input");
          const btn = document.getElementById("chat-send");
          if (input) { input.disabled = true; input.placeholder = "This conversation is closed."; }
          if (btn) btn.disabled = true;
        }
      }
    } catch (e) {
      warn("Failed to load conversation:", e);
    }

    // Real-time incoming
    socket.on("new_message", (msg) => {
      try { appendToChat(msg); } catch (e) { err("appendToChat error:", e); }
    });
    socket.on("error_message", (e) => warn("socket error_message:", e));

    // Expose send function that your reply form calls
    window.sendChatMessage = async function (text) {
      if (!text || typeof text !== "string") return;
      // Build payload to emit; don't include optimistic DB id
      const payload = {
        contact_query_id: contact_query_id,
        ticket_public_id: PUBLIC_TICKET,
        is_admin: 0,
        admin_identifier: null,
        user_id: CURRENT_USER_ID || null,
        reply_text: text,
        // let server / PHP assign DB id and broadcast via /emit-reply to reflect canonical copy
      };

      // Optimistic render: only if you want; dedupe prevents duplication when server echoes
      appendToChat({ ...payload, replied_at: new Date().toISOString() });

      // Emit to socket server (server will re-broadcast to all room members)
      try {
        socket.emit("send_message", payload);
      } catch (e) {
        warn("socket.emit(send_message) failed:", e);
      }

      // Also send to your PHP endpoint to persist (you already likely do this via form + fetch)
      // NOTE: This library does not auto-call your insert PHP; your UI should still POST to contact_replies.php
    };

    // allow debugging access
    window.__chatSocket = socket;
    window.__chatSeen = seen;
  }

  // Run init when DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
