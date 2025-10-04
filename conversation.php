<?php
// conversation.php
// Shows a single ticket (open or closed) based on ?ticket_id=TCK-...
// Requires: common_start.php that provides session and $pdo (PDO instance).

require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

$public_ticket_id = trim((string)($_GET['ticket_id'] ?? ''));

if ($public_ticket_id === '') {
    http_response_code(400);
    echo "Ticket not specified. Please go back and try again.";
    exit;
}

try {
    // find the contact_query by ticket_id
    $stmt = $pdo->prepare("SELECT * FROM contact_queries WHERE ticket_id = ? LIMIT 1");
    $stmt->execute([$public_ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo "Ticket not found. It may have been removed or the ticket id is invalid.";
        exit;
    }

    // sanitize fields we'll echo into HTML
    $ticket_id_public = htmlspecialchars($ticket['ticket_id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $contact_query_id = (int) ($ticket['id'] ?? 0);
    $ticket_subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ticket_name = htmlspecialchars($ticket['name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ticket_status = $ticket['status'] ?? 'open'; // 'open' or 'closed'
    $ticket_submitted = htmlspecialchars($ticket['submitted_at'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // current user id (0 if not logged in)
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
  <link rel="stylesheet" href="conversation.css">
</head>
<body>
  <header>
    <div class="header-left">
      <a href="contact.php" style="color: #021327ff;" class="btn btn-primary">⬅ Back</a>
      <h2>🗨️ Conversation — <?= $ticket_id_public ?></h2>
    </div>
    <div class="header-right">
      <small>Submitted: <?= $ticket_submitted ?> • Status: <?= htmlspecialchars($ticket_status) ?></small>
    </div>
  </header>

  <main id="conversationArea">
    <!-- We show the single ticket in the appropriate container -->
    <section id="openTicketContainer" style="<?= $ticket_status === 'open' ? '' : 'display:none;' ?>">
      <div class="ticket-card" data-ticket-id="<?= $ticket_id_public ?>" data-contact-query-id="<?= $contact_query_id ?>" data-status="<?= $ticket_status ?>">
        <h3 class="ticket-subject"><?= $ticket_subject ?></h3>
        <div class="ticket-meta">From: <?= $ticket_name ?> — Ticket: <?= $ticket_id_public ?></div>
        <!-- conversation messages area (populated by conversation.js / get_conversation.php) -->
        <div class="messages" id="messages_container"></div>

        <!-- basic send form (conversation.js may have its own) -->
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
        <!-- You can optionally hide reply UI for closed tickets; keep it visible only if you want replies -->
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

  <!-- socket.io and your existing scripts -->
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script src="script.js"></script>
  <script src="conversation.js"></script>
  <script src="/js/chat-socket.js"></script>
</body>
</html>
