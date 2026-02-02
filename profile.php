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
<?php include __DIR__ . '/includes/header.php'; ?>
<style>
  /* ===============================
   PROFILE INLINE EDITOR STYLES
================================ */

/* Editor container */
.partner-editor,
.address-editor,
.partner-editor-inline,
.address-editor-inline {
  margin-top: 12px;
  padding: 16px;
  border-radius: 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
}

/* Editor header */
.editor-head h5 {
  font-weight: 600;
  font-size: 1rem;
}

/* Form labels */
.inline-edit-form .form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: #374151;
}

/* Inputs */
.inline-edit-form .form-control {
  border-radius: 8px;
  font-size: 0.9rem;
  padding: 8px 10px;
}

/* Action buttons container */
.editor-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 12px;
}

/* SAVE button */
.btn-save {
  background: linear-gradient(135deg, #0d6efd, #0a58ca);
  border: none;
  color: #fff;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-save:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(13, 110, 253, 0.25);
}

/* CANCEL button */
.btn-cancel {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  color: #374151;
  padding: 8px 16px;
  font-size: 0.85rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-cancel:hover {
  background: #e5e7eb;
}

/* Add buttons (+ Add Partner / Address) */
#add-partner-btn,
#add-address-btn {
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  padding: 6px 14px;
}

/* Partner / Address cards actions */
.card-actions .btn {
  font-size: 0.75rem;
  border-radius: 6px;
  padding: 4px 10px;
}

/* Partner title */
.partner-title {
  font-weight: 600;
  font-size: 0.9rem;
}

/* Sub text */
.partner-meta,
.address-card .text-muted {
  font-size: 0.75rem;
}

</style>
<main class="container py-4">

  <?php if ($ok): ?>
    <div class="alert alert-success fw-semibold"><?= h($ok) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="alert alert-danger fw-semibold"><?= h($err) ?></div>
  <?php endif; ?>

  <!-- ================= PROFILE HEADER ================= -->
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-4">
          <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center"
               style="width:72px;height:72px;font-size:1.6rem;">
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

          <div>
            <h5 class="mb-1 fw-bold"><?= h($user['name'] ?? 'Your Name') ?></h5>
            <div class="text-muted"><?= h($user['email'] ?? '') ?></div>

            <div class="mt-2 d-flex gap-2 flex-wrap">
              <?php if (!empty($user['phone'])): ?>
                <span class="badge bg-light text-dark border">📞 <?= h($user['phone']) ?></span>
              <?php endif; ?>
              <?php if (!empty($user['login_type'])): ?>
                <span class="badge bg-primary-subtle text-primary">
                  🔐 <?= h(strtoupper($user['login_type'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Account Snapshot</h6>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">User ID</span>
            <strong>#<?= h($user['id'] ?? '-') ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Joined</span>
            <strong><?= h($user['created_at'] ?? '—') ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Partners</span>
            <strong><?= count($partners) + count($gv_partners) ?></strong>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Addresses</span>
            <strong><?= count($addresses) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= MAIN CONTENT ================= -->
  <div class="row g-4">
    <!-- ADDRESSES -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Saved Addresses</h6>
            <button id="add-address-btn" class="btn btn-sm btn-primary">+ Add</button>
          </div>
          <p class="text-muted small mb-3">Manage your shipping addresses.</p>
          <div id="add-address-editor"></div>
          <div class="addresses-grid">
            <?php if ($addresses): foreach ($addresses as $addr): ?>
              <div class="address-card border rounded p-3 mb-3"
                   data-row-id="<?= (int)$addr['id'] ?>">

                <strong><?= h($addr['house_no']) ?></strong>
                <?php if ($addr['landmark']): ?>
                  <div><?= h($addr['landmark']) ?></div>
                <?php endif; ?>
                <div class="text-muted small">
                  <?= h($addr['city']) ?> / <?= h($addr['pincode']) ?>
                </div>
                <div class="text-muted small mb-2">
                  Added: <?= h($addr['created_at']) ?>
                </div>

                <div class="card-actions d-flex gap-2">
                  <button class="btn btn-outline-primary btn-sm edit-address-btn"
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
                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; else: ?>
              <p class="text-muted">No addresses saved.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- PARTNERS -->
    <div class="col-lg-6">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Saved Partners</h6>
            <button id="add-partner-btn" class="btn btn-sm btn-primary">+ Add</button>
          </div>
          <p class="text-muted small mb-3">FASTag & GV Partner IDs.</p>
          <div id="add-partner-editor"></div>
          <div class="partners-grid">
            <?php foreach ($gv_partners as $gp): ?>
              <div class="partner-card border rounded p-3 mb-3"
                   data-row-id="<?= (int)$gp['id'] ?>"
                   data-table="gv_partners">

                <div class="partner-title fw-semibold">
                  GV – <?= h($gp['gv_partner_id']) ?>
                  <?= $gp['name'] ? ' — ' . h($gp['name']) : '' ?>
                </div>
                <div class="partner-meta text-muted small mb-2">
                  Saved: <?= h($gp['created_at']) ?>
                </div>

                <div class="card-actions d-flex gap-2">
                  <button class="btn btn-outline-primary btn-sm edit-partner-btn"
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
                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

            <?php foreach ($partners as $p): ?>
              <div class="partner-card border rounded p-3 mb-3"
                   data-row-id="<?= (int)$p['id'] ?>"
                   data-table="partners">

                <div class="partner-title fw-semibold">
                  <?= h($p['name']) ?> — <?= h($p['partner_id']) ?>
                  <?= $p['bank_name'] ? ' (' . h($p['bank_name']) . ')' : '' ?>
                </div>
                <div class="partner-meta text-muted small mb-2">
                  Saved: <?= h($p['created_at']) ?>
                </div>

                <div class="card-actions d-flex gap-2">
                  <button class="btn btn-outline-primary btn-sm edit-partner-btn"
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
                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="public/js/auth-sync.js"></script>
<script src="public/js/script.js"></script>
<script src="public/js/profile.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>