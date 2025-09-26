<?php
// profile.php - cleaned, no overlay, AJAX profile/address/partner updates
declare(strict_types=1);
require_once 'common_start.php';
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Helpers
if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
        if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
        return 0;
    }
}
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('get_db_pdo')) {
    function get_db_pdo(): PDO {
        if (function_exists('db')) {
            $maybe = db();
            if ($maybe instanceof PDO) return $maybe;
        }
        global $pdo;
        if ($pdo instanceof PDO) return $pdo;
        throw new RuntimeException('No PDO available. Ensure db.php exposes db() or $pdo.');
    }
}
function redirect_profile(){ header('Location: profile.php'); exit; }

$currentUserId = current_user_id();

// Handle delete actions server-side (partners & addresses)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_partner') {
        // optional CSRF
        if (function_exists('verify_csrf')) {
            try { verify_csrf(); } catch (Throwable $e) { redirect_profile(); }
        }
        $delId = (int)($_POST['id'] ?? 0);
        $table = $_POST['table'] ?? '';
        $allowed = ['gv_partners','partners'];
        if ($delId > 0 && $currentUserId > 0 && in_array($table, $allowed, true)) {
            try {
                $pdo = get_db_pdo();
                $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ? AND user_id = ?");
                $stmt->execute([$delId, $currentUserId]);
            } catch (Throwable $e) {
                error_log('profile.php delete_partner error: ' . $e->getMessage());
            }
        }
        redirect_profile();
    }

    if ($action === 'delete_address') {
        if (function_exists('verify_csrf')) {
            try { verify_csrf(); } catch (Throwable $e) { redirect_profile(); }
        }
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId > 0 && $currentUserId > 0) {
            try {
                $pdo = get_db_pdo();
                $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$delId, $currentUserId]);
            } catch (Throwable $e) {
                error_log('profile.php delete_address error: ' . $e->getMessage());
            }
        }
        redirect_profile();
    }
}

// Fetch data for page
$user = [];
$latestAddress = null;
$addresses = [];
$gv_partners = [];
$partners = [];

try {
    $pdo = get_db_pdo();
    if ($currentUserId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$currentUserId]);
        $latestAddress = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$currentUserId]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, gv_partner_id, name, created_at FROM gv_partners WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$currentUserId]);
        $gv_partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, bank_name, partner_id, name, created_at FROM partners WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$currentUserId]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('profile.php DB error: ' . $e->getMessage());
}

$ok = function_exists('flash_get') ? flash_get('ok') : null;
$err = function_exists('flash_get') ? flash_get('error') : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your Profile</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
</head>
<body class="profile-page">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
  <?php if ($ok): ?><div class="flash flash-ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-error"><?= h($err) ?></div><?php endif; ?>

  <section class="profile-section">
    <h1>Your Account</h1>

    <!-- Profile update (AJAX) -->
    <form id="profile-form" class="card profile-card" method="post">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <h2>Personal Details</h2>

      <label for="profile-name">Name</label>
      <input id="profile-name" name="name" type="text" value="<?= h($user['name'] ?? '') ?>" required>

      <label for="profile-email">Email (readonly)</label>
      <input id="profile-email" name="email" type="email" value="<?= h($user['email'] ?? '') ?>" readonly>

      <label for="profile-phone">Phone</label>
      <input id="profile-phone" name="phone" type="tel" value="<?= h($user['phone'] ?? '') ?>">

      <div class="form-actions">
        <button id="profile-save-btn" class="btn btn-primary" type="submit">Update Profile</button>
      </div>
    </form>

    <!-- Address save (AJAX) -->
    <form id="address-form" class="card address-form" method="post">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <h3>Saved Address (edit & Save)</h3>

      <label>House / Flat No</label>
      <input id="addr-house" name="house_no" type="text" value="<?= h($latestAddress['house_no'] ?? '') ?>">

      <label>Landmark</label>
      <input id="addr-landmark" name="landmark" type="text" value="<?= h($latestAddress['landmark'] ?? '') ?>">

      <label>City</label>
      <input id="addr-city" name="city" type="text" value="<?= h($latestAddress['city'] ?? '') ?>">

      <label>Pincode</label>
      <input id="addr-pincode" name="pincode" type="text" value="<?= h($latestAddress['pincode'] ?? '') ?>">

      <div class="form-actions">
        <button id="address-save-btn" class="btn btn-primary" type="submit">Save Address</button>
      </div>
    </form>

    <!-- Saved Addresses shown above partners -->
    <div class="card addresses-list">
      <h3>Saved Addresses</h3>
      <?php if (!empty($addresses)): ?>
        <?php foreach ($addresses as $addr): ?>
          <div class="address-item">
            <div class="addr-line"><strong><?= h($addr['house_no']) ?></strong></div>
            <?php if (!empty($addr['landmark'])): ?><div class="addr-line"><?= h($addr['landmark']) ?></div><?php endif; ?>
            <div class="addr-line small"><?= h($addr['city']) ?><?= (!empty($addr['city']) && !empty($addr['pincode'])) ? ' / ' : '' ?><?= h($addr['pincode']) ?></div>
            <div class="addr-actions">
              <form method="post" onsubmit="return confirm('Delete this address?');">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_address">
                <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                <button class="btn btn-danger" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted">You have no saved addresses.</p>
      <?php endif; ?>
    </div>

    <!-- Saved Partners -->
     <div class="card partners-block">
  <div class="partners-header">
    <h3>Saved Partners</h3>
    <button id="add-partner-btn" class="btn btn-primary btn-sm" type="button">Add Partner</button>
  </div>
  <div id="add-partner-editor"></div> <!-- inline add form goes here -->
  <div class="partners-grid">
        <?php foreach ($gv_partners as $gp): ?>
          <div class="partner-card" data-row-id="<?= (int)$gp['id'] ?>" data-table="gv_partners">
            <div class="partner-title">GV <?= h($gp['gv_partner_id']) ?><?= !empty($gp['name']) ? ' — '.h($gp['name']) : '' ?></div>
            <div class="partner-meta">Saved: <?= h($gp['created_at']) ?></div>
            <div class="card-actions">
              <button class="btn btn-secondary edit-partner-btn"
                      data-id="<?= (int)$gp['id'] ?>"
                      data-table="gv_partners"
                      data-gv_partner_id="<?= h($gp['gv_partner_id']) ?>"
                      data-name="<?= h($gp['name']) ?>">Edit</button>

              <form method="post" onsubmit="return confirm('Delete partner?');">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_partner">
                <input type="hidden" name="id" value="<?= (int)$gp['id'] ?>">
                <input type="hidden" name="table" value="gv_partners">
                <button class="btn btn-danger" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

        <?php foreach ($partners as $p): ?>
          <div class="partner-card" data-row-id="<?= (int)$p['id'] ?>" data-table="partners">
            <div class="partner-title"><?= h($p['bank_name']) ?> — <?= h($p['partner_id']) ?><?= !empty($p['name']) ? ' ('.h($p['name']).')' : '' ?></div>
            <div class="partner-meta">Saved: <?= h($p['created_at']) ?></div>
            <div class="card-actions">
              <button class="btn btn-secondary edit-partner-btn"
                      data-id="<?= (int)$p['id'] ?>"
                      data-table="partners"
                      data-bank_name="<?= h($p['bank_name']) ?>"
                      data-partner_id="<?= h($p['partner_id']) ?>"
                      data-name="<?= h($p['name']) ?>">Edit</button>

              <form method="post" onsubmit="return confirm('Delete partner?');">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete_partner">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="table" value="partners">
                <button class="btn btn-danger" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>

  </section>
</main>
<script src="script.js"></script>
<script src="profile.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
