<?php
// profile.php
declare(strict_types=1);

require_once __DIR__ . '/config/common_start.php';
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        if (!empty($_SESSION['user']['id'])) {
            return (int)$_SESSION['user']['id'];
        }
        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        return 0;
    }
}

if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('get_db_pdo')) {
    function get_db_pdo(): PDO {
        if (function_exists('db')) {
            $maybe = db();
            if ($maybe instanceof PDO) {
                return $maybe;
            }
        }
        global $pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        throw new RuntimeException('No PDO available. Ensure /config/db.php exposes db() or $pdo.');
    }
}

function redirect_profile(): void {
    header('Location: profile.php');
    exit;
}

$currentUserId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_partner') {
        if (function_exists('verify_csrf')) {
            try {
                verify_csrf();
            } catch (Throwable $e) {
                redirect_profile();
            }
        }

        $delId = (int)($_POST['id'] ?? 0);
        $table = $_POST['table'] ?? '';
        $allowedTables = ['gv_partners', 'partners'];

        if ($delId > 0 && $currentUserId > 0 && in_array($table, $allowedTables, true)) {
            try {
                $pdo = get_db_pdo();
                // table name already validated via whitelist above
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
            try {
                verify_csrf();
            } catch (Throwable $e) {
                redirect_profile();
            }
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

$user         = [];
$addresses    = [];
$gv_partners  = [];
$partners     = [];

try {
    $pdo = get_db_pdo();

    if ($currentUserId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, phone, created_at, login_type
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare(
            'SELECT id, house_no, landmark, city, pincode, created_at
             FROM addresses
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$currentUserId]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            'SELECT id, gv_partner_id, name, created_at
             FROM gv_partners
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$currentUserId]);
        $gv_partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            'SELECT id, bank_name, partner_id, name, created_at
             FROM partners
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$currentUserId]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('profile.php DB error: ' . $e->getMessage());
}

$ok  = function_exists('flash_get') ? flash_get('ok') : null;
$err = function_exists('flash_get') ? flash_get('error') : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Your Profile</title>
  <link rel="stylesheet" href="public/css/styles.css">
  <link rel="stylesheet" href="public/css/profile.css">
</head>
<body class="profile-page">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
  <section class="profile-section profile-shell">

    <?php if ($ok): ?>
      <div class="flash flash-ok"><?= h($ok) ?></div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="flash flash-error"><?= h($err) ?></div>
    <?php endif; ?>

    <div class="profile-header-row">
      <div class="card profile-hero-card">
        <div class="profile-avatar">
          <?php
          $initial = '';
          if (!empty($user['name'])) {
              $initial = mb_strtoupper(mb_substr($user['name'], 0, 1));
          } elseif (!empty($user['email'])) {
              $initial = mb_strtoupper(mb_substr($user['email'], 0, 1));
          }
          echo h($initial ?: 'U');
          ?>
        </div>
        <div class="profile-hero-meta">
          <div class="profile-name-line">
            <span><?= h($user['name'] ?? 'Your Name') ?></span>
          </div>
          <div class="profile-email-line">
            <?= h($user['email'] ?? '') ?>
          </div>
          <div class="profile-pill-row">
            <?php if (!empty($user['phone'])): ?>
              <span class="pill">
                <span class="icon-dot"></span>
                Phone: <?= h($user['phone']) ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($user['login_type'])): ?>
              <span class="pill login-pill">
                <span class="icon-dot"></span>
                Login: <?= h(strtoupper($user['login_type'])) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card profile-badge-right">
        <div>
          <h2>Account Snapshot</h2>
          <p class="small" style="color:var(--muted);margin-bottom:10px;">
            Quick overview of your account and profile details.
          </p>
        </div>
        <div class="profile-badge-grid">
          <div>
            <div class="profile-badge-label">User ID</div>
            <div class="profile-badge-value">
              #<?= h($user['id'] ?? $currentUserId ?: '-') ?>
            </div>
          </div>
          <div>
            <div class="profile-badge-label">Joined</div>
            <div class="profile-badge-value">
              <?= !empty($user['created_at']) ? h($user['created_at']) : '—' ?>
            </div>
          </div>
          <div>
            <div class="profile-badge-label">Partners Linked</div>
            <div class="profile-badge-value">
              <?= (int)(count($partners) + count($gv_partners)) ?>
            </div>
          </div>
          <div>
            <div class="profile-badge-label">Addresses Saved</div>
            <div class="profile-badge-value">
              <?= (int)count($addresses) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-main-grid">
      <div class="profile-stack">
        <div class="card addresses-block">
          <div class="addresses-header">
            <h3>Saved Addresses</h3>
            <button id="add-address-btn" class="btn btn-primary btn-sm" type="button">
              + Add Address
            </button>
          </div>
          <p class="small" style="color:var(--muted);margin-bottom:8px;">
            Manage your saved shipping addresses. Use Edit to update an address
            or Add Address to save a new one.
          </p>

          <!-- Add-address inline editor host -->
          <div id="add-address-editor"></div>

          <div class="addresses-grid">
            <?php if (!empty($addresses)): ?>
              <?php foreach ($addresses as $addr): ?>
                <div class="address-card"
                     data-row-id="<?= (int)$addr['id'] ?>"
                     data-house_no="<?= h($addr['house_no']) ?>"
                     data-landmark="<?= h($addr['landmark']) ?>"
                     data-city="<?= h($addr['city']) ?>"
                     data-pincode="<?= h($addr['pincode']) ?>">

                  <div class="addr-line">
                    <strong><?= h($addr['house_no']) ?></strong>
                  </div>

                  <?php if (!empty($addr['landmark'])): ?>
                    <div class="addr-line"><?= h($addr['landmark']) ?></div>
                  <?php endif; ?>

                  <div class="addr-line small">
                    <?= h($addr['city']) ?>
                    <?= (!empty($addr['city']) && !empty($addr['pincode'])) ? ' / ' : '' ?>
                    <?= h($addr['pincode']) ?>
                  </div>

                  <div class="addr-meta-small">
                    Added: <?= h($addr['created_at']) ?>
                  </div>

                  <div class="card-actions" style="margin-top:6px;">
                    <button class="btn btn-secondary btn-sm edit-address-btn"
                            type="button"
                            data-id="<?= (int)$addr['id'] ?>"
                            data-house_no="<?= h($addr['house_no']) ?>"
                            data-landmark="<?= h($addr['landmark']) ?>"
                            data-city="<?= h($addr['city']) ?>"
                            data-pincode="<?= h($addr['pincode']) ?>">
                      Edit
                    </button>

                    <form method="post" onsubmit="return confirm('Delete this address?');">
                      <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                      <input type="hidden" name="action" value="delete_address">
                      <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="muted">You have no saved addresses yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="profile-stack">
        <div class="card partners-block">
          <div class="partners-header">
            <h3>Saved Partners</h3>
            <button id="add-partner-btn" class="btn btn-primary btn-sm" type="button">
              Add Partner
            </button>
          </div>
          <p class="small" style="color:var(--muted);margin-bottom:8px;">
            Manage your FASTag partner IDs and GV partner IDs linked to this account.
          </p>

          <div id="add-partner-editor"></div>

          <div class="partners-grid">
            <?php foreach ($gv_partners as $gp): ?>
              <div class="partner-card"
                   data-row-id="<?= (int)$gp['id'] ?>"
                   data-table="gv_partners">
                <div class="partner-title">
                  GV - <?= h($gp['gv_partner_id']) ?>
                  <?= !empty($gp['name']) ? ' — ' . h($gp['name']) : '' ?>
                </div>
                <div class="partner-meta">Saved: <?= h($gp['created_at']) ?></div>
                <div class="card-actions">
                  <button class="btn btn-secondary edit-partner-btn"
                          data-id="<?= (int)$gp['id'] ?>"
                          data-table="gv_partners"
                          data-gv_partner_id="<?= h($gp['gv_partner_id']) ?>"
                          data-name="<?= h($gp['name']) ?>">
                    Edit
                  </button>

                  <form method="post" onsubmit="return confirm('Delete partner?');">
                    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="id" value="<?= (int)$gp['id'] ?>">
                    <input type="hidden" name="table" value="gv_partners">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

            <?php foreach ($partners as $p): ?>
              <div class="partner-card"
                   data-row-id="<?= (int)$p['id'] ?>"
                   data-table="partners">
                <div class="partner-title">
                  <?= h($p['name']) ?> — <?= h($p['partner_id']) ?>
                  <?= !empty($p['bank_name']) ? ' (' . h($p['bank_name']) . ')' : '' ?>
                </div>
                <div class="partner-meta">Saved: <?= h($p['created_at']) ?></div>
                <div class="card-actions">
                  <button class="btn btn-secondary edit-partner-btn"
                          data-id="<?= (int)$p['id'] ?>"
                          data-table="partners"
                          data-bank_name="<?= h($p['bank_name']) ?>"
                          data-partner_id="<?= h($p['partner_id']) ?>"
                          data-name="<?= h($p['name']) ?>">
                    Edit
                  </button>

                  <form method="post" onsubmit="return confirm('Delete partner?');">
                    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="table" value="partners">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($partners) && empty($gv_partners)): ?>
              <p class="muted">No partner IDs saved yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
<script src="public/js/auth-sync.js"></script>
<script src="public/js/script.js"></script>
<script src="public/js/profile.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>