<?php
// update_profile.php — unified JSON API with robust PDO fallback and JSON error responses
declare(strict_types=1);

require_once 'common_start.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure session
if (session_status() === PHP_SESSION_NONE) session_start();

// Global JSON error handler so client always receives JSON
set_exception_handler(function ($e) {
    error_log('update_profile.php uncaught exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (exception).']);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    // convert PHP warnings/notices to exceptions for consistent handling
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Fallback get_db_pdo() if not defined by your project
if (!function_exists('get_db_pdo')) {
    function get_db_pdo(): PDO {
        // If function db() exists, prefer it
        if (function_exists('db')) {
            $maybe = db();
            if ($maybe instanceof PDO) return $maybe;
        }
        // If global $pdo exists, use it
        global $pdo;
        if ($pdo instanceof PDO) return $pdo;

        // Try to build PDO from common environment variables (optional)
        $host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: null;
        $user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: null;
        $pass = getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: null;

        if ($name && $user !== null) {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            try {
                $pdo_local = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                return $pdo_local;
            } catch (Throwable $e) {
                error_log("get_db_pdo auto-create failed: " . $e->getMessage());
            }
        }

        // Last resort: throw — the exception handler will return JSON
        throw new RuntimeException('No PDO available. Ensure db.php exposes db() or $pdo or set DB_* env vars.');
    }
}

// helper JSON functions
function json_err(string $msg, int $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function json_ok(string $msg = 'OK', array $data = []) {
    echo json_encode(array_merge(['success' => true, 'message' => $msg], $data));
    exit;
}

// Ensure user is logged in (use existing session patterns)
$user_id = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    json_err('User not logged in', 401);
}

$pdo = get_db_pdo();

// read action
$action = trim((string)($_POST['action'] ?? ''));

// --------- update partner branch ----------
if ($action === 'update_partner') {
    $table = $_POST['table'] ?? '';
    $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    $whitelist = [
        'gv_partners' => ['gv_partner_id', 'name'],
        'partners'    => ['bank_name', 'partner_id', 'name'],
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
            if (isset($_POST[$col])) {
                $val = trim((string)$_POST[$col]);
                $set[] = "`$col` = ?";
                $params[] = $val;
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
        error_log('update_profile.php update_partner error: ' . $e->getMessage());
        json_err('Error updating partner', 500);
    }
}

// --------- profile/address update branch ----------
$raw_name  = trim((string)($_POST['name'] ?? ''));
$firstName = trim((string)($_POST['firstName'] ?? ''));
$lastName  = trim((string)($_POST['lastName'] ?? ''));
$email     = trim((string)($_POST['email'] ?? ''));
$phone     = trim((string)($_POST['phone'] ?? ''));
$house_no  = trim((string)($_POST['house_no'] ?? $_POST['houseNo'] ?? ''));
$landmark  = trim((string)($_POST['landmark'] ?? ''));
$city      = trim((string)($_POST['city'] ?? ''));
$pincode   = trim((string)($_POST['pincode'] ?? $_POST['pin'] ?? ''));

$final_name = $raw_name !== '' ? $raw_name : trim(($firstName . ' ' . $lastName));

if ($final_name === '') json_err('Name is required', 400);
if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) json_err('Invalid phone number', 400);

try {
    // Update users table
    $updateParts = [];
    $params = [];
    if ($final_name !== '') { $updateParts[] = "name = ?"; $params[] = $final_name; }
    if ($email !== '') { $updateParts[] = "email = ?"; $params[] = $email; }
    if ($phone !== '') { $updateParts[] = "phone = ?"; $params[] = $phone; }

    if (!empty($updateParts)) {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(", ", $updateParts) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // Update/insert addresses if provided
    $hasAddress = ($house_no !== '' || $landmark !== '' || $city !== '' || $pincode !== '');
    if ($hasAddress) {
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $addr = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($addr) {
            $stmt = $pdo->prepare("UPDATE addresses SET house_no = ?, landmark = ?, city = ?, pincode = ?, created_at = created_at WHERE id = ? AND user_id = ?");
            $stmt->execute([$house_no ?: null, $landmark ?: null, $city, $pincode, (int)$addr['id'], $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $house_no ?: null, $landmark ?: null, $city, $pincode]);
        }
    }

    json_ok('Profile updated', ['reload' => true]);
} catch (Throwable $e) {
    error_log('update_profile.php profile update error: ' . $e->getMessage());
    json_err('Server error updating profile', 500);
}
