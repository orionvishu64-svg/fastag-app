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
<?php include __DIR__ . '/includes/header.php'; ?>
<style>
#messages_container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.message {
  max-width: 72%;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.45;
  box-shadow: 0 4px 14px rgba(0,0,0,.08);
  word-wrap: break-word;
}

.message.user {
  align-self: flex-end;
  background: #0d6efd;
  color: #fff;
  border-bottom-right-radius: 6px;
}

.message.admin {
  align-self: flex-start;
  background: #f1f3f5;
  color: #212529;
  border-bottom-left-radius: 6px;
}

.message-header {
  font-size: 11px;
  opacity: .75;
  margin-bottom: 4px;
}

.message-header .time {
  margin-left: 6px;
  font-weight: 400;
}
</style>
<div class="container py-4">
  <!-- PAGE HEADER -->
  <div class="card shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <a href="conversations_list.php" class="btn btn-outline-primary btn-sm">
          ⬅ Back
        </a>
        <h5 class="mb-0">
          🗨️ Conversation — <?= htmlspecialchars($ticket_id_public) ?>
        </h5>
      </div>
      <small class="text-muted">
        Submitted: <?= $ticket_submitted ?> •
        Status:
        <span class="badge <?= $ticket_status === 'open' ? 'bg-success' : 'bg-secondary' ?>">
          <?= htmlspecialchars($ticket_status) ?>
        </span>
      </small>
    </div>
  </div>

  <!-- OPEN TICKET -->
  <section id="openTicketContainer" class="<?= $ticket_status === 'open' ? '' : 'd-none' ?>">
    <div class="card shadow-sm mb-4"
         data-ticket-id="<?= $ticket_id_public ?>"
         data-contact-query-id="<?= $contact_query_id ?>"
         data-status="<?= $ticket_status ?>">

      <div class="card-header bg-light">
        <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($ticket_subject) ?></h6>
        <small class="text-muted">
          From: <?= htmlspecialchars($ticket_name) ?> —
          Ticket: <?= htmlspecialchars($ticket_id_public) ?>
        </small>
      </div>

      <div class="card-body">

        <!-- MESSAGES -->
        <div id="messages_container"
             class="border rounded p-3 mb-3"
             style="height:45vh; overflow-y:auto;">
        </div>

        <!-- REPLY -->
        <form id="replyForm" class="input-group">
          <textarea id="replyMessage"
            class="form-control"
            placeholder="Type your message..."
            rows="2"
            required></textarea>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </form>
      </div>
    </div>
  </section>

  <!-- CLOSED TICKET -->
  <section id="closedTicketsContainer" class="<?= $ticket_status === 'closed' ? '' : 'd-none' ?>">
    <div class="card shadow-sm border-secondary"
         data-ticket-id="<?= $ticket_id_public ?>"
         data-contact-query-id="<?= $contact_query_id ?>"
         data-status="<?= $ticket_status ?>">

      <div class="card-header bg-secondary text-white">
        <h6 class="mb-1 fw-semibold">
          <?= htmlspecialchars($ticket_subject) ?> (Closed)
        </h6>
        <small>
          From: <?= htmlspecialchars($ticket_name) ?> —
          Ticket: <?= htmlspecialchars($ticket_id_public) ?>
        </small>
      </div>

      <div class="card-body">

        <!-- MESSAGES -->
        <div id="messages_container_closed"
             class="border rounded p-3 mb-3 bg-light"
             style="height:40vh; overflow-y:auto;">
        </div>

        <!-- DISABLED REPLY -->
        <div class="input-group">
          <textarea id="reply_text_closed"
                    class="form-control"
                    rows="2"
                    disabled>
            This ticket is closed. Replies may not be accepted.
          </textarea>
          <button id="send_reply_closed" class="btn btn-secondary" disabled>
            Send
          </button>
        </div>

      </div>
    </div>
  </section>
</div>
  <script>
    window.TICKET_PUBLIC_ID = <?= json_encode($ticket_id_public, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    window.CONTACT_QUERY_ID = <?= json_encode($contact_query_id) ?>;
    window.TICKET_STATUS = <?= json_encode($ticket_status) ?>;
    window.CURRENT_USER_ID = <?= json_encode($current_user_id) ?>;
  </script>
  <script src="/socket.io/socket.io.js"></script>
  <script src="/public/js/conversation.js"></script>