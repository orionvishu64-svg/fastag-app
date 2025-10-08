(if (window.__chatSocketAlreadyInitialized) { console.warn("chat-socket.js already initialized; skipping duplicate load."); return; }
window.__chatSocketAlreadyInitialized = true;
window.__chatSocketInitCount = (window.__chatSocketInitCount || 0) + 1;
console.log("chat-socket.js init count:", window.__chatSocketInitCount);

)
// chat-socket.js - robust client for ticket chat (replace /var/www/html/js/chat-socket.js)
// Paste this full file replacing the old one.

(function () {
  'use strict';

  // --- Config (can be overridden by page) ---
  const SOCKET_SERVER = (typeof window.SOCKET_SERVER_URL !== 'undefined' && window.SOCKET_SERVER_URL)
    ? String(window.SOCKET_SERVER_URL).replace(/\/$/, '')
    : ''; // empty -> same origin /socket.io proxy

  const PUBLIC_TICKET = (window.TICKET_PUBLIC_ID || '').toString().trim();
  const CURRENT_USER_ID = (typeof window.CURRENT_USER_ID !== 'undefined') ? window.CURRENT_USER_ID : null;

  if (!PUBLIC_TICKET) {
    console.error('chat-socket: Missing PUBLIC_TICKET (window.TICKET_PUBLIC_ID)');
    return;
  }

  // --- Helpers ---
  function log(...a) { console.log('chat-socket:', ...a); }
  function warn(...a) { console.warn('chat-socket:', ...a); }
  function err(...a) { console.error('chat-socket:', ...a); }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  function normText(s) {
    if (!s) return '';
    return s.toString()
      .replace(/^[^A-Za-z0-9]*/,'')
      .replace(/^[A-Za-z0-9_#-]+:\s*/,'')
      .toLowerCase()
      .replace(/[^\w\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 300);
  }

  function genLocalId() {
    return `local_${Date.now().toString(36)}_${Math.floor(Math.random()*90000+10000).toString(36)}`;
  }

  // --- Dedupe set (keys) ---
  const seen = new Set();
  function messageKey(msg) {
    // If server-side id exists, build a stable key that does NOT depend on timestamp
    const id = msg.id || msg.reply_id || msg.reply_db_id || '';
    const local = msg.local_id || msg.localId || msg.localid || '';
    if (id) {
      // stable key based on id + local if present
      return `${String(id)}|${String(local)}`;
    }
    // fallback: use normalized text plus timestamp (less stable)
    const text = (msg.reply_text || msg.message || '').toString().replace(/\s+/g, ' ').slice(0, 200);
    const ts = msg.replied_at || msg.created_at || msg.timestamp || '';
    return `NOID|${local}|${text}|${ts}`;
  }
  function markSeen(msg) { const k = messageKey(msg); if (k) { seen.add(k); if (seen.size > 5000) { const arr = Array.from(seen).slice(-2000); seen.clear(); arr.forEach(x => seen.add(x)); } } }
  function isSeen(msg) { const k = messageKey(msg); return !!k && seen.has(k); }

  // --- Container discovery ---
  const CONTAINER_SELECTORS = [
    '#chat-messages',
    '#messages_container',
    '#chat-messages_container',
    '#messages_container_closed',
    '#chat-messages-closed',
    '#admin-chat-messages',
    '#openThread',
    '#openTicketContainer',
    '.messages',
    '.chat-messages-container',
    '.conversation-messages'
  ];

  function findContainerOnce() {
    for (const sel of CONTAINER_SELECTORS) {
      try {
        const el = document.querySelector(sel);
        if (el) return el;
      } catch (e) { /* ignore bad selector */ }
    }
    return null;
  }

  function waitForContainer(timeoutMs = 3000) {
    return new Promise((resolve, reject) => {
      const existing = findContainerOnce();
      if (existing) return resolve(existing);
      const obs = new MutationObserver(() => {
        const el = findContainerOnce();
        if (el) { obs.disconnect(); resolve(el); }
      });
      obs.observe(document.body, { childList: true, subtree: true });
      setTimeout(() => { obs.disconnect(); const el = findContainerOnce(); if (el) resolve(el); else reject(new Error('container not found')); }, timeoutMs);
    });
  }

  // Build a small index of nodes from current DOM (replyId/localId/text/ts)
  function buildDOMIndex() {
    const idx = [];
    for (const sel of CONTAINER_SELECTORS) {
      try {
        const container = document.querySelector(sel);
        if (!container) continue;
        const nodes = Array.from(container.children || []);
        for (const n of nodes) {
          if (!container.contains(n)) continue;
          // read reply id or local id attributes produced by our client
          const replyId = n.dataset && (n.dataset.replyId || n.dataset.reply_id) ? (n.dataset.replyId || n.dataset.reply_id) : null;
          const localId = n.dataset && n.dataset.localId ? n.dataset.localId : null;
          const bubble = (n.querySelector && (n.querySelector('.bubble') || n.querySelector('.message') || n.querySelector('.text')));
          const rawText = bubble ? (bubble.textContent || '') : (n.textContent || '');
          const tsEl = (n.querySelector && (n.querySelector('.ts') || n.querySelector('time') || n.querySelector('.time')));
          const rawTs = tsEl ? (tsEl.textContent || tsEl.innerText || '') : '';
          const normalized = normText(rawText);
          if (!normalized) continue;
          idx.push({ sel, node: n, text: normalized, ts: rawTs ? rawTs.toString().trim() : '', localId: localId || null, replyId: replyId || null });
        }
      } catch (e) { /* ignore */ }
    }
    return idx;
  }

  function createMessageNode(msg, { localId } = {}) {
    const el = document.createElement('div');
    const isAdmin = Number(msg.is_admin) === 1;
    el.className = isAdmin ? 'msg admin' : 'msg user';
    if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
    if (localId) el.setAttribute('data-local-id', String(localId));
    const who = isAdmin ? (msg.admin_identifier || 'Admin') : (msg.user_name || 'You');
    const txt = msg.reply_text || msg.message || '';
    const ts = msg.replied_at || msg.created_at || new Date().toISOString();
    el.innerHTML = `<div class="bubble"><strong>${escapeHtml(who)}:</strong> ${escapeHtml(txt)}</div><div class="ts">${escapeHtml(ts)}</div>`;
    return el;
  }

  function appendToChat(msg, opts = {}) {
    try {
      if (isSeen(msg)) return false;
      const container = findContainerOnce();
      if (!container) {
        warn('chat-socket: messages container not found (tried selectors)');
        return false;
      }
      // Avoid appending the same DOM node object twice
      if (msg._appendedNode && container.contains(msg._appendedNode)) {
        markSeen(msg);
        return false;
      }
      const node = createMessageNode(msg, { localId: opts.localId || null });
      container.appendChild(node);
      container.scrollTop = container.scrollHeight;
      // mark seen using messageKey (prefers id/local)
      const toMark = Object.assign({}, msg);
      if (opts.localId) toMark.local_id = opts.localId;
      markSeen(toMark);
      // store reference on the msg object to avoid double-appending same object in memory
      msg._appendedNode = node;
      return true;
    } catch (e) {
      err('appendToChat error', e);
      return false;
    }
  }

  // Mark existing DOM nodes as seen (important to avoid initial fetch duplicates)
  function markSeenFromDOMIndex() {
    const index = buildDOMIndex();
    let count = 0;
    for (const item of index) {
      const fake = { reply_text: item.text, replied_at: item.ts };
      if (item.replyId) fake.reply_id = item.replyId;
      if (item.localId) fake.local_id = item.localId;
      markSeen(fake);
      count++;
    }
    return count;
  }

  // Check if incoming server message matches something already in DOM/optimistic
  function incomingMatchesExisting(msg) {
    // 1) local_id exact match (most reliable)
    const local = msg.local_id || msg.localId || msg.localid;
    if (local) {
      const el = document.querySelector(`[data-local-id="${CSS.escape(String(local))}"]`);
      if (el) {
        // update reply-id if server provided one
        if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
        markSeen(msg);
        return true;
      }
    }

    // 2) server id exact match (use stable key)
    const id = msg.id || msg.reply_id || msg.reply_db_id;
    if (id) {
      const el = document.querySelector(`[data-reply-id="${CSS.escape(String(id))}"]`);
      if (el) { markSeen(msg); return true; }
    }

    // 3) fallback fuzzy match by normalized text + timestamp within delta
    const index = buildDOMIndex();
    const replyNorm = normText(msg.reply_text || msg.message || '');
    const replyTs = (msg.replied_at || msg.created_at || '').toString().trim();
    if (!replyNorm) return false;

    for (const d of index) {
      const shortPartial = replyNorm.split(' ').slice(0,4).join(' ');
      if (d.text.includes(replyNorm) || replyNorm.includes(d.text) || (shortPartial && d.text.includes(shortPartial))) {
        if (replyTs && d.ts) {
          const t1 = Date.parse(replyTs);
          const t2 = Date.parse(d.ts);
          if (!isNaN(t1) && !isNaN(t2)) {
            if (Math.abs(t1 - t2) <= 15000) { markSeen(msg); return true; } // 15s tolerance
          } else {
            markSeen(msg); return true;
          }
        } else {
          if (replyNorm.length > 4) { markSeen(msg); return true; }
        }
      }
    }
    return false;
  }

  // --- Socket + init flow ---
  async function lookupContactQueryId() {
    try {
      const url = `/lookup_ticket_id.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}&json=1`;
      const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const j = await res.json().catch(()=>null);
      if (!j || j.error) throw new Error(j && j.error ? j.error : 'Invalid response');
      return j.contact_query_id || null;
    } catch (e) {
      warn('lookup_ticket_id failed (continuing with public ticket):', e && e.message ? e.message : e);
      return null;
    }
  }

  async function init() {
    try {
      await waitForContainer(2500);
      log('messages container present (or will be found).');
    } catch (e) {
      warn('messages container not found quickly; continuing.');
    }

    // Mark existing server-rendered DOM messages as seen so we don't re-append them
    const marked = markSeenFromDOMIndex();
    if (marked) log('chat-socket: marked existing DOM messages as seen:', marked);

    const contact_query_id = await lookupContactQueryId();
    if (contact_query_id) log('Resolved contact_query_id:', contact_query_id);

    const ioOpts = { transports: ['websocket','polling'], reconnection: true, reconnectionAttempts: Infinity, reconnectionDelay: 2000, withCredentials: true };
    if (!SOCKET_SERVER) ioOpts.path = ioOpts.path || '/socket.io';
    const socket = SOCKET_SERVER ? io(SOCKET_SERVER, ioOpts) : io(ioOpts);

    socket.on('connect', () => {
      log('CONNECTED -> socket.id:', socket.id);
      const joinPayload = { contact_query_id: contact_query_id, ticket_public_id: PUBLIC_TICKET, user_type: 'user', user_id: CURRENT_USER_ID || null };
      try { socket.emit('join_ticket', joinPayload); log('Emitted join_ticket for:', joinPayload); } catch (e) { warn('join_ticket emit failed', e); }
    });

    socket.on('reconnect', (n) => {
      log('RECONNECTED -> re-emitting join_ticket', n);
      try { socket.emit('join_ticket', { contact_query_id: contact_query_id, ticket_public_id: PUBLIC_TICKET, user_type: 'user', user_id: CURRENT_USER_ID || null }); } catch (e) { warn('rejoin failed', e); }
    });

    socket.on('reconnect_attempt', (n) => log('reconnect attempt:', n));
    socket.on('connect_error', (e) => warn('connect_error:', e && e.message ? e.message : e));
    socket.on('disconnect', (reason) => warn('socket disconnected:', reason));

    // Load initial thread and append only unseen replies
    try {
      const t = await fetch(`/get_conversation.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}&json=1`, { credentials: 'include', headers: { Accept: 'application/json' } });
      if (!t.ok) throw new Error('Load conversation HTTP ' + t.status);
      const json = await t.json().catch(()=>null);
      if (!json) { warn('get_conversation returned no JSON'); }
      else {
        const replies = Array.isArray(json.replies) ? json.replies : (Array.isArray(json) ? json : []);
        let appended = 0, skipped = 0;
        // build index of current DOM for matching
        const currentIndex = buildDOMIndex();

        for (const r of replies) {
          if (isSeen(r)) { skipped++; continue; }
          let exists = false;
          if (r.id || r.reply_id) {
            const el = document.querySelector(`[data-reply-id="${CSS.escape(String(r.id || r.reply_id))}"]`);
            if (el) exists = true;
          }
          if (!exists) {
            const norm = normText(r.reply_text || r.message || '');
            const ts = (r.replied_at || r.created_at || '').toString().trim();
            for (const d of currentIndex) {
              if (!norm) break;
              const shortPartial = norm.split(' ').slice(0,4).join(' ');
              if (d.text.includes(norm) || norm.includes(d.text) || (shortPartial && d.text.includes(shortPartial))) {
                if (ts && d.ts) {
                  const t1 = Date.parse(ts), t2 = Date.parse(d.ts);
                  if (!isNaN(t1) && !isNaN(t2) && Math.abs(t1 - t2) <= 15000) { exists = true; break; }
                } else {
                  if (norm.length > 4) { exists = true; break; }
                }
              }
            }
          }
          if (exists) { markSeen(r); skipped++; continue; }
          if (appendToChat(r)) appended++; else markSeen(r);
        }
        log('initial fetch - appended:', appended, 'skipped/seen:', skipped);

        if (json.hasOwnProperty('can_reply') && !json.can_reply) {
          const input = document.getElementById('chat-input');
          const btn = document.getElementById('chat-send');
          if (input) { input.disabled = true; input.placeholder = 'This conversation is closed.'; }
          if (btn) btn.disabled = true;
        }
      }
    } catch (e) {
      warn('Failed to load conversation:', e);
    }

    // server pushes
    socket.on('new_message', (msg) => {
      try {
        if (incomingMatchesExisting(msg)) {
          log('chat-socket: skipped server-echo new_message (matched existing DOM/local_id/id)');
          return;
        }
        appendToChat(msg);
      } catch (e) { err('new_message handler error', e); }
    });

    socket.on('error_message', (e) => warn('socket error_message:', e));

    // Expose socket for debug
    window.__chatSocket = socket;
    window.__chatSeen = seen;

    // Expose send function (UI should call)
    window.sendChatMessage = function (text) {
      if (!text || typeof text !== 'string') return;
      const localId = genLocalId();
      const payload = {
        contact_query_id: null,
        ticket_public_id: PUBLIC_TICKET,
        is_admin: 0,
        admin_identifier: null,
        user_id: CURRENT_USER_ID || null,
        reply_text: text,
        local_id: localId
      };

      // optimistic render with localId
      appendToChat(Object.assign({}, payload, { replied_at: new Date().toISOString() }), { localId });

      try {
        window.__chatSocket && window.__chatSocket.emit && window.__chatSocket.emit('send_message', payload);
      } catch (e) {
        warn('socket.emit(send_message) failed:', e);
      }

      // If your UI also POSTs to backend (contact_replies.php) ensure it sends the same local_id
      // Example (uncomment if you want this auto-post here):
      /*
      fetch('/contact_replies.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query_id: /* numeric id if available * /, message: text, local_id: localId, ticket_id: PUBLIC_TICKET })
      }).then(r=>r.json()).then(j=>console.log('saved', j)).catch(e=>console.error(e));
      */
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
