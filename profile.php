<?php
// profile.php - merged profile page with partners edit/delete handled inline
declare(strict_types=1);

require_once 'common_start.php';
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ---------- helpers ----------
if (!function_exists('get_db_pdo')) {
    function get_db_pdo(): PDO {
        if (function_exists('db')) {
            $maybe = db();
            if ($maybe instanceof PDO) return $maybe;
        }
        global $pdo;
        if ($pdo instanceof PDO) return $pdo;
        throw new RuntimeException('No PDO available. Ensure db() or $pdo is provided by db.php');
    }
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
        if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
        if (!empty($_SESSION['uid'])) return (int)$_SESSION['uid'];
        if (!empty($_SESSION['userid'])) return (int)$_SESSION['userid'];
        return 0;
    }
}

function redirect_profile(): void {
    header('Location: profile.php');
    exit;
}

// compute once
$currentUserId = current_user_id();

// ---------- POST handlers (address save, address delete, partner delete) ----------

// Save / update address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_address')) {
    if (function_exists('verify_csrf')) {
        try { verify_csrf(); } catch (Throwable $e) {
            if (function_exists('flash_set')) flash_set('error', 'Invalid request token.');
            redirect_profile();
        }
    }

    if ($currentUserId <= 0) {
        if (function_exists('flash_set')) flash_set('error', 'Not authenticated.');
        redirect_profile();
    }

    $house_no = trim((string)($_POST['house_no'] ?? ''));
    $landmark = trim((string)($_POST['landmark'] ?? ''));
    $city     = trim((string)($_POST['city'] ?? ''));
    $pincode  = trim((string)($_POST['pincode'] ?? ''));

    if ($city === '' || $pincode === '') {
        if (function_exists('flash_set')) flash_set('error', 'City and Pincode are required.');
        redirect_profile();
    }
    if (!preg_match('/^\d{4,8}$/', $pincode)) {
        if (function_exists('flash_set')) flash_set('error', 'Invalid pincode format.');
        redirect_profile();
    }

    try {
        $pdo = get_db_pdo();
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
        $stmt->execute([$currentUserId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            // If your addresses table doesn't have updated_at, this UPDATE still works (we don't use updated_at)
            $stmt = $pdo->prepare("UPDATE addresses SET house_no = ?, landmark = ?, city = ?, pincode = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$house_no ?: null, $landmark ?: null, $city, $pincode, (int)$existing, $currentUserId]);
            if (function_exists('flash_set')) flash_set('ok', 'Address updated.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$currentUserId, $house_no ?: null, $landmark ?: null, $city, $pincode]);
            if (function_exists('flash_set')) flash_set('ok', 'Address saved.');
        }
    } catch (Throwable $e) {
        error_log('profile.php save_address error: ' . $e->getMessage());
        if (function_exists('flash_set')) flash_set('error', 'Unable to save address.');
    }

    redirect_profile();
}

// Delete address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete_address')) {
    if (function_exists('verify_csrf')) {
        try { verify_csrf(); } catch (Throwable $e) {
            if (function_exists('flash_set')) flash_set('error', 'Invalid request token.');
            redirect_profile();
        }
    }

    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0 && $currentUserId > 0) {
        try {
            $pdo = get_db_pdo();
            $stmt = $pdo->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $delId, ':uid' => $currentUserId]);
            if (function_exists('flash_set')) flash_set('ok', 'Address removed.');
        } catch (Throwable $e) {
            error_log('profile.php delete_address error: ' . $e->getMessage());
            if (function_exists('flash_set')) flash_set('error', 'Unable to remove address.');
        }
    }
    redirect_profile();
}

// Delete partner (handle directly in profile.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete_partner')) {
    if (function_exists('verify_csrf')) {
        try { verify_csrf(); } catch (Throwable $e) {
            if (function_exists('flash_set')) flash_set('error', 'Invalid request token.');
            redirect_profile();
        }
    }

    $delId = (int)($_POST['id'] ?? 0);
    $table = $_POST['table'] ?? '';
    $allowed = ['gv_partners', 'partners'];

    if ($delId > 0 && $currentUserId > 0 && in_array($table, $allowed, true)) {
        try {
            $pdo = get_db_pdo();
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ? AND user_id = ?");
            $stmt->execute([$delId, $currentUserId]);
            if (function_exists('flash_set')) flash_set('ok', 'Partner deleted.');
        } catch (Throwable $e) {
            error_log('profile.php delete_partner error: ' . $e->getMessage());
            if (function_exists('flash_set')) flash_set('error', 'Unable to delete partner.');
        }
    }
    redirect_profile();
}

// ---------- Fetch data for page ----------
$user = [];
$latestAddress = null;
$addresses = [];
$gv_partners = [];
$partners = [];

try {
    $pdo = get_db_pdo();

    if ($currentUserId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':uid' => $currentUserId]);
        $latestAddress = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute([':uid' => $currentUserId]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Partners
        $stmtG = $pdo->prepare("SELECT id, gv_partner_id, name, created_at FROM gv_partners WHERE user_id = ? ORDER BY created_at DESC");
        $stmtG->execute([$currentUserId]);
        $gv_partners = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        $stmtP = $pdo->prepare("SELECT id, bank_name, partner_id, name, created_at FROM partners WHERE user_id = ? ORDER BY created_at DESC");
        $stmtP->execute([$currentUserId]);
        $partners = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('profile.php DB error: ' . $e->getMessage());
}

// Flash messages
$ok = function_exists('flash_get') ? flash_get('ok') : null;
$err = function_exists('flash_get') ? flash_get('error') : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profile</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* small styles used by this page (non-invasive) */
.container { max-width:1100px; margin:18px auto; padding:10px; }
.profile-grid { display:grid; grid-template-columns: 1fr 360px; gap:18px; align-items:start; }
.profile-card, .addresses-card, .saved-partners { background:#fff; border-radius:8px; padding:14px; box-shadow:0 6px 18px rgba(0,0,0,0.04); }
.form-row { display:flex; gap:12px; }
.form-group { flex:1; display:flex; flex-direction:column; }
input[type="text"], input[type="email"], input[type="tel"] { padding:8px; border-radius:6px; border:1px solid #ddd; }
.update-btn { padding:10px 12px; border-radius:6px; border:0; background:#0f9d58; color:#fff; cursor:pointer; }
.address-item { padding:10px; margin-bottom:10px; border-radius:6px; background:#f6f6f6; }
.partners-grid { display:flex; flex-wrap:wrap; gap:12px; margin-top:12px; }
.card { border:1px solid #eee; border-radius:8px; padding:12px; min-width:220px; max-width:320px; background:#fff; box-sizing:border-box; }
.card-title { font-weight:600; margin-bottom:6px; }
.card-meta { font-size:0.85rem; color:#666; margin-bottom:10px; }
.card-actions { display:flex; justify-content:flex-end; gap:8px; align-items:center; }
.btn { padding:6px 10px; border-radius:6px; border:none; cursor:pointer; }
.btn-danger { background:#d64545; color:#fff; }
.btn-secondary { background:#f0f0f0; color:#222; }
.btn-primary { background:#0f9d58; color:#fff; }
.ep-input { width:100%; padding:8px; margin:6px 0 10px; border-radius:6px; border:1px solid #ddd; box-sizing:border-box; }
.muted { color:#777; }
.flash { padding:10px; border-radius:6px; margin-bottom:10px; }
.flash-ok { background:#e6f4ea; color:#0f6f3d; }
.flash-error { background:#fdecea; color:#8a1f12; }
.logout-row { margin-top:12px; text-align:right; }
@media (max-width:980px) {
  .profile-grid { grid-template-columns: 1fr; }
  .partners-grid { flex-direction:column; }
  .card { width:100%; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container">
  <?php if ($ok): ?><div class="flash flash-ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-error"><?= h($err) ?></div><?php endif; ?>

  <header style="margin-bottom:12px">
    <h1><i class="fa-regular fa-user"></i> Your Account</h1>
    <p class="muted">View and update your personal information</p>
  </header>

  <div class="profile-grid">
    <div>
      <div class="profile-card">
        <h3>Personal Details</h3>
        <p class="muted">Update your name, contact and address</p>

        <!-- Profile update posts to update_profile.php (server handles and returns JSON or redirect) -->
        <form id="profile-form" class="profile-form" method="post" action="update_profile.php">
          <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
          <div class="form-row">
            <div class="form-group">
              <label for="profile-name">Name</label>
              <input type="text" id="profile-name" name="name" value="<?= h($user['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="profile-email">Email</label>
              <input type="email" id="profile-email" name="email" value="<?= h($user['email'] ?? '') ?>" readonly>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="profile-phone">Phone</label>
              <input type="tel" id="profile-phone" name="phone" value="<?= h($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="profile-house_no">House No</label>
              <input type="text" id="profile-house_no" name="house_no" value="<?= h($latestAddress['house_no'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="profile-landmark">Landmark</label>
              <input type="text" id="profile-landmark" name="landmark" value="<?= h($latestAddress['landmark'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="profile-city">City</label>
              <input type="text" id="profile-city" name="city" value="<?= h($latestAddress['city'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row" style="margin-top:8px;">
            <div class="form-group">
              <label for="profile-pincode">Pincode</label>
              <input type="text" id="profile-pincode" name="pincode" value="<?= h($latestAddress['pincode'] ?? '') ?>">
            </div>
          </div>

          <div style="margin-top:12px;">
            <button type="submit" class="update-btn"><i class="fa-solid fa-pen-to-square"></i> Update Profile</button>
          </div>
        </form>
      </div>

      <div style="height:16px"></div>

      <!-- Add / Update Address form -->
      <div class="profile-card">
        <h4>Saved Address</h4>
        <form method="post" onsubmit="return confirm('Save address?');">
          <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
          <input type="hidden" name="action" value="save_address">
          <div class="form-row">
            <div class="form-group">
              <label>House / Flat No</label>
              <input type="text" name="house_no" value="<?= h($latestAddress['house_no'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Landmark</label>
              <input type="text" name="landmark" value="<?= h($latestAddress['landmark'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" value="<?= h($latestAddress['city'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Pincode</label>
              <input type="text" name="pincode" value="<?= h($latestAddress['pincode'] ?? '') ?>" required>
            </div>
          </div>

          <div style="margin-top:10px;">
            <button type="submit" class="btn btn-primary">Save Address</button>
          </div>
        </form>
      </div>

      <div style="height:16px"></div>

      <!-- Saved Partners -->
      <div class="saved-partners">
        <h3>Saved Partners</h3>

        <?php if (empty($gv_partners) && empty($partners)): ?>
          <p class="muted">You haven't saved any partners yet.</p>
        <?php else: ?>
          <div class="partners-grid" id="partners-grid">
            <!-- GV partners -->
            <?php foreach ($gv_partners as $gp): ?>
              <div class="card partner-card gv-card" data-row-id="<?= (int)$gp['id'] ?>" data-table="gv_partners">
                <div class="card-title">GV <?= h($gp['gv_partner_id'] ?? '') ?><?php if (!empty($gp['name'])): ?> — <?= h($gp['name']) ?><?php endif; ?></div>
                <div class="card-meta">Saved: <?= h($gp['created_at'] ?? '') ?></div>
                <div class="card-actions">
                  <button class="btn btn-secondary edit-partner-btn"
                    data-id="<?= (int)$gp['id'] ?>"
                    data-table="gv_partners"
                    data-gv_partner_id="<?= h($gp['gv_partner_id'] ?? '') ?>"
                    data-name="<?= h($gp['name'] ?? '') ?>">
                    Edit
                  </button>

                  <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this partner?');">
                    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="id" value="<?= (int)$gp['id'] ?>">
                    <input type="hidden" name="table" value="gv_partners">
                    <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Normal partners -->
            <?php foreach ($partners as $p): ?>
              <div class="card partner-card" data-row-id="<?= (int)$p['id'] ?>" data-table="partners">
                <div class="card-title"><?= h($p['bank_name'] ?? '') ?> — <?= h($p['partner_id'] ?? '') ?><?php if (!empty($p['name'])): ?> (<?= h($p['name']) ?>)<?php endif; ?></div>
                <div class="card-meta">Saved: <?= h($p['created_at'] ?? '') ?></div>
                <div class="card-actions">
                  <button class="btn btn-secondary edit-partner-btn"
                    data-id="<?= (int)$p['id'] ?>"
                    data-table="partners"
                    data-bank_name="<?= h($p['bank_name'] ?? '') ?>"
                    data-partner_id="<?= h($p['partner_id'] ?? '') ?>"
                    data-name="<?= h($p['name'] ?? '') ?>">
                    Edit
                  </button>

                  <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this partner?');">
                    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="table" value="partners">
                    <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right column: addresses list + logout -->
    <aside>
      <div class="addresses-card">
        <h3>Saved Addresses</h3>
        <?php if (!empty($addresses)): ?>
          <?php foreach ($addresses as $addr): ?>
            <div class="address-item">
              <div><strong><?= h($addr['house_no']) ?></strong></div>
              <?php if (!empty($addr['landmark'])): ?><div><?= h($addr['landmark']) ?></div><?php endif; ?>
              <div><?= h($addr['city']) ?><?= (!empty($addr['city']) && !empty($addr['pincode'])) ? ' / ' : '' ?><?= h($addr['pincode']) ?></div>
              <div style="font-size:0.9em;color:#666">Saved: <?= h($addr['created_at']) ?></div>
              <div style="margin-top:8px;">
                <form method="post" onsubmit="return confirm('Delete this address?');">
                  <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete_address">
                  <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                  <button type="submit" class="btn btn-danger">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">You have no saved addresses.</p>
        <?php endif; ?>
      </div>

      <div style="height:12px"></div>

      <div class="profile-card">
        <form method="post" action="logout.php">
          <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
          <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </form>
      </div>
    </aside>
  </div>
</div>

<!-- Edit Partner Modal (hidden) -->
<div id="edit-partner-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:9999;align-items:center;justify-content:center;">
  <div id="edit-partner-modal" style="width:420px;max-width:95%;background:#fff;padding:18px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.2);">
    <h4 id="edit-partner-title" style="margin:0 0 8px;">Edit Partner</h4>

    <form id="edit-partner-form">
      <input type="hidden" name="id" id="ep-id" />
      <input type="hidden" name="table" id="ep-table" />

      <!-- GV fields (gv_partner_id + name - you added name column) -->
      <div id="ep-gv-fields" style="display:none;">
        <label>GV Partner ID</label>
        <input type="text" name="gv_partner_id" id="ep-gv_partner_id" class="ep-input" />
        <label>Name</label>
        <input type="text" name="name" id="ep-gv-name" class="ep-input" />
      </div>

      <!-- Normal partner fields -->
      <div id="ep-p-fields" style="display:none;">
        <label>Bank Name</label>
        <input type="text" name="bank_name" id="ep-bank_name" class="ep-input" />
        <label>Partner ID</label>
        <input type="text" name="partner_id" id="ep-partner_id" class="ep-input" />
        <label>Name</label>
        <input type="text" name="name" id="ep-p-name" class="ep-input" />
      </div>

      <div style="margin-top:12px;text-align:right;">
        <button type="button" id="ep-cancel" class="btn">Cancel</button>
        <button type="submit" id="ep-save" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
// Inline JS: open modal, populate fields, submit via AJAX to update_profile.php
(function(){
  function flash(msg, ok=true) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style = 'position:fixed;right:18px;top:18px;padding:8px 12px;border-radius:8px;background:' + (ok? '#0f9d58' : '#d64545') + ';color:#fff;z-index:10000;';
    document.body.appendChild(el);
    setTimeout(()=> el.remove(), 2600);
  }

  const overlay = document.getElementById('edit-partner-overlay');
  const form = document.getElementById('edit-partner-form');

  // Open modal
  document.querySelectorAll('.edit-partner-btn').forEach(btn => {
    btn.addEventListener('click', function(){
      const id = btn.dataset.id;
      const table = btn.dataset.table;

      document.getElementById('ep-id').value = id;
      document.getElementById('ep-table').value = table;

      // hide both
      document.getElementById('ep-gv-fields').style.display = 'none';
      document.getElementById('ep-p-fields').style.display = 'none';

      if (table === 'gv_partners') {
        document.getElementById('ep-gv-fields').style.display = '';
        document.getElementById('ep-gv_partner_id').value = btn.dataset.gv_partner_id || '';
        document.getElementById('ep-gv-name').value = btn.dataset.name || '';
        document.getElementById('edit-partner-title').textContent = 'Edit GV Partner';
      } else {
        document.getElementById('ep-p-fields').style.display = '';
        document.getElementById('ep-bank_name').value = btn.dataset.bank_name || '';
        document.getElementById('ep-partner_id').value = btn.dataset.partner_id || '';
        document.getElementById('ep-p-name').value = btn.dataset.name || '';
        document.getElementById('edit-partner-title').textContent = 'Edit Partner';
      }

      overlay.style.display = 'flex';
    });
  });

  // Cancel
  document.getElementById('ep-cancel').addEventListener('click', function(){
    overlay.style.display = 'none';
  });

  // Submit via AJAX
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const id = document.getElementById('ep-id').value;
    const table = document.getElementById('ep-table').value;

    const fd = new FormData();
    fd.append('action', 'update_partner');
    fd.append('id', id);
    fd.append('table', table);

    if (table === 'gv_partners') {
      fd.append('gv_partner_id', document.getElementById('ep-gv_partner_id').value.trim());
      fd.append('name', document.getElementById('ep-gv-name').value.trim());
    } else {
      fd.append('bank_name', document.getElementById('ep-bank_name').value.trim());
      fd.append('partner_id', document.getElementById('ep-partner_id').value.trim());
      fd.append('name', document.getElementById('ep-p-name').value.trim());
    }

    // If you use a CSRF token in a meta tag or global var, append it here:
    // fd.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('update_profile.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(async res => {
      let txt = await res.text();
      try {
        const json = JSON.parse(txt || '{}');
        if (json.success) {
          // update DOM card
          const card = document.querySelector(`.card[data-row-id="${id}"][data-table="${table}"]`);
          if (card) {
            const titleEl = card.querySelector('.card-title');
            if (table === 'gv_partners') {
              titleEl.textContent = 'GV ' + fd.get('gv_partner_id') + (fd.get('name') ? ' — ' + fd.get('name') : '');
            } else {
              titleEl.textContent = (fd.get('bank_name') || '') + ' — ' + (fd.get('partner_id') || '') + (fd.get('name') ? ' (' + fd.get('name') + ')' : '');
            }
            // update edit button data attributes
            const editBtn = card.querySelector('.edit-partner-btn');
            if (editBtn) {
              if (table === 'gv_partners') {
                editBtn.dataset.gv_partner_id = fd.get('gv_partner_id');
                editBtn.dataset.name = fd.get('name');
              } else {
                editBtn.dataset.bank_name = fd.get('bank_name');
                editBtn.dataset.partner_id = fd.get('partner_id');
                editBtn.dataset.name = fd.get('name');
              }
            }
          }
          flash(json.message || 'Updated', true);
          overlay.style.display = 'none';
        } else {
          flash(json.message || 'Update failed', false);
        }
      } catch (err) {
        console.error('Invalid JSON response:', txt);
        flash('Server error', false);
      }
    }).catch(err => {
      console.error(err);
      flash('Network error', false);
    });
  });

  // Show server-side delete status (optional: based on ?deleted=1 in URL)
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('deleted') === '1') flash('Deleted', true);
  if (urlParams.get('deleted') === '0') flash('Delete failed', false);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
