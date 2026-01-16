<?php
// conversations_list.php
require_once __DIR__ . '/config/common_start.php';
require 'config/db.php';

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

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container py-5 bg-light">

  <!-- HEADER CARD -->
  <div class="card shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <h1 class="h4 fw-bold mb-0 text-primary">My Conversations</h1>
      <a href="contact.php" class="btn btn-outline-secondary">
        ← Back to Contact
      </a>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="card shadow-sm">
    <div class="card-body p-0">

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Ticket</th>
              <th>Subject</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>

          <?php if (empty($tickets)): ?>
            <tr>
              <td colspan="3" class="text-center text-muted py-4">
                No conversations found.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($tickets as $t): ?>
            <tr>
              <td>
                <div class="fw-semibold">
                  <?= htmlspecialchars($t['ticket_id'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <small class="text-muted">
                  <?= htmlspecialchars($t['submitted_at'], ENT_QUOTES, 'UTF-8') ?>
                </small>
              </td>

              <td>
                <?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?>
                <div>
                  <?php if ($t['status'] === 'open'): ?>
                    <span class="badge bg-success">Open</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Closed</span>
                  <?php endif; ?>
                </div>
              </td>

              <td class="text-end">
                <?php if ($t['status'] === 'open'): ?>
                  <a href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>"
                     class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-comment-dots me-1"></i>
                    Open
                  </a>
                <?php else: ?>
                  <a href="conversation.php?ticket_id=<?= urlencode($t['ticket_id']) ?>"
                     class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-eye me-1"></i>
                    View
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>