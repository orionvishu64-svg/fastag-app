<?php
// conversations_list.php

require_once __DIR__ . '/config/db.php';
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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Conversations list</title>
  <style>/* Consistent theme with signup/login pages — header + full-width table */
/* ---------- Variables ---------- */
:root{
  --bg-1:#f3f8fe;
  --bg-2:#fbfdff;
  --accent:#0ea5e9;
  --accent-dark:#0284c7;
  --muted:#64748b;
  --card-bg:rgba(255,255,255,0.98);
  --radius:14px;
  --shadow:0 14px 36px rgba(2,6,23,0.06);
  --shadow-soft:0 20px 40px rgba(10,20,40,0.06);
  --font:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  --maxw:1100px;
}
/* ---------- Base Layout ---------- */
*{box-sizing:border-box}
html,body{height:100%;margin:0;padding:0;}
body{
  font-family:var(--font);
  color:#0f172a;
  background:
    radial-gradient(900px 400px at 8% 10%, rgba(14,165,233,0.05), transparent),
    linear-gradient(180deg,var(--bg-1),var(--bg-2));
  display:flex;
  justify-content:center;
  padding:28px;
}
/* Wrapper for entire page */
.convo-page{
  width:100%;
  max-width:var(--maxw);
  display:flex;
  flex-direction:column;
  gap:22px;
}
/* ---------- Top Header Card ---------- */
.info-card{
  width:100%;
  background:linear-gradient(180deg, rgba(14,165,233,0.05), rgba(2,132,199,0.02));
  border-radius:var(--radius);
  box-shadow:var(--shadow-soft);
  border:1px solid rgba(15,23,42,0.03);
  padding:20px 26px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
}
.info-card h1{
  font-size:22px;
  font-weight:800;
  color:var(--accent-dark);
  margin:0;
}
.info-card a{
  text-decoration:none;
  background:linear-gradient(180deg, rgba(255,255,255,0.95), #f6f8fb);
  padding:10px 16px;
  border-radius:10px;
  color:#0369a1;
  font-weight:700;
  border:1px solid rgba(15,23,42,0.06);
  box-shadow:0 8px 20px rgba(10,20,40,0.03);
  transition:all .12s ease;
}
.info-card a:hover{
  transform:translateY(-1px);
  filter:brightness(1.05);
}
/* ---------- Main Table Card ---------- */
.main-card{
  background:var(--card-bg);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  border:1px solid rgba(15,23,42,0.04);
  padding:20px 22px;
  width:100%;
  overflow:auto;
}
.main-card table{
  width:100%;
  border-collapse:collapse;
  min-width:700px;
  font-size:15px;
}
.main-card th{
  text-align:left;
  font-weight:700;
  color:#0f172a;
  padding:12px 14px;
  border-bottom:1px solid rgba(15,23,42,0.08);
  background:linear-gradient(180deg, rgba(255,255,255,0.6), rgba(250,250,250,0.85));
}
.main-card td{
  padding:12px 14px;
  border-bottom:1px solid rgba(15,23,42,0.04);
  color:#334155;
}
tr.open td{
  background:linear-gradient(180deg, rgba(230,255,240,0.9), rgba(255,255,255,0.85));
}
tr.closed td{
  background:linear-gradient(180deg, rgba(255,247,235,0.95), rgba(255,255,255,0.85));
  color:#475569;
}
/* ---------- Buttons ---------- */
.btn{
  padding:8px 12px;
  border-radius:10px;
  font-weight:700;
  text-decoration:none;
  display:inline-block;
  border:none;
  cursor:pointer;
}
.btn-open{
  background:linear-gradient(180deg,#34d399,#10b981);
  color:#fff;
  box-shadow:0 8px 18px rgba(16,185,129,0.12);
}
.btn-view{
  background:linear-gradient(180deg,#60a5fa,#3b82f6);
  color:#fff;
  box-shadow:0 8px 18px rgba(59,130,246,0.12);
}
/* ---------- Responsive ---------- */
@media (max-width:768px){
  body{padding:16px;}
  .info-card{flex-direction:column;gap:10px;text-align:center;}
  .info-card h1{font-size:20px;}
  .info-card a{margin-top:4px;}
  .main-card{padding:16px;}
  .main-card table{min-width:600px;font-size:14px;}
}
@media (max-width:480px){
  .info-card h1{font-size:18px;}
  .info-card{padding:16px;}
  .main-card{padding:14px;}
}
</style>
</head>
<body>
<div class="convo-layout">
  <div class="info-card">
    <h1>All Conversations</h1>
    <a href="contact.php">Back to Contact</a>
  </div>
<div class="main-card">
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
          </div>
          </div>
  <script>
    // optional: simple auto-refresh every 45s (uncomment if you want)
    // setInterval(()=> location.reload(), 45000);
  </script>
</body>
</html>