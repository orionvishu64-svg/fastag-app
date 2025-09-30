<?php
require_once __DIR__ . '/common_start.php';   // handles session_start once
require_once __DIR__ . '/db.php';             // your PDO connection

// Ensure user is logged in
if (empty($_SESSION['user']['id'])) {
    header("Location: /login.php");
    exit();
}

// Define canonical user_id
$user_id = (int)$_SESSION['user']['id'];

// Messages
$error_gv = "";
$error_partner = "";
$success_gv = "";
$success_partner = "";
$success_delete = "";

// --- Handle deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $del_id = (int) $_POST['delete_partner'];
    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: partner_form.php?deleted=1");
    exit();
}

// --- Handle form actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $gv_partner = trim($_POST['gv_partner'] ?? "");
    $bank = trim($_POST['bank_name'] ?? "");
    $partner_id = trim($_POST['partner_id'] ?? "");
    $name = trim($_POST['name'] ?? "");
    $action = $_POST['action'];

    $isAlnum = fn($s) => preg_match('/^[A-Za-z0-9]+$/', $s);

    // Save GV Partner
    if ($action === "save_gv") {
        if ($gv_partner === "") {
            $error_gv = "Please enter a GV Partner ID to save.";
        } elseif (!$isAlnum($gv_partner)) {
            $error_gv = "GV Partner ID must be alphanumeric.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO gv_partners (user_id, gv_partner_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $gv_partner]);
            header("Location: partner_form.php?gv_added=1");
            exit();
        }
    }

    // Save Partner
    if ($action === "save_partner") {
        if ($bank === "" || $partner_id === "" || $name === "") {
            $error_partner = "Please fill Bank, Partner ID, and Name before saving.";
        } elseif (!$isAlnum($partner_id)) {
            $error_partner = "Partner ID must be alphanumeric.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO partners (user_id, bank_name, partner_id, name, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $bank, $partner_id, $name]);
            header("Location: partner_form.php?partner_saved=1");
            exit();
        }
    }

    // Go to Dashboard (check if at least one record exists)
    if ($action === "save") {
        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM gv_partners WHERE user_id = ?");
        $stmt1->execute([$user_id]);
        $gv_count = $stmt1->fetchColumn();

        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE user_id = ?");
        $stmt2->execute([$user_id]);
        $partner_count = $stmt2->fetchColumn();

        if ($gv_count > 0 || $partner_count > 0) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error_partner = "Please add at least one GV Partner ID or Partner record before proceeding.";
        }
    }
}

// --- Messages via GET ---
if (isset($_GET['gv_added'])) $success_gv = "GV Partner ID saved successfully.";
if (isset($_GET['partner_saved'])) $success_partner = "Partner saved successfully!";
if (isset($_GET['deleted'])) $success_delete = "Partner deleted successfully.";

// --- Fetch saved partners ---
$stmt = $pdo->prepare("SELECT id, bank_name, partner_id, name, created_at FROM partners WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$savedPartners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Partner Form</title>
<style>
  body { font-family: Arial, sans-serif; background:#f4f6f8; padding:30px; }
  .container { max-width:800px; margin:auto; background:#fff; padding:26px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
  h2 { text-align:center; margin-bottom:20px; }
  label { font-weight:600; display:block; margin:10px 0 6px; }
  input { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; }
  .or-divider { text-align:center; margin:20px 0; font-weight:700; }
  .group { border:1px solid #eee; padding:15px; border-radius:8px; margin-top:10px; }
  button { padding:10px 14px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
  .save-btn { background:#28a745; color:white; margin-top:10px; }
  .save-partner { background:#0b5ed7; color:white; margin-top:10px; }
  .save-gv { background:#6f42c1; color:white; margin-top:10px; }
  .delete-btn { background:#dc3545; color:white; margin-top:10px; }
  .msg { padding:10px; border-radius:6px; margin:10px 0; font-weight:600; }
  .msg.success { background:#e6ffed; color:#166534; }
  .msg.error { background:#fff1f2; color:#9f1239; }
  .cards { margin:20px 0; }
  .card { background:#f9fbff; border:1px solid #ddd; padding:12px; border-radius:8px; margin-bottom:12px; }
  .btn-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
</style>
</head>
<body>
    <div class="nav-logo">
        <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png" alt="ApnaPayment">
    </div>
<div class="container">
  <h2>Please fill this form to proceed</h2>
  <p>We're likely interested to know which banks you worked for.</p>
  <br>

  <form method="post" onsubmit="return validateForm(event)">
    <!-- GV Partner -->
    <label for="gv_partner">GV Partner ID</label>
    <?php if ($success_gv): ?><div class="msg success"><?= $success_gv ?></div><?php endif; ?>
    <?php if ($error_gv): ?><div class="msg error"><?= $error_gv ?></div><?php endif; ?>
    <input type="text" id="gv_partner" name="gv_partner" placeholder="Enter GV Partner ID">
    <div class="btn-row">
      <button type="submit" name="action" value="save_gv" class="save-gv">Save GV Partner</button>
    </div>

    <div class="or-divider">OR</div>

    <!-- Partner Fields -->
    <?php if ($success_partner): ?><div class="msg success"><?= $success_partner ?></div><?php endif; ?>
    <?php if ($error_partner): ?><div class="msg error"><?= $error_partner ?></div><?php endif; ?>
    <div class="group">
      <label>Bank Name</label>
      <input type="text" name="bank_name" placeholder="Enter Bank Name">

      <label>Partner ID</label>
      <input type="text" name="partner_id" placeholder="Enter Partner ID">

      <label>Name</label>
      <input type="text" name="name" placeholder="Enter Your Name">
    </div>

    <div class="btn-row">
      <button type="submit" name="action" value="save_partner" class="save-partner">Save Partner</button>
    </div>
  </form>

  <!-- Saved Partners -->
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

  <!-- Go to Dashboard -->
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

  if (action === "save_gv") {
    if (gv === "" || !alnum.test(gv)) {
      alert("GV Partner ID must be alphanumeric.");
      return false;
    }
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

  return true; // For "save" we let PHP check
}
</script>
</body>
</html>
