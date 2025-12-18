<?php
// conversation.php

require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

$public_ticket_id = trim((string)($_GET['ticket_id'] ?? ''));

if ($public_ticket_id === '') {
    http_response_code(400);
    echo "Ticket not specified. Please go back and try again.";
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT * FROM contact_queries WHERE ticket_id = ? LIMIT 1");
    $stmt->execute([$public_ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo "Ticket not found. It may have been removed or the ticket id is invalid.";
        exit;
    }

    $ticket_id_public = htmlspecialchars($ticket['ticket_id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $contact_query_id = (int) ($ticket['id'] ?? 0);
    $ticket_subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ticket_name = htmlspecialchars($ticket['name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ticket_status = $ticket['status'] ?? 'open';
    $ticket_submitted = htmlspecialchars($ticket['submitted_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $current_user_id = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;

} catch (Exception $e) {
    http_response_code(500);
    error_log("conversation.php DB error: " . $e->getMessage());
    echo "An internal error occurred. Please try again later.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Conversation — <?= $ticket_id_public ?></title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/conversation.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <header>
    <div class="header-left">
      <a href="conversations_list.php" class="btn btn-primary">⬅ Back</a>
      <h2>🗨️ Conversation — <?= $ticket_id_public ?></h2>
    </div>
    <div class="header-right">
      <small>Submitted: <?= $ticket_submitted ?> • Status: <?= htmlspecialchars($ticket_status) ?></small>
    </div>
  </header>

  <main id="conversationArea">
    <section id="openTicketContainer" style="<?= $ticket_status === 'open' ? '' : 'display:none;' ?>">
      <div class="ticket-card" data-ticket-id="<?= $ticket_id_public ?>" data-contact-query-id="<?= $contact_query_id ?>" data-status="<?= $ticket_status ?>">
        <h3 class="ticket-subject"><?= $ticket_subject ?></h3>
        <div class="ticket-meta">From: <?= $ticket_name ?> — Ticket: <?= $ticket_id_public ?></div>
        <div class="messages" id="messages_container"></div>
        <div class="reply-area" id="reply_area">
          <textarea id="reply_text" placeholder="Type your message..."></textarea>
          <button id="send_reply">Send</button>
        </div>
      </div>
    </section>

    <section id="closedTicketsContainer" style="<?= $ticket_status === 'closed' ? '' : 'display:none;' ?>">
      <div class="ticket-card closed" data-ticket-id="<?= $ticket_id_public ?>" data-contact-query-id="<?= $contact_query_id ?>" data-status="<?= $ticket_status ?>">
        <h3 class="ticket-subject"><?= $ticket_subject ?> (Closed)</h3>
        <div class="ticket-meta">From: <?= $ticket_name ?> — Ticket: <?= $ticket_id_public ?></div>
        <div class="messages" id="messages_container_closed"></div>
        <div class="reply-area" id="reply_area_closed">
          <textarea id="reply_text_closed" placeholder="This ticket is closed. Replies may not be accepted."></textarea>
          <button id="send_reply_closed" disabled>Send (closed)</button>
        </div>
      </div>
    </section>
  </main>

  <!-- Expose safe JS variables -->
  <script>
    window.TICKET_PUBLIC_ID = <?= json_encode($ticket_id_public, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    window.CONTACT_QUERY_ID = <?= json_encode($contact_query_id) ?>;
    window.TICKET_STATUS = <?= json_encode($ticket_status) ?>;
    window.CURRENT_USER_ID = <?= json_encode($current_user_id) ?>;
  </script>

  <script src="/public/js/script.js"></script>
  <script src="/public/js/conversation.js"></script>
<script src="/socket.io/socket.io.js"></script>
<script>
(function(){
  const CONTACT_QUERY_ID = (typeof window.CONTACT_QUERY_ID !== 'undefined') ? Number(window.CONTACT_QUERY_ID) : null;
  const TICKET_STATUS = (typeof window.TICKET_STATUS !== 'undefined') ? String(window.TICKET_STATUS) : 'open';

  if (!CONTACT_QUERY_ID) {
    console.warn('No CONTACT_QUERY_ID on page; socket will not join a room.');
    return;
  }

  // element selectors (adjust if your markup differs)
  const openContainer   = document.getElementById('messages_container');
  const closedContainer = document.getElementById('messages_container_closed');
  const container = (TICKET_STATUS === 'closed') ? closedContainer : openContainer;
  if (!container) {
    console.warn('No messages container found; aborting socket handler.');
    return;
  }

  // small dedupe sets for this browser session
  const seenServerIds = new Set();
  const seenLocalIds  = new Set();

  // helper: safe text escape
  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  // render function - append reply bubble
  function renderReply(payload, origin) {
    try {
      if (!payload || typeof payload !== 'object') return;
      const sid = payload.id ? String(payload.id) : null;
      const lid = payload.local_id ? String(payload.local_id) : null;

      // dedupe
      if (sid && seenServerIds.has(sid)) return;
      if (lid && seenLocalIds.has(lid)) return;

      if (sid) seenServerIds.add(sid);
      if (lid) seenLocalIds.add(lid);

      // build element
      const el = document.createElement('div');
      el.className = 'reply ' + (payload.is_admin ? 'admin' : 'user');

      const meta = document.createElement('div');
      meta.className = 'meta';
      // label: if payload contains sender_name/sender_email use those, otherwise Admin/You/User
      const label = payload.sender_name || payload.sender_email || (payload.is_admin ? 'Admin' : 'User');
      const time = payload.replied_at ? escapeHtml(payload.replied_at) : new Date().toLocaleString();
      meta.innerHTML = '<strong>' + escapeHtml(label) + '</strong> <small>' + time + '</small>';

      const text = document.createElement('div');
      text.className = 'text';
      text.textContent = payload.reply_text ?? payload.message ?? '';

      el.appendChild(meta);
      el.appendChild(text);

      container.appendChild(el);
      // smooth scroll into view
      el.scrollIntoView({ behavior: 'smooth', block: 'end' });
      return el;
    } catch (err) {
      console.error('renderReply error', err);
    }
  }

  // connect socket.io
  try {
    const socket = io({
      path: '/socket.io',
      transports: ['polling','websocket']
    });

    const ROOM = 'ticket_' + CONTACT_QUERY_ID;

    socket.on('connect', () => {
      console.log('[socket] connected', socket.id, 'joining', ROOM);
      socket.emit('join', { room: ROOM });
    });

    socket.on('connect_error', (err) => {
      console.warn('[socket] connect_error', err);
    });

    socket.on('disconnect', (reason) => {
      console.log('[socket] disconnected', reason);
    });

    // debug: show raw payloads arriving
    socket.on('new_reply', (rawPayload) => {
      try {
        console.debug('[socket] new_reply raw:', rawPayload);
        let payload = rawPayload;
        if (typeof rawPayload === 'string') {
          try { payload = JSON.parse(rawPayload); } catch(e) { /* keep string */ }
        }
        // normalize keys: support both reply_text and message
        if (payload && typeof payload === 'object') {
          if (!('reply_text' in payload) && ('message' in payload)) {
            payload.reply_text = payload.message;
          }
          // ensure is_admin is number 0/1
          if ('is_admin' in payload) {
            payload.is_admin = (payload.is_admin === 1 || payload.is_admin === '1' || payload.is_admin === true) ? 1 : 0;
          } else {
            payload.is_admin = 0;
          }
        }
        renderReply(payload, 'socket');
      } catch (err) {
        console.error('[socket] new_reply handler error', err);
      }
    });

    // optional: allow testing from console
    window.__socketTestEmit = function(payload) {
      fetch('/opt/bitnami/apache/htdocs/fastag_website/config/socket_emit.php', { method: 'POST', body: JSON.stringify(payload) })
        .then(r => r.text()).then(t => console.log('emit result', t)).catch(e => console.error(e));
    };

  } catch (err) {
    console.error('Socket init failed', err);
  }
})();
</script>
</body>
</html>