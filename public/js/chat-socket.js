// chat-socket.js - robust client for ticket chat (replace /var/www/html/public/js/chat-socket.js)
(function () {
  'use strict';

  // config overrides
  const SOCKET_SERVER = (typeof window.SOCKET_SERVER_URL !== 'undefined' && window.SOCKET_SERVER_URL)
    ? String(window.SOCKET_SERVER_URL).replace(/\/$/, '')
    : '';
  const PUBLIC_TICKET = (window.TICKET_PUBLIC_ID || '').toString().trim();
  const CURRENT_USER_ID = (typeof window.CURRENT_USER_ID !== 'undefined') ? window.CURRENT_USER_ID : null;
  let contactQueryId = null;

  if (!PUBLIC_TICKET) {
    console.error('chat-socket: Missing PUBLIC_TICKET (window.TICKET_PUBLIC_ID)');
    return;
  }

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
      .replace(/^[^\w]*/,'')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 500)
      .toLowerCase();
  }

  function genLocalId() {
    return `local_${Date.now().toString(36)}_${Math.floor(Math.random()*90000+10000).toString(36)}`;
  }

  // seen store
  const seenIds = new Set();
  const seenLocal = new Set();
  const seenHashes = new Set();
  const localMap = new Map(); // local_id -> node (optimistic)

  // container search
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
      } catch (e) {}
    }
    return null;
  }

  // build index of existing DOM replies (used to mark seen)
  function buildDOMIndex() {
    const idx = [];
    for (const sel of CONTAINER_SELECTORS) {
      try {
        const container = document.querySelector(sel);
        if (!container) continue;
        const nodes = Array.from(container.children || []);
        for (const n of nodes) {
          if (!container.contains(n)) continue;
          const replyId = n.dataset && (n.dataset.replyId || n.dataset.reply_id) ? (n.dataset.replyId || n.dataset.reply_id) : null;
          const localId = n.dataset && (n.dataset.localId || n.dataset.local_id || n.dataset.localid) ? (n.dataset.localId || n.dataset.local_id || n.dataset.localid) : null;
          const bubble = (n.querySelector && (n.querySelector('.bubble') || n.querySelector('.message') || n.querySelector('.text') || n.querySelector('.chat-message')));
          const rawText = bubble ? (bubble.textContent || '') : (n.textContent || '');
          const tsEl = (n.querySelector && (n.querySelector('.ts') || n.querySelector('time') || n.querySelector('.time') || n.querySelector('.meta')));
          const rawTs = tsEl ? (tsEl.textContent || tsEl.innerText || '') : '';
          const normalized = normText(rawText);
          if (!normalized) continue;
          idx.push({ sel, node: n, text: normalized, ts: rawTs ? rawTs.toString().trim() : '', localId: localId || null, replyId: replyId || null });
        }
      } catch (e) {}
    }
    return idx;
  }

  // create node that matches server markup (class=reply, .meta, .text)
  function createMessageNode(msg, { localId } = {}) {
    const el = document.createElement('div');
    const isAdmin = Number(msg.is_admin) === 1;
    el.className = isAdmin ? 'reply admin' : 'reply user';
    if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
    if (localId) el.setAttribute('data-local-id', String(localId));
    const who = isAdmin ? (msg.admin_identifier || 'Admin') : (msg.user_name || 'You');
    const txt = msg.reply_text || msg.message || msg.text || '';
    const ts = msg.replied_at || msg.created_at || new Date().toISOString();
    el.innerHTML = `<div class="meta">${escapeHtml(who)} | ${escapeHtml(String(ts))}</div><div class="text chat-message" data-text="${escapeHtml(String(txt).slice(0,200))}" data-ts="${escapeHtml(String(ts))}">${escapeHtml(txt)}</div>`;
    return el;
  }

  function markSeenFromDOMIndex() {
    const index = buildDOMIndex();
    for (const item of index) {
      const fake = { reply_text: item.text, replied_at: item.ts };
      if (item.replyId) fake.reply_id = item.replyId;
      if (item.localId) fake.local_id = item.localId;
      // mark by id/local/hash
      if (fake.reply_id) seenIds.add(String(fake.reply_id));
      if (fake.local_id) seenLocal.add(String(fake.local_id));
      if (item.text) seenHashes.add(item.text);
    }
    return index.length;
  }

  function isSeen(msg) {
    const id = msg.id || msg.reply_id || msg.reply_db_id || null;
    const local = msg.local_id || msg.localId || msg.localid || null;
    if (id && seenIds.has(String(id))) return true;
    if (local && seenLocal.has(String(local))) return true;
    const h = normText(msg.reply_text || msg.message || msg.text || '');
    if (h && seenHashes.has(h)) return true;
    return false;
  }

  // incoming message fuzzy match against existing DOM (local id, reply-id, or fuzzy text+ts)
  function incomingMatchesExisting(msg) {
    // local id match
    const local = msg.local_id || msg.localId || msg.localid;
    if (local) {
      try {
        const el = document.querySelector(`[data-local-id="${CSS.escape(String(local))}"]`);
        if (el) {
          if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
          seenLocal.add(String(local));
          if (msg.id || msg.reply_id) seenIds.add(String(msg.id || msg.reply_id));
          return true;
        }
      } catch (e) {
        const el = document.querySelector(`[data-local-id="${String(local)}"]`);
        if (el) { if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id)); seenLocal.add(String(local)); return true; }
      }
    }

    // db id match
    const id = msg.id || msg.reply_id || msg.reply_db_id;
    if (id) {
      try {
        const el = document.querySelector(`[data-reply-id="${CSS.escape(String(id))}"]`);
        if (el) { seenIds.add(String(id)); return true; }
      } catch (e) {
        const el = document.querySelector(`[data-reply-id="${String(id)}"]`);
        if (el) { seenIds.add(String(id)); return true; }
      }
    }

    // fuzzy text+ts matching against DOM index
    const index = buildDOMIndex();
    const replyNorm = normText(msg.reply_text || msg.message || '');
    const replyTs = (msg.replied_at || msg.created_at || '').toString().trim();
    if (!replyNorm) return false;

    for (const d of index) {
      const shortPartial = replyNorm.split(' ').slice(0,4).join(' ');
      if (d.text.includes(replyNorm) || replyNorm.includes(d.text) || (shortPartial && d.text.includes(shortPartial))) {
        if (replyTs && d.ts) {
          const t1 = Date.parse(replyTs), t2 = Date.parse(d.ts);
          if (!isNaN(t1) && !isNaN(t2) && Math.abs(t1 - t2) <= 15000) { 
            // match
            if (msg.id) seenIds.add(String(msg.id));
            if (msg.local_id) seenLocal.add(String(msg.local_id));
            seenHashes.add(replyNorm);
            return true;
          }
        } else {
          if (replyNorm.length > 4) { 
            if (msg.id) seenIds.add(String(msg.id));
            if (msg.local_id) seenLocal.add(String(msg.local_id));
            seenHashes.add(replyNorm);
            return true;
          }
        }
      }
    }
    return false;
  }

  // append (and mark seen) - uses server-like markup
  function appendToChat(msg, opts = {}) {
    try {
      if (isSeen(msg)) return false;
      const container = findContainerOnce();
      if (!container) {
        warn('chat-socket: messages container not found (tried selectors)');
        return false;
      }
      const node = createMessageNode(msg, { localId: opts.localId || null });
      container.appendChild(node);
      container.scrollTop = container.scrollHeight;

      // mark sees
      const id = msg.id || msg.reply_id || msg.reply_db_id;
      const local = opts.localId || msg.local_id || msg.localId || msg.localid;
      const h = normText(msg.reply_text || msg.message || msg.text || '');
      if (id) seenIds.add(String(id));
      if (local) { seenLocal.add(String(local)); if (opts.localId) localMap.set(String(local), node); }
      if (h) seenHashes.add(h);
      msg._appendedNode = node;
      return true;
    } catch (e) { err('appendToChat error', e); return false; }
  }

  // lookup numeric id for join
  async function lookupContactQueryId() {
    try {
      const url = `/config/lookup_ticket_id.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}&json=1`;
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
      // wait for container briefly
      const container = findContainerOnce();
      if (!container) {
        // don't block; allow page to load HTML. markSeenFromDOMIndex will still find nodes if present later.
        log('chat-socket: messages container not immediately available (continuing).');
      }

      const markedCount = markSeenFromDOMIndex();
      if (markedCount) log('chat-socket: marked existing DOM messages as seen:', markedCount);

      // resolve numeric contact_query_id if available
      const resolved = await lookupContactQueryId();
      if (resolved) {
        contactQueryId = resolved;
        log('Resolved contact_query_id:', contactQueryId);
      }

      const ioOpts = { transports: ['websocket','polling'], reconnection: true, reconnectionAttempts: Infinity, reconnectionDelay: 2000, withCredentials: true };
      if (!SOCKET_SERVER) ioOpts.path = ioOpts.path || '/socket.io';
      const socket = SOCKET_SERVER ? io(SOCKET_SERVER, ioOpts) : io(ioOpts);

      socket.on('connect', () => {
        log('CONNECTED -> socket.id:', socket.id);
        const joinPayload = {
          contact_query_id: contactQueryId || null,
          ticket_public_id: PUBLIC_TICKET,
          user_type: 'user',
          user_id: CURRENT_USER_ID || null
        };
        try {
          socket.emit('join_ticket', joinPayload);
          log('Emitted join_ticket for:', joinPayload);
        } catch (e) { warn('join_ticket emit failed', e); }
      });

      socket.on('reconnect', (n) => {
        log('RECONNECTED -> re-emitting join_ticket', n);
        try {
          socket.emit('join_ticket', {
            contact_query_id: contactQueryId || null,
            ticket_public_id: PUBLIC_TICKET,
            user_type: 'user',
            user_id: CURRENT_USER_ID || null
          });
        } catch (e) { warn('rejoin failed', e); }
      });

      socket.on('reconnect_attempt', (n) => log('reconnect attempt:', n));
      socket.on('connect_error', (e) => warn('connect_error:', e && e.message ? e.message : e));
      socket.on('disconnect', (reason) => warn('socket disconnected:', reason));

      // initial server-side fetch of conversation (append only unseen)
      try {
        const t = await fetch(`/config/get_conversation.php?ticket_id=${encodeURIComponent(PUBLIC_TICKET)}&json=1`, { credentials: 'include', headers: { Accept: 'application/json' } });
        if (!t.ok) throw new Error('Load conversation HTTP ' + t.status);
        const json = await t.json().catch(()=>null);
        if (!json) { warn('get_conversation returned no JSON'); }
        else {
          const replies = Array.isArray(json.replies) ? json.replies : (Array.isArray(json) ? json : []);
          let appended = 0, skipped = 0;
          const currentIndex = buildDOMIndex();
          for (const r of replies) {
            if (isSeen(r)) { skipped++; continue; }
            // check DOM id presence
            let exists = false;
            const rid = r.id || r.reply_id;
            if (rid) {
              try {
                const el = document.querySelector(`[data-reply-id="${CSS.escape(String(rid))}"]`);
                if (el) exists = true;
              } catch (e) {
                const el = document.querySelector(`[data-reply-id="${String(rid)}"]`);
                if (el) exists = true;
              }
            }
            if (!exists) {
              // fuzzy compare with currentIndex
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
            if (exists) { seenIds.add(String(r.id || r.reply_id || '')); skipped++; continue; }
            if (appendToChat(r)) appended++; else { skipped++; if (r.id) seenIds.add(String(r.id)); }
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
          // If this message corresponds to an optimistic local node, reconcile (prefer local id match)
          const lid = msg.local_id || msg.localId || msg.localid || null;
          if (lid && localMap.has(String(lid))) {
            const el = localMap.get(String(lid));
            // attach reply id if present
            if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
            // update text + meta if needed
            const tEl = el.querySelector && el.querySelector('.text');
            const mEl = el.querySelector && el.querySelector('.meta');
            if (tEl && (msg.reply_text || msg.message)) tEl.textContent = msg.reply_text || msg.message;
            if (mEl) mEl.textContent = (msg.user_name || 'You') + ' | ' + (msg.replied_at || msg.created_at || '');
            // mark seen and cleanup
            if (msg.id) seenIds.add(String(msg.id));
            seenLocal.add(String(lid));
            localMap.delete(String(lid));
            if (msg.reply_text) seenHashes.add(normText(msg.reply_text));
            return;
          }

          // if already matches an existing DOM element, skip
          if (incomingMatchesExisting(msg)) {
            log('chat-socket: skipped server-echo new_message (matched DOM/local/id)');
            return;
          }
          // otherwise append
          appendToChat(msg);
        } catch (e) { err('new_message handler error', e); }
      });

      socket.on('error_message', (e) => warn('socket error_message:', e));

      // expose
      window.__chatSocket = socket;
      window.__chatSeen = { seenIds, seenLocal, seenHashes, localMap };
    } catch (e) {
      err('init error', e);
    }
  }

  // send function used by UI
  window.sendChatMessage = function (text) {
    if (!text || typeof text !== 'string') return;
    const localId = genLocalId();
    const payload = {
      contact_query_id: contactQueryId || null,
      ticket_public_id: PUBLIC_TICKET,
      is_admin: 0,
      admin_identifier: null,
      user_id: CURRENT_USER_ID || null,
      reply_text: text,
      local_id: localId
    };

    // optimistic render with localId
    const appended = appendToChat(Object.assign({}, payload, { replied_at: new Date().toISOString() }), { localId });
    if (appended) localMap.set(String(localId), (function(){ return document.querySelector(`[data-local-id="${localId}"]`); })());

    try {
      window.__chatSocket && window.__chatSocket.emit && window.__chatSocket.emit('send_message', payload);
    } catch (e) {
      warn('socket.emit(send_message) failed:', e);
    }

    // If the UI also posts to backend, include local_id and origin_socket_id as appropriate.
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
