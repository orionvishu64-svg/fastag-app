<?php
require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user']['id'])) {
    header("Location: /index.html");
    exit();
}
$user_id = (int)$_SESSION['user']['id'];
$error_gv = $error_partner = $success_gv = $success_partner = $success_delete = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $del_id = (int) $_POST['delete_partner'];
    $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: partner_form.php?deleted=1");
    exit();
} 

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
    <title>Apna Payment Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width:860px">

  <div class="text-center mb-4">
    <img src="https://www.apnapayment.com/website/img/logo/ApnaPayment200Black.png"
         alt="ApnaPayment"
         height="42">
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">

      <div class="text-center mb-4">
        <h3 class="fw-bold text-primary">Partner Information</h3>
        <p class="text-muted mb-0">Please fill this form to continue</p>
      </div>

      <div class="mb-4">
        <h6 class="fw-semibold mb-3">GV Partner ID</h6>

        <?php if ($success_gv): ?>
          <div class="alert alert-success"><?= $success_gv ?></div>
        <?php endif; ?>

        <?php if ($error_gv): ?>
          <div class="alert alert-danger"><?= $error_gv ?></div>
        <?php endif; ?>

        <input type="text"
               class="form-control form-control-lg mb-3"
               name="gv_partner"
               placeholder="Enter GV Partner ID">

        <button type="submit"
                name="action"
                value="save_gv"
                class="btn btn-warning w-100 fw-semibold">
          Save GV Partner
        </button>
      </div>

      <div class="text-center text-muted fw-semibold my-4">OR</div>

      <div class="mb-4">
        <h6 class="fw-semibold mb-3">Bank Partner Details</h6>

        <?php if ($success_partner): ?>
          <div class="alert alert-success"><?= $success_partner ?></div>
        <?php endif; ?>

        <?php if ($error_partner): ?>
          <div class="alert alert-danger"><?= $error_partner ?></div>
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-4">
            <input class="form-control"
                   name="bank_name"
                   placeholder="Bank Name">
          </div>
          <div class="col-md-4">
            <input class="form-control"
                   name="partner_id"
                   placeholder="Partner ID">
          </div>
          <div class="col-md-4">
            <input class="form-control"
                   name="name"
                   placeholder="Your Name">
          </div>
        </div>

        <button type="submit"
                name="action"
                value="save_partner"
                class="btn btn-warning w-100 fw-semibold mt-3">
          Save Partner
        </button>
      </div>

      <?php if (!empty($savedPartners)): ?>
      <hr class="my-4">

      <h6 class="fw-bold mb-3">Saved Partners</h6>

      <div class="row g-3">
        <?php foreach ($savedPartners as $p): ?>
        <div class="col-md-6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">

              <div class="d-flex align-items-center mb-2">
                <div class="rounded-circle bg-warning text-dark fw-bold d-flex
                            align-items-center justify-content-center me-3"
                     style="width:40px;height:40px">
                  <?= strtoupper(substr($p['bank_name'],0,1)) ?>
                </div>
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($p['bank_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($p['partner_id']) ?></small>
                </div>
              </div>

              <div class="small text-muted">
                Name: <?= htmlspecialchars($p['name']) ?><br>
                Saved at: <?= htmlspecialchars($p['created_at']) ?>
              </div>

              <form method="post" class="mt-3">
                <input type="hidden" name="delete_partner" value="<?= $p['id'] ?>">
                <button class="btn btn-outline-danger btn-sm w-100"
                        onclick="return confirm('Delete this partner?')">
                  Delete
                </button>
              </form>

            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="post" class="mt-5">
        <input type="hidden" name="action" value="save">
        <button class="btn btn-primary btn-lg w-100 fw-semibold">
          Go to Dashboard
        </button>
      </form>

    </div>
  </div>
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