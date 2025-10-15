<?php
// /update_profile.php - JSON API for profile, address, partner updates
declare(strict_types=1);
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// Error handling -> JSON
set_exception_handler(function($e){
    error_log('/update_profile.php uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
});
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// get PDO fallback (keeps original behavior)
if (!function_exists('get_db_pdo')) {
    function get_db_pdo(): PDO {
        if (function_exists('db')) {
            $maybe = db();
            if ($maybe instanceof PDO) return $maybe;
        }
        global $pdo;
        if ($pdo instanceof PDO) return $pdo;

        // Try env variables (optional)
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: null;
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASS') ?: null;

        if ($name && $user !== null) {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo_local = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return $pdo_local;
        }

        throw new RuntimeException('No PDO available. Ensure /db.php provides db() or global $pdo.');
    }
}

// JSON helpers (kept)
function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function json_ok($msg='OK', $data=[]) {
    echo json_encode(array_merge(['success'=>true,'message'=>$msg], $data));
    exit;
}

// Session user
$user_id = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($user_id <= 0) json_err('Not authenticated', 401);

$pdo = get_db_pdo();
$action = trim((string)($_POST['action'] ?? ''));

// optional CSRF check (preserve your existing hook)
if (function_exists('verify_csrf')) {
    try { verify_csrf(); } catch (Throwable $e) { json_err('Invalid request token', 403); }
}

// ---------------------------
// update_partner (edit existing) - uses table param
// ---------------------------
if ($action === 'update_partner') {
    $table = $_POST['table'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $whitelist = [
        'gv_partners' => ['gv_partner_id','name'],
        'partners'    => ['bank_name','partner_id','name'],
    ];
    if (!isset($whitelist[$table]) || $id <= 0) json_err('Invalid request', 400);

    try {
        // verify owner
        $stmt = $pdo->prepare("SELECT user_id FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        $owner = $stmt->fetchColumn();
        if (!$owner || (int)$owner !== $user_id) json_err('Permission denied', 403);

        $set = [];
        $params = [];
        foreach ($whitelist[$table] as $col) {
            if (array_key_exists($col, $_POST)) {
                $val = trim((string)$_POST[$col]);
                // optional: add more validation per column if needed
                $set[] = "`$col` = ?";
                $params[] = ($val === '') ? null : $val;
            }
        }
        if (empty($set)) json_err('No fields to update', 400);
        $set[] = "`updated_at` = NOW()";
        $params[] = $id;

        $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_ok('Partner updated');
    } catch (Throwable $e) {
        error_log('update_profile.update_partner: ' . $e->getMessage());
        json_err('Error updating partner', 500);
    }
}

// ---------------------------
// create_partner (supports 'partners' & 'gv_partners')
// ---------------------------
if ($action === 'create_partner') {
    $table = $_POST['table'] ?? 'partners';
    if (!in_array($table, ['partners', 'gv_partners'], true)) json_err('Invalid table', 400);

    try {
        if ($table === 'gv_partners') {
            $gv_partner_id = trim((string)($_POST['gv_partner_id'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            if ($gv_partner_id === '') json_err('GV Partner ID required', 400);
            if (!preg_match('/^[A-Za-z0-9\-_]+$/', $gv_partner_id)) json_err('GV Partner ID invalid', 400);

            $stmt = $pdo->prepare("INSERT INTO gv_partners (user_id, gv_partner_id, name, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $gv_partner_id, $name !== '' ? $name : null]);
            $newId = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare("SELECT id, gv_partner_id, name, created_at FROM gv_partners WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt2->execute([$newId, $user_id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            json_ok('GV Partner added', ['partner' => $row]);
        } else {
            $bank_name = trim((string)($_POST['bank_name'] ?? ''));
            $partner_id = trim((string)($_POST['partner_id'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            if ($bank_name === '' || $partner_id === '') json_err('Bank name and Partner ID are required', 400);

            if (mb_strlen($bank_name) > 100 || mb_strlen($partner_id) > 100) json_err('Input too long', 400);

            $stmt = $pdo->prepare("INSERT INTO partners (user_id, bank_name, partner_id, name, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $bank_name, $partner_id, $name !== '' ? $name : null]);
            $newId = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare("SELECT id, bank_name, partner_id, name, created_at FROM partners WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt2->execute([$newId, $user_id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            json_ok('Partner added', ['partner' => $row]);
        }
    } catch (Throwable $e) {
        error_log('update_profile.create_partner: ' . $e->getMessage());
        json_err('Error adding partner', 500);
    }
}

// ---------------------------
// profile_update (update user name + phone only — email not changed)
// ---------------------------
if ($action === 'profile_update') {
    $raw_name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($raw_name === '') json_err('Name is required', 400);
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) json_err('Invalid phone', 400);

    try {
        $parts = [];
        $params = [];
        $parts[] = "name = ?";
        $params[] = $raw_name;
        if ($phone !== '') { $parts[] = "phone = ?"; $params[] = $phone; }
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $parts) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_ok('Profile updated', ['reload' => true]);
    } catch (Throwable $e) {
        error_log('update_profile.profile_update: ' . $e->getMessage());
        json_err('Server error updating profile', 500);
    }
}

// ---------------------------
// save_address (insert/update)
// ---------------------------
if ($action === 'save_address') {
    $house_no = trim((string)($_POST['house_no'] ?? ''));
    $landmark = trim((string)($_POST['landmark'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $pincode = trim((string)($_POST['pincode'] ?? ''));
    if ($city === '' || $pincode === '') json_err('City & Pincode required', 400);
    if (!preg_match('/^\d{4,8}$/', $pincode)) json_err('Invalid pincode format', 400);

    try {
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE addresses SET house_no = ?, landmark = ?, city = ?, pincode = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$house_no ?: null, $landmark ?: null, $city, $pincode, (int)$existing, $user_id]);
            json_ok('Address updated');
        } else {
            $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $house_no ?: null, $landmark ?: null, $city, $pincode]);
            json_ok('Address saved');
        }
    } catch (Throwable $e) {
        error_log('update_profile.save_address: ' . $e->getMessage());
        json_err('Error saving address', 500);
    }
}

// Falls through: invalid action
json_err('Unknown action', 400);
