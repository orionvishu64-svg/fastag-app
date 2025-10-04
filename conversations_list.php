<?php
// conversations_list.php

require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

// fetch open and closed conversations
try {
    // open tickets first
    $stmtOpen = $pdo->prepare("SELECT id, ticket_id, name, email, subject, submitted_at, status, viewed, priority 
                               FROM contact_queries
                               ORDER BY FIELD(status,'open','closed'), submitted_at DESC");
    $stmtOpen->execute();
    $tickets = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<h3>DB Error</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Conversations list</title>
  <style>
    table { width:100%; border-collapse: collapse; }
    th,td { padding:8px; border:1px solid #ddd; text-align:left; }
    .open { background:#e8fff0; }
    .closed { background:#fff7e8; color:#777; }
    .btn { padding:6px 10px; border-radius:4px; text-decoration:none; display:inline-block; }
    .btn-open { background:#28a745; color:#fff; }
    .btn-view { background:#007bff; color:#fff; }
  </style>
</head>
<body>
  <h1>All Conversations</h1>
  <p><a href="contact.php" style="color: #1e75daff; hover: #100750ff;" class="btn btn-secondary">Back to Contact</a></p>

  <table>
    <thead>
      <tr>
        <th>Ticket</th>
        <th>Name</th>
        <th>Subject</th>
        <th>Submitted</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="conversations-body">
    <?php foreach ($tickets as $t): ?>
      <?php $cls = ($t['status'] === 'open') ? 'open' : 'closed'; ?>
      <tr class="<?= $cls ?>" data-contact-query-id="<?= htmlspecialchars($t['id']) ?>">
        <td><?= htmlspecialchars($t['ticket_id']) ?></td>
        <td><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['email']) ?>)</td>
        <td><?= htmlspecialchars($t['subject']) ?></td>
        <td><?= htmlspecialchars($t['submitted_at']) ?></td>
        <td><?= htmlspecialchars($t['priority']) ?></td>
        <td><?= htmlspecialchars($t['status']) ?></td>
        <td>
          <?php if ($t['status'] === 'open'): ?>
            <a class="btn btn-open" href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>">Open</a>
          <?php else: ?>
            <a class="btn btn-view" href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>">View</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    // optional: simple auto-refresh every 45s (uncomment if you want)
    // setInterval(()=> location.reload(), 45000);
  </script>
</body>
</html>
