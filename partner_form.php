<?php
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

// Ensure user is logged in
if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit();
}

$user_id = (int)$_SESSION['user']['id'];
$error_gv = $error_partner = $success_gv = $success_partner = $success_delete = "";

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $del_id = (int) $_POST['delete_partner'];
    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: partner_form.php?deleted=1");
    exit();
}

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $gv_partner = trim($_POST['gv_partner'] ?? "");
    $bank = trim($_POST['bank_name'] ?? "");
    $partner_id = trim($_POST['partner_id'] ?? "");
    $name = trim($_POST['name'] ?? "");
    $action = $_POST['action'];
    $isAlnum = fn($s) => preg_match('/^[A-Za-z0-9]+$/', $s);

    if ($action === "save_gv") {
        if ($gv_partner === "") $error_gv = "Please enter a GV Partner ID.";
        elseif (!$isAlnum($gv_partner)) $error_gv = "GV Partner ID must be alphanumeric.";
        else {
            $stmt = $pdo->prepare("INSERT INTO gv_partners (user_id, gv_partner_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $gv_partner]);
            header("Location: partner_form.php?gv_added=1");
            exit();
        }
    }

    if ($action === "save_partner") {
        if ($bank === "" || $partner_id === "" || $name === "") $error_partner = "Please fill Bank, Partner ID, and Name.";
        elseif (!$isAlnum($partner_id)) $error_partner = "Partner ID must be alphanumeric.";
        else {
            $stmt = $pdo->prepare("INSERT INTO partners (user_id, bank_name, partner_id, name, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $bank, $partner_id, $name]);
            header("Location: partner_form.php?partner_saved=1");
            exit();
        }
    }

    if ($action === "save") {
        $gv_count = $pdo->query("SELECT COUNT(*) FROM gv_partners WHERE user_id = $user_id")->fetchColumn();
        $partner_count = $pdo->query("SELECT COUNT(*) FROM partners WHERE user_id = $user_id")->fetchColumn();

        if ($gv_count > 0 || $partner_count > 0) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error_partner = "Please add at least one GV Partner ID or Partner record before proceeding.";
        }
    }
}

if (isset($_GET['gv_added'])) $success_gv = "GV Partner ID saved successfully.";
if (isset($_GET['partner_saved'])) $success_partner = "Partner saved successfully!";
if (isset($_GET['deleted'])) $success_delete = "Partner deleted successfully.";

$stmt = $pdo->prepare("SELECT id, bank_name, partner_id, name, created_at FROM partners WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$savedPartners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Partner Form - ApnaPayment</title>

<style>
:root {
  --bg-dark: #0b0b0c;
  --panel-dark: #0f0f10;
  --panel-2: #141414;
  --text: #f3f3f3;
  --muted: #b0b0b0;
  --warm-yellow: #ffb84d;
  --warm-yellow-2: #ffcf73;
  --warm-red: #e85c41;
  --shadow-1: 0 14px 36px rgba(0,0,0,0.6);
  --radius: 14px;
  --form-max: 720px;
  --font: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* ---------- Base ---------- */
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  font-family: var(--font);
  background: linear-gradient(180deg, var(--bg-dark), #070707);
  color: var(--text);
  height: 100%;
  width: 100%;
}
body {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 100px 16px 40px;
}

/* ---------- Logo ---------- */
.nav-logo { position: fixed; top: 20px; left: 24px; z-index: 10; }
.nav-logo img { height: 38px; width: auto; display: block; }

/* ---------- Container ---------- */
.container {
  width: 100%;
  max-width: var(--form-max);
  background: linear-gradient(180deg, var(--panel-dark), var(--panel-2));
  border-radius: var(--radius);
  padding: 28px;
  box-shadow: var(--shadow-1);
  border: 1px solid rgba(255,255,255,0.04);
}

/* ---------- Headings ---------- */
.container h2 {
  text-align: center;
  font-size: 1.4rem;
  color: var(--warm-yellow);
  font-weight: 800;
  margin-bottom: 0.5rem;
}
.container p {
  text-align: center;
  color: var(--muted);
  margin-bottom: 1rem;
  font-size: 0.95rem;
}

/* ---------- Form ---------- */
label {
  display: block;
  margin: 10px 0 6px;
  font-weight: 600;
  color: var(--muted);
  font-size: 0.95rem;
}
input, select, textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  background: linear-gradient(180deg,#0b0b0c,#0f0f10);
  color: var(--text);
  font-size: 15px;
  transition: all 0.18s ease;
}
input::placeholder { color: rgba(243,243,243,0.28); }
input:focus {
  border-color: var(--warm-yellow);
  box-shadow: 0 0 0 6px rgba(255,184,77,0.04);
  outline: none;
}

/* ---------- Buttons ---------- */
button {
  cursor: pointer;
  border: none;
  border-radius: 10px;
  padding: 10px 14px;
  font-weight: 700;
  transition: transform 0.06s ease, filter 0.12s ease;
}
.save-btn {
  width: 100%;
  background: linear-gradient(90deg, var(--warm-yellow), var(--warm-yellow-2));
  color: #111;
  margin-top: 10px;
  box-shadow: 0 10px 26px rgba(255,184,77,0.08);
}
.save-btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
.save-partner, .save-gv {
  background: linear-gradient(90deg, var(--warm-yellow), var(--warm-yellow-2));
  color: #111;
}
.delete-btn {
  background: linear-gradient(180deg, var(--warm-red), #b9301f);
  color: #111;
  padding: 8px 10px;
  border-radius: 8px;
  box-shadow: 0 6px 18px rgba(232,92,65,0.1);
}

/* ---------- Messages ---------- */
.msg {
  padding: 10px;
  border-radius: 8px;
  margin: 10px 0;
  font-weight: 700;
  font-size: 14px;
  border: 1px solid rgba(255,255,255,0.02);
}
.msg.success {
  background: linear-gradient(180deg, rgba(255,248,236,0.04), rgba(255,248,236,0.02));
  color: var(--warm-yellow);
  border-color: rgba(255,184,77,0.06);
}
.msg.error {
  background: linear-gradient(180deg, rgba(60,10,10,0.1), rgba(40,6,6,0.08));
  color: var(--warm-red);
  border-color: rgba(232,92,65,0.06);
}

/* ---------- Saved Partner Cards ---------- */
.cards { margin-top: 18px; }
.cards h3 {
  margin-bottom: 12px;
  font-size: 1rem;
  font-weight: 800;
  color: var(--text);
}
.card {
  background: linear-gradient(180deg, #0b0b0c, #0f0f10);
  border: 1px solid rgba(255,255,255,0.03);
  padding: 14px;
  border-radius: 10px;
  margin-bottom: 12px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.5);
}
.card p { margin: 6px 0; color: var(--muted); font-size: 14px; }

/* ---------- Utility ---------- */
.btn-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
.or-divider { text-align: center; font-weight: 700; color: var(--muted); margin: 18px 0; }

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
  .container { padding: 18px; }
  .nav-logo { top: 14px; left: 16px; }
}

/* Accessibility */
:focus { outline: 3px solid rgba(255,184,77,0.08); outline-offset: 2px; }
</style>
</head>

<body>
<div class="nav-logo">
  <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200White.png" alt="ApnaPayment">
</div>

<div class="container">
  <h2>Partner Information</h2>
  <p>Please fill this form to continue</p>

  <form method="post" onsubmit="return validateForm(event)">
    <label for="gv_partner">GV Partner ID</label>
    <?php if ($success_gv): ?><div class="msg success"><?= $success_gv ?></div><?php endif; ?>
    <?php if ($error_gv): ?><div class="msg error"><?= $error_gv ?></div><?php endif; ?>
    <input type="text" id="gv_partner" name="gv_partner" placeholder="Enter GV Partner ID">
    <div class="btn-row">
      <button type="submit" name="action" value="save_gv" class="save-gv">Save GV Partner</button>
    </div>

    <div class="or-divider">OR</div>

    <?php if ($success_partner): ?><div class="msg success"><?= $success_partner ?></div><?php endif; ?>
    <?php if ($error_partner): ?><div class="msg error"><?= $error_partner ?></div><?php endif; ?>

    <label>Bank Name</label>
    <input type="text" name="bank_name" placeholder="Enter Bank Name">
    <label>Partner ID</label>
    <input type="text" name="partner_id" placeholder="Enter Partner ID">
    <label>Name</label>
    <input type="text" name="name" placeholder="Enter Your Name">

    <div class="btn-row">
      <button type="submit" name="action" value="save_partner" class="save-partner">Save Partner</button>
    </div>
  </form>

  <?php if (!empty($savedPartners)): ?>
  <div class="cards">
    <h3>Saved Partners</h3>
    <?php foreach ($savedPartners as $p): ?>
      <div class="card">
        <p><strong>Bank:</strong> <?= htmlspecialchars($p['bank_name']) ?></p>
        <p><strong>Partner ID:</strong> <?= htmlspecialchars($p['partner_id']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($p['name']) ?></p>
        <p><small>Saved at: <?= htmlspecialchars($p['created_at']) ?></small></p>
        <form method="post" style="display:inline;">
          <input type="hidden" name="delete_partner" value="<?= $p['id'] ?>">
          <button type="submit" class="delete-btn" onclick="return confirm('Delete this partner?')">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" style="margin-top:10px;">
    <input type="hidden" name="action" value="save">
    <button type="submit" class="save-btn">Go to Dashboard</button>
  </form>
</div>

<script>
function validateForm(e) {
  let action = e.submitter ? e.submitter.value : "";
  let gv = document.getElementById('gv_partner').value.trim();
  let bank = document.querySelector('[name="bank_name"]').value.trim();
  let pid = document.querySelector('[name="partner_id"]').value.trim();
  let name = document.querySelector('[name="name"]').value.trim();
  const alnum = /^[A-Za-z0-9]+$/;

  if (action === "save_gv" && (gv === "" || !alnum.test(gv))) {
    alert("GV Partner ID must be alphanumeric.");
    return false;
  }
  if (action === "save_partner") {
    if (bank === "" || pid === "" || name === "") {
      alert("Please fill Bank, Partner ID, and Name.");
      return false;
    }
    if (!alnum.test(pid)) {
      alert("Partner ID must be alphanumeric.");
      return false;
    }
  }
  return true;
}
</script>
</body>
</html>
