// chat-socket.js
(function () {
  'use strict';

  // ----- Config (override via global vars if needed) -----
  const SOCKET_SERVER = (typeof window.SOCKET_SERVER_URL !== 'undefined' && window.SOCKET_SERVER_URL)
    ? String(window.SOCKET_SERVER_URL).replace(/\/$/, '')
    : '';
  const PUBLIC_TICKET = (window.TICKET_PUBLIC_ID || '').toString().trim();
  const CURRENT_USER_ID = (typeof window.CURRENT_USER_ID !== 'undefined') ? window.CURRENT_USER_ID : null;

  if (!PUBLIC_TICKET) {
    console.error('chat-socket: Missing PUBLIC_TICKET (window.TICKET_PUBLIC_ID)');
    return;
  }

  // ----- Small logging helpers -----
  function log(...a) { console.log('chat-socket:', ...a); }
  function warn(...a) { console.warn('chat-socket:', ...a); }
  function err(...a) { console.error('chat-socket:', ...a); }

  // ----- Utilities -----
  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }
  function normText(s) {
    if (!s) return '';
    return s.toString().replace(/^\s+|\s+$/g, '').replace(/\s+/g, ' ').slice(0, 500).toLowerCase();
  }
  function genLocalId() {
    if (window.crypto && window.crypto.randomUUID) return 'local_' + crypto.randomUUID();
    return `local_${Date.now().toString(36)}_${Math.floor(Math.random()*90000+10000).toString(36)}`;
  }

  // ----- DOM container candidates (tries many selectors used across your project) -----
  const CONTAINER_SELECTORS = [
    '#openThread',
    '#chat-messages',
    '#messages_container',
    '#chat-messages_container',
    '#messages_container_closed',
    '#chat-messages-closed',
    '#admin-chat-messages',
    '#openTicketContainer',
    '.messages',
    '.chat-messages-container',
    '.conversation-messages'
  ];

  function findContainer() {
    for (const sel of CONTAINER_SELECTORS) {
      try {
        const el = document.querySelector(sel);
        if (el) return el;
      } catch (e) {}
    }
    return null;
  }

  // ----- Seen sets + optimistic map -----
  const seenIds = new Set();
  const seenLocal = new Set();
  const seenHashes = new Set();
  const localMap = new Map();

  // ----- Build a lightweight index of current DOM replies for fuzzy matching -----
  function buildDOMIndex() {
    const container = findContainer();
    if (!container) return [];
    const nodes = Array.from(container.children || []);
    const idx = [];
    for (const n of nodes) {
      try {
        const rid = n.getAttribute && (n.getAttribute('data-reply-id') || n.dataset && (n.dataset.replyId || n.dataset.reply_id)) || null;
        const lid = n.getAttribute && (n.getAttribute('data-local-id') || n.dataset && (n.dataset.localId || n.dataset.local_id)) || null;
        const textEl = n.querySelector && (n.querySelector('.text') || n.querySelector('.bubble-text') || n.querySelector('.message') || n);
        const rawText = textEl ? (textEl.textContent || '') : (n.textContent || '');
        const tsEl = n.querySelector && (n.querySelector('.meta') || n.querySelector('.ts') || n.querySelector('time'));
        const rawTs = tsEl ? (tsEl.textContent || '').trim() : '';
        const txtNorm = normText(rawText);
        if (!txtNorm) continue;
        idx.push({ node: n, replyId: rid, localId: lid, text: txtNorm, ts: rawTs });
      } catch (e) { /* ignore node read errors */ }
    }
    return idx;
  }

  // ----- Fuzzy incoming match: local_id, reply_id, or text+ts -----
  function incomingMatchesExisting(msg) {
    const local = msg.local_id || msg.localId || msg.localid || null;
    if (local) {
      try {
        const sel = `[data-local-id="${CSS && CSS.escape ? CSS.escape(String(local)) : String(local)}"]`;
        const el = document.querySelector(sel);
        if (el) {
          if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
          seenLocal.add(String(local));
          if (msg.id) seenIds.add(String(msg.id));
          return true;
        }
      } catch (e) {
        const el = document.querySelector(`[data-local-id="${String(local)}"]`);
        if (el) { if (msg.id) el.setAttribute('data-reply-id', String(msg.id)); seenLocal.add(String(local)); if (msg.id) seenIds.add(String(msg.id)); return true; }
      }
    }

    const id = msg.id || msg.reply_id || msg.reply_db_id || null;
    if (id) {
      try {
        const sel = `[data-reply-id="${CSS && CSS.escape ? CSS.escape(String(id)) : String(id)}"]`;
        const el = document.querySelector(sel);
        if (el) { seenIds.add(String(id)); return true; }
      } catch (e) {
        const el = document.querySelector(`[data-reply-id="${String(id)}"]`);
        if (el) { seenIds.add(String(id)); return true; }
      }
    }

    const idx = buildDOMIndex();
    const replyNorm = normText(msg.reply_text || msg.message || msg.text || '');
    if (!replyNorm) return false;
    const replyTs = (msg.replied_at || msg.created_at || '').toString().trim();

    for (const d of idx) {
      const shortPartial = replyNorm.split(' ').slice(0,4).join(' ');
      if (d.text.includes(replyNorm) || replyNorm.includes(d.text) || (shortPartial && d.text.includes(shortPartial))) {
        if (replyTs && d.ts) {
          const t1 = Date.parse(replyTs), t2 = Date.parse(d.ts);
          if (!isNaN(t1) && !isNaN(t2) && Math.abs(t1 - t2) <= 15000) {
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

  // ----- Fallback DOM append if conversation renderer not present -----
  function createFallbackNode(msg, { localId } = {}) {
    const el = document.createElement('div');
    const isAdmin = Number(msg.is_admin) === 1;
    el.className = isAdmin ? 'reply admin' : 'reply user';
    if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
    if (localId) el.setAttribute('data-local-id', String(localId));
    const who = isAdmin ? (msg.admin_identifier || 'Admin') : (msg.user_name || 'You');
    const txt = msg.reply_text || msg.message || msg.text || '';
    const ts = msg.replied_at || msg.created_at || new Date().toISOString();
    el.innerHTML = `<div class="meta">${escapeHtml(who)} | ${escapeHtml(String(ts))}</div>
                    <div class="text chat-message" data-text="${escapeHtml(String(txt).slice(0,200))}" data-ts="${escapeHtml(String(ts))}">
                      ${escapeHtml(txt)}
                    </div>`;
    return el;
  }

  function fallbackAppend(msg, { localId } = {}) {
    try {
      const container = findContainer();
      if (!container) {
        warn('chat-socket: container not found, cannot append message');
        return false;
      }
      const id = msg.id || msg.reply_id;
      if (id && seenIds.has(String(id))) return false;
      const local = localId || msg.local_id;
      if (local && seenLocal.has(String(local))) return false;
      const node = createFallbackNode(msg, { localId });
      container.appendChild(node);
      container.scrollTop = container.scrollHeight;

      if (id) seenIds.add(String(id));
      if (local) { seenLocal.add(String(local)); localMap.set(String(local), node); }
      const h = normText(msg.reply_text || msg.message || msg.text || '');
      if (h) seenHashes.add(h);
      return true;
    } catch (e) { err('fallbackAppend error', e); return false; }
  }

  // ----- High-level append: prefer conversation renderer if present -----
  function appendMessage(msg, opts = {}) {
    if (window.CONVERSATION && typeof window.CONVERSATION.renderMessage === 'function') {
      try {
        const normalized = {
          id: msg.id ?? msg.inserted_id ?? msg.reply_id ?? null,
          local_id: msg.local_id ?? msg.localId ?? msg.client_msg_id ?? null,
          reply_text: msg.reply_text ?? msg.message ?? msg.content ?? '',
          is_admin: (typeof msg.is_admin !== 'undefined') ? msg.is_admin : (msg.user_type === 'admin' ? 1 : 0),
          created_at: msg.created_at ?? msg.replied_at ?? new Date().toISOString(),
          admin_identifier: msg.admin_identifier,
          user_id: msg.user_id
        };
        const node = window.CONVERSATION.renderMessage(normalized, { containerId: 'openThread', prepend: false });
        const id = normalized.id;
        const local = normalized.local_id;
        if (id) seenIds.add(String(id));
        if (local) { seenLocal.add(String(local)); if (node) localMap.set(String(local), node); }
        const h = normText(normalized.reply_text || '');
        if (h) seenHashes.add(h);
        return !!node;
      } catch (e) {
        warn('chat-socket: CONVERSATION.renderMessage failed, falling back', e);
        return fallbackAppend(msg, opts);
      }
    } else {
      return fallbackAppend(msg, opts);
    }
  }

  // ----- Socket init & handlers -----
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

  async function initSocket() {
    try {
      (function markSeenFromDOM() {
        const idx = buildDOMIndex();
        for (const it of idx) {
          if (it.replyId) seenIds.add(String(it.replyId));
          if (it.localId) seenLocal.add(String(it.localId));
          if (it.text) seenHashes.add(it.text);
        }
        if (idx.length) log('chat-socket: marked existing DOM messages:', idx.length);
      })();

      const contactQueryId = await lookupContactQueryId();

      // explicit path to match Apache proxy
      const ioOpts = { transports: ['websocket','polling'], reconnection: true, reconnectionAttempts: Infinity, reconnectionDelay: 2000, withCredentials: true, path: '/socket.io/' };

      // ensure single socket and don't re-init if already exists
      if (window.__chatSocket && window.__chatSocket.io) {
        log('chat-socket: socket appears already initialized');
        return window.__chatSocket;
      }

      // always create client with explicit SOCKET_SERVER (default is IP)
      const socket = io(SOCKET_SERVER, ioOpts);

      socket.on('connect', () => {
        log('CONNECTED -> socket.id:', socket.id);
        const joinPayload = {
          contact_query_id: contactQueryId || null,
          ticket_public_id: PUBLIC_TICKET,
          user_type: 'user',
          user_id: CURRENT_USER_ID || null
        };
        try { socket.emit('join_ticket', joinPayload); log('Emitted join_ticket', joinPayload); } catch (e) { warn('join_ticket emit failed', e); }
      });

      socket.on('reconnect', (n) => {
        log('RECONNECTED -> re-emitting join_ticket', n);
        try {
          socket.emit('join_ticket', { contact_query_id: contactQueryId || null, ticket_public_id: PUBLIC_TICKET, user_type: 'user', user_id: CURRENT_USER_ID || null });
        } catch (e) { warn('rejoin failed', e); }
      });
      socket.on('connect_error', (e) => warn('connect_error', e && e.message ? e.message : e));
      socket.on('disconnect', (reason) => warn('socket disconnected:', reason));

      socket.off('new_message').on('new_message', (msg) => {
        try {
          const local = msg.local_id || msg.localId || msg.localid || null;
          if (local && localMap.has(String(local))) {
            const el = localMap.get(String(local));
            if (msg.id || msg.reply_id) el.setAttribute('data-reply-id', String(msg.id || msg.reply_id));
            const tEl = el.querySelector && (el.querySelector('.text') || el.querySelector('.bubble-text') || el.querySelector('.message'));
            const mEl = el.querySelector && (el.querySelector('.meta') || el.querySelector('.ts'));
            if (tEl && (msg.reply_text || msg.message)) tEl.textContent = msg.reply_text || msg.message;
            if (mEl) mEl.textContent = (msg.user_name || 'You') + ' | ' + (msg.replied_at || msg.created_at || '');
            if (msg.id) seenIds.add(String(msg.id));
            seenLocal.add(String(local));
            if (msg.reply_text) seenHashes.add(normText(msg.reply_text));
            localMap.delete(String(local));
            return;
          }

          if (incomingMatchesExisting(msg)) {
            log('chat-socket: skipped server message (already present)');
            return;
          }

          appendMessage(msg);
        } catch (e) {
          err('new_message handler error', e);
        }
      });

      socket.off('error_message').on('error_message', (e) => warn('socket error_message:', e));

      // expose socket and small API
      window.__chatSocket = socket;
      window.__chatSeen = { seenIds, seenLocal, seenHashes, localMap };
      return socket;
    } catch (e) {
      err('initSocket error', e);
      return null;
    }
  }

  // ----- Public send function (optimistic render + socket emit) -----
  window.sendChatMessage = function (text) {
    try {
      if (!text || typeof text !== 'string' || !text.trim()) return false;
      const localId = genLocalId();
      const payload = {
        contact_query_id: null,
        ticket_public_id: PUBLIC_TICKET,
        is_admin: 0,
        admin_identifier: null,
        user_id: CURRENT_USER_ID || null,
        reply_text: text,
        local_id: localId,
        created_at: new Date().toISOString()
      };

      const appended = appendMessage(payload, { localId });
      if (appended) {
        const node = document.querySelector(`[data-local-id="${localId}"]`);
        if (node) localMap.set(String(localId), node);
      }

      const s = window.__chatSocket || null;
      try {
        if (s && s.connected) {
          s.emit('send_message', payload);
        } else {
          initSocket().then((socket) => {
            if (socket && socket.emit) socket.emit('send_message', payload);
          }).catch(e => warn('Lazy socket init/emit failed', e));
        }
      } catch (e) {
        warn('socket.emit(send_message) failed:', e);
      }

      return true;
    } catch (e) {
      err('sendChatMessage error', e);
      return false;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { setTimeout(initSocket, 50); });
  } else {
    setTimeout(initSocket, 50);
  }

})();