<?php
// conversations_list.php
require_once __DIR__ . '/config/common_start.php';
require 'config/db.php';

// Ensure user logged-in (your system uses $_SESSION['user']['id'])
if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit;
}
$user_id = (int) $_SESSION['user']['id'];

header('Content-Type: text/html; charset=utf-8');
try {
    $stmt = $pdo->prepare(
        "SELECT id, user_id, ticket_id, subject, status, viewed, submitted_at
         FROM contact_queries
         WHERE user_id = :user_id
         ORDER BY FIELD(status,'open','closed'), submitted_at DESC"
    );
    $stmt->execute([':user_id' => $user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<h3>DB Error</h3><pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Conversations</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="stylesheet" href="/public/css/conversation_list.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
<div class="convo-page">
  <div class="info-card">
    <h1>My Conversations</h1>
    <a href="contact.php">Back to Contact</a>
  </div>

  <div class="main-card" aria-live="polite">
    <table>
      <thead>
        <tr>
          <th>User Id</th>
          <th>Ticket</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="conversations-body">
      <?php foreach ($tickets as $t): ?>
        <?php $cls = ($t['status'] === 'open') ? 'open' : 'closed'; ?>
        <tr class="<?= $cls ?>" data-contact-query-id="<?= htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8') ?>">
          <td data-label="User ID"><?= htmlspecialchars($t['user_id'], ENT_QUOTES, 'UTF-8') ?></td>
          <td data-label="Ticket"><?= htmlspecialchars($t['ticket_id'], ENT_QUOTES, 'UTF-8') ?></td>
          <td data-label="Subject"><?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?></td>
          <td data-label="Status"><?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td data-label="Actions">
            <div class="actions-wrap">
            <?php if ($t['status'] === 'open'): ?>
              <a class="btn btn-open" href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>" style="white-space:nowrap;">Open</a>
            <?php else: ?>
              <a class="btn btn-view" href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>" style="white-space:nowrap;">View</a>
            <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="/public/js/script.js"></script>
</body>
</html>