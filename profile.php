<?php
// profile.php - merged and cleaned version
declare(strict_types=1);

require_once 'common_start.php';
require_once 'db.php'; // must provide db() or $pdo

if (session_status() === PHP_SESSION_NONE) session_start();

// Helper: get PDO instance
function get_db_pdo(): PDO {
    if (function_exists('db')) {
        $pdoLocal = db();
        if ($pdoLocal instanceof PDO) return $pdoLocal;
    }
    global $pdo;
    if ($pdo instanceof PDO) return $pdo;
    throw new RuntimeException('No PDO instance found. Ensure db() or $pdo is available from db.php');
}

// Safe echo helper
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

// Determine current user id
function current_user_id(): int {
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    return 0;
}


// Redirect helper (PRG / canonical)
function redirect_profile(): void {
    header('Location: profile.php');
    exit;
}

// Handle POST: delete_address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete_address')) {
    if (function_exists('verify_csrf')) {
        try { verify_csrf(); } catch (Throwable $e) {
            if (function_exists('flash_set')) flash_set('error', 'Invalid request token.');
            redirect_profile();
        }
    }

    $delId = (int)($_POST['id'] ?? 0);
    $currentUserId = current_user_id();
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

// Ensure logged in (if not, allow page to render and let client overlay handle the login)
$currentUserId = current_user_id();
if ($currentUserId <= 0) {
    // Not logged in: allow the profile page to load (overlay will prompt login)
    $user = [];
    $latestAddress = null;
    $addresses = [];
}

// Fetch data for the page
try {
    $pdo = get_db_pdo();

    $stmt = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':uid' => $currentUserId]);
    $latestAddress = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare('SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute([':uid' => $currentUserId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('profile.php DB error: ' . $e->getMessage());
    $user = $user ?? [];
    $latestAddress = $latestAddress ?? null;
    $addresses = $addresses ?? [];
}

// Flash messages
$ok = function_exists('flash_get') ? flash_get('ok') : null;
$err = function_exists('flash_get') ? flash_get('error') : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Profile</title>
   <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div id="status-message" aria-live="polite"></div>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container">
  <?php if ($ok): ?><div class="flash flash-ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-error"><?= h($err) ?></div><?php endif; ?>

  <header class="page-header">
    <h1><i class="fa-regular fa-user"></i> Your Account</h1>
    <p>View and update your personal information</p>
  </header>

  <div class="profile-grid">
    <div class="profile-card">
      <div class="profile-header">
        <h2>Personal Details</h2>
        <p>Update your name, contact, and address</p>
      </div>
<section>
      <form id="profile-form" class="profile-form" method="post" action="update_profile.php">
        <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
        <div class="form-row">
          <div class="form-group">
            <label for="profile-name">Name</label>
            <input type="text" id="profile-name" name="name" value="<?= h($user['name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="profile-email">Email</label>
            <input type="email" id="profile-email" name="email" value="<?= h($user['email'] ?? '') ?>" readonly />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="profile-phone">Phone</label>
            <input type="tel" id="profile-phone" name="phone" value="<?= h($user['phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="profile-house_no">House No</label>
            <input type="text" id="profile-house_no" name="house_no" value="<?= h($latestAddress['house_no'] ?? '') ?>" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="profile-landmark">Landmark</label>
            <input type="text" id="profile-landmark" name="landmark" value="<?= h($latestAddress['landmark'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="profile-city">City</label>
            <input type="text" id="profile-city" name="city" value="<?= h($latestAddress['city'] ?? '') ?>" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="profile-pincode">Pincode</label>
            <input type="text" id="profile-pincode" name="pincode" value="<?= h($latestAddress['pincode'] ?? '') ?>" />
          </div>
        </div>

        <button type="submit" class="update-btn"><i class="fa-solid fa-pen-to-square"></i> Update Profile</button>
      </form>
    </div>
  </section>
  </br>
    <div class="addresses-card">
      <div class="profile-header"><h3>Saved Addresses</h3></div>
      <?php if (!empty($addresses)): ?>
        <div class="addresses-list">
          <?php foreach ($addresses as $addr): ?>
            <div class="address-item" style="padding:10px;margin-bottom:10px;border-radius:6px;background: #eeeeeeff;color: #160101ff;">
              <div><strong><?= h($addr['house_no']) ?></strong></div>
              <?php if (!empty($addr['landmark'])): ?><div><?= h($addr['landmark']) ?></div><?php endif; ?>
              <div><?= h($addr['city']) ?><?= (!empty($addr['city']) && !empty($addr['pincode'])) ? ' / ' : '' ?><?= h($addr['pincode']) ?></div>
              <div style="font-size:0.9em;color: #363636ff">Saved: <?= h($addr['created_at']) ?></div>
              <div style="margin-top:8px;">
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this address?');">
                  <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                  <input type="hidden" name="action" value="delete_address">
                  <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                  <button type="submit" class="btn btn-danger">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">You have no saved addresses.</p>
      <?php endif; ?>
    </div>
  </div>
      </br>
  <div class="logout-row">
    <form method="post" action="logout.php">
      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
      <button class="logout-btn" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
    </form>
  </div>
</div>
<script src="script.js"></script>
<script src="profile.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>