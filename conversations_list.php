<?php
// conversations_list.php - shows only tickets for the currently-logged-in user
require_once __DIR__ . '/config/config_auth.php'; // your auth file location
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');

/* Ensure session/login */
if (function_exists('require_login')) {
    // If your auth helper forces login, call it
    require_login();
}
// start session if not started
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// try common session keys produced by your auth
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['uid'] ?? null;

if (empty($userId)) {
    // not logged in — change redirect if your app uses different login page
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, user_id, ticket_id, subject, status, viewed, submitted_at
         FROM contact_queries
         WHERE user_id = :user_id
         ORDER BY FIELD(status,'open','closed'), submitted_at DESC"
    );
    $stmt->execute([':user_id' => $userId]);
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
  <style>
/* convo-table-dark-warm.css — dark warm theme (keeps all IDs/classes unchanged) */
/* ---------- THEME VARIABLES ---------- */
:root{
  --bg-dark: #0b0b0c;
  --panel-dark: #0f0f10;
  --panel-2: #141414;
  --text: #f3f3f3;
  --muted: #b0b0b0;
  --warm-yellow: #ffb84d;
  --warm-yellow-2: #ffcf73;
  --warm-red: #e85c41;
  --card-bg: linear-gradient(180deg, rgba(18,18,18,0.85), rgba(14,14,14,0.9));
  --radius:14px;
  --shadow: 0 14px 36px rgba(0,0,0,0.75);
  --shadow-soft: 0 20px 40px rgba(0,0,0,0.6);
  --font: system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  --maxw:1100px;
  --glass-border: rgba(255,255,255,0.04);
}

/* ---------- Base Layout ---------- */
*{box-sizing:border-box}
html,body{height:100%;margin:0;padding:0;}
body{
  font-family:var(--font);
  color:var(--text);
  background:
    radial-gradient(900px 400px at 8% 10%, rgba(255,184,77,0.02), transparent),
    linear-gradient(180deg,var(--bg-dark), #070707);
  display:flex;
  justify-content:center;
  padding:28px;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
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
  background: linear-gradient(180deg, rgba(255,184,77,0.03), rgba(232,92,65,0.02));
  border-radius:var(--radius);
  box-shadow:var(--shadow-soft);
  border:1px solid rgba(255,255,255,0.03);
  padding:20px 26px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
}
.info-card h1{
  font-size:22px;
  font-weight:800;
  color:var(--warm-yellow);
  margin:0;
}
.info-card a{
  text-decoration:none;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.17));
  padding:10px 16px;
  border-radius:10px;
  color: #fff;
  font-weight:700;
  border:1px solid rgba(255, 255, 255, 0.17);
  box-shadow:0 8px 20px rgba(0,0,0,0.6);
  transition:all .12s ease;
}
.info-card a:hover{
  background: linear-gradient(180deg, var(--warm-yellow), var(--warm-yellow-2));
  color: #000;
  transform:translateY(-1px);
  filter:brightness(1.06);
}

/* ---------- Main Table Card ---------- */
.main-card{
  background: var(--card-bg);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  border:1px solid rgba(255,255,255,0.03);
  padding:20px 22px;
  width:100%;
  overflow:auto;
}

/* desktop table */
.main-card table{
  width:100%;
  border-collapse:collapse;
  font-size:15px;
  color:var(--text);
}
.main-card th{
  text-align:left;
  font-weight:700;
  color:var(--warm-yellow-2);
  padding:12px 14px;
  border-bottom:1px solid rgba(255,255,255,0.03);
  background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
}
.main-card td{
  padding:12px 14px;
  border-bottom:1px solid rgba(255,255,255,0.02);
  color:var(--muted);
  vertical-align:middle;
}
tr.open td{
  background: linear-gradient(180deg, rgba(43, 71, 47, 0.53), rgba(20,20,20,0.15));
  color: #dff7e6;
}
tr.closed td{
  background: linear-gradient(180deg, rgba(92, 51, 36, 0.45), rgba(20,20,20,0.06));
  color: #ffc7b8cc;
}

/* ---------- Buttons ---------- */
.btn{
  padding:8px 12px;
  border-radius:10px;
  font-weight:700;
  display:inline-block;
  cursor:pointer;
  transition:transform .12s ease, box-shadow .12s ease, filter .12s ease;
}
.btn-open{
  background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.23));
  color: #ffcf73;
  border: 1px solid rgba(255, 255, 255, 0.21);
  box-shadow:0 8px 18px rgba(232,92,65,0.12);
}
.btn-open:hover{ transform:translateY(-2px); filter:brightness(1.02); background: linear-gradient(90deg, rgba(255,184,77,1), rgba(232,92,65,1)); color: #ffffff;}
.btn-view{
  background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.23));
  color: #e85c41;
  border: 1px solid rgba(255, 255, 255, 0.21);
  box-shadow:0 8px 18px rgba(255,184,77,0.10);
}
.btn-view:hover{ transform:translateY(-2px); filter:brightness(1.02); background: linear-gradient(90deg, rgba(232,92,65,1), rgba(255,184,77,1)); color: #ffffff;}

/* Fine-tune table rows for better dark readability */
.main-card tbody tr{ transition:background .14s ease, transform .12s ease; }
.main-card tbody tr:hover{ transform:translateY(-3px); box-shadow:0 12px 30px rgba(0,0,0,0.6); }

/* ---------- Responsive: convert rows to cards on small screens ---------- */
@media (max-width:800px){
  body{padding:16px;}
  .info-card{flex-direction:column;gap:10px;text-align:center;}
  .info-card h1{font-size:20px;}
  .info-card a{margin-top:4px;}
  .main-card{padding:16px;}

  /* hide header row, convert each tr into a block card */
  .main-card thead{ display:none; }
  .main-card table, .main-card tbody, .main-card tr, .main-card td {
    display:block;
    width:100%;
  }
  .main-card tr{
    margin-bottom:12px;
    border-radius:12px;
    padding:12px;
    box-shadow:0 6px 18px rgba(0,0,0,0.5);
    border:1px solid rgba(255,255,255,0.02);
    background: linear-gradient(180deg, rgba(18,18,18,0.85), rgba(22,22,22,0.92));
  }
  .main-card td{
    padding:10px 8px;
    border-bottom:none;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    font-size:14px;
    color:var(--muted);
  }
  .main-card td + td { margin-top:6px; } /* spacing between stacked fields */

  /* show label on left using data-label */
  .main-card td::before{
    content:attr(data-label);
    display:inline-block;
    width:36%;
    min-width:95px;
    font-weight:700;
    color:var(--warm-yellow-2);
    text-transform:capitalize;
    font-size:13px;
  }

  /* make Actions button full width on very small devices */
  .main-card .actions-wrap{
    display:flex;
    width:100%;
    justify-content:flex-end;
  }
  .main-card .btn{
    flex:0 0 auto;
    width:auto;
  }

  /* if extremely narrow, stack label and value vertically for readability */
  @media (max-width:420px){
    .main-card td{
      flex-direction:column;
      align-items:flex-start;
    }
    .main-card td::before{
      width:auto;
      margin-bottom:6px;
    }
    .main-card .actions-wrap{
      width:100%;
    }
    .main-card .btn{
      width:100%;
      text-align:center;
      padding:10px;
    }
  }
}

/* even smaller touch optimizations */
@media (max-width:480px){
  .info-card h1{font-size:18px;}
  .info-card{padding:16px;}
  .main-card{padding:14px;}
  body{padding:12px;}
}
/* reduced-motion support */
@media (prefers-reduced-motion: reduce){
  *{transition:none!important;animation:none!important;scroll-behavior:auto!important;}
}
  </style>
</head>
<body>
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
<script>
  // optional: simple auto-refresh every 45s (uncomment if you want)
  // setInterval(()=> location.reload(), 45000);
</script>
</body>
</html>