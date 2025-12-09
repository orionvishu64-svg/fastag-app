<?php
// /api/create_return.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';

if (file_exists($commonPath)) require_once $commonPath;
if (file_exists($dbPath)) require_once $dbPath;

if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) { error_log('create_return: safe_pdo failed: ' . $e->getMessage()); }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $pdo = $GLOBALS['pdo'];
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = null;
if (function_exists('get_current_user_id')) {
    try { $currentUserId = get_current_user_id(); } catch (Throwable $e) { $currentUserId = null; }
}
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

$allow_request_fallback = defined('ALLOW_REQUEST_USER_FALLBACK') ? (bool) ALLOW_REQUEST_USER_FALLBACK : true;
$rawJson = get_json_input();
if ($currentUserId === null && $allow_request_fallback) {
    if (!empty($_REQUEST['user_id'])) $currentUserId = (int)$_REQUEST['user_id'];
    if ($currentUserId === null && !empty($rawJson['user_id'])) $currentUserId = (int)$rawJson['user_id'];
}

if (empty($currentUserId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$input = $rawJson;
if (empty($input)) {
    $input = $_POST ?? [];
}

$csrfProvided = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
if (empty($csrfProvided) || empty($_SESSION['return_csrf_token']) || !hash_equals($_SESSION['return_csrf_token'], (string)$csrfProvided)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'invalid_csrf']);
    exit;
}

// Read fields
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$reason = isset($input['reason']) ? trim((string)$input['reason']) : '';
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
$external_awb = isset($input['external_awb']) ? trim((string)$input['external_awb']) : null;
$notify_admin = isset($input['notify_admin']) ? (bool)$input['notify_admin'] : true;

if ($order_id <= 0 || $reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_parameters', 'message' => 'order_id and reason are required']);
    exit;
}
if (mb_strlen($reason) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'reason_too_long']);
    exit;
}

try {
    $rl = $pdo->prepare("SELECT COUNT(*) FROM returns WHERE user_id = :uid AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $rl->execute([':uid' => $currentUserId]);
    $countLastHour = (int)$rl->fetchColumn();
    if ($countLastHour >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'rate_limited', 'message'=>'Too many return requests. Try later.']);
        exit;
    }
} catch (Throwable $e) {
    error_log('create_return rate-limit check failed: ' . $e->getMessage());
}

try {
    $ost = $pdo->prepare("SELECT id, user_id, awb, address_id FROM orders WHERE id = :id LIMIT 1");
    $ost->execute([':id' => $order_id]);
    $order = $ost->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'order_not_found']);
        exit;
    }
    
    if ((int)$order['user_id'] !== (int)$currentUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'not_owner']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log('create_return order lookup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

$cleanItems = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    $cleanItems[] = [
        'product_name' => isset($it['product_name']) ? (string)$it['product_name'] : (isset($it['name']) ? (string)$it['name'] : ''),
        'quantity' => isset($it['quantity']) ? (int)$it['quantity'] : 1,
        'price' => isset($it['price']) ? (float)$it['price'] : 0.0,
        'product_id' => isset($it['product_id']) ? (int)$it['product_id'] : null,
        'bank' => isset($it['bank']) ? (string)$it['bank'] : ''
    ];
}

$return_id = null;
try {
    $pdo->beginTransaction();

    $inserted = false;
    try {
        $ins = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, external_awb, status, created_at, updated_at) VALUES (:order_id, :user_id, :reason, :awb, :status, NOW(), NOW())");
        $ins->execute([
            ':order_id' => $order_id,
            ':user_id' => $currentUserId,
            ':reason' => $reason,
            ':awb' => $external_awb,
            ':status' => 'requested'
        ]);
        $inserted = true;
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'unknown column') !== false || stripos($e->getMessage(), 'column') !== false) {
            $ins2 = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, status, created_at, updated_at) VALUES (:order_id, :user_id, :reason, :status, NOW(), NOW())");
            $ins2->execute([
                ':order_id' => $order_id,
                ':user_id' => $currentUserId,
                ':reason' => $reason,
                ':status' => 'requested'
            ]);
            $inserted = true;
        } else {
            throw $e;
        }
    }

    if (!$inserted) throw new RuntimeException('insert_failed');

    $return_id = (int)$pdo->lastInsertId();

    $note = 'Customer requested return' . ($external_awb ? (': external_awb=' . $external_awb) : '') . ' - ' . mb_substr($reason, 0, 1000);
    $tstmt = $pdo->prepare("INSERT INTO order_tracking (order_id, location, event_status, event, note, event_source, occurred_at, updated_at) VALUES (:oid, :loc, :status, :event, :note, :source, NOW(), NOW())");
    $locHint = null;
    try {
        if (!empty($order['address_id'])) {
            $as = $pdo->prepare("SELECT city, pincode FROM addresses WHERE id = :aid LIMIT 1");
            $as->execute([':aid' => (int)$order['address_id']]);
            $ar = $as->fetch(PDO::FETCH_ASSOC);
            if ($ar) {
                $locHint = trim((string)($ar['city'] ?? '') . ' ' . (string)($ar['pincode'] ?? ''));
                if ($locHint === '') $locHint = null;
            }
        }
    } catch (Throwable $e) {
        $locHint = null;
    }

    $tstmt->execute([
        ':oid' => $order_id,
        ':loc' => $locHint,
        ':status' => 'Return Requested',
        ':event' => 'RETURN_REQUESTED',
        ':note' => $note,
        ':source' => 'system'
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_return DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
    exit;
}

$adminResponse = null;
$payload = [
    'return_id' => $return_id,
    'order_id' => $order_id,
    'user_id' => $currentUserId,
    'reason' => $reason,
    'items' => $cleanItems,
    'awb' => $order['awb'] ?? null,
    'external_awb' => $external_awb,
    'status' => 'requested',
    'source' => 'customer_site'
];

if ($notify_admin) {
    try {
        if (function_exists('admin_api_post')) {
            $adminResponse = admin_api_post('/api/returns.php', $payload);
        } else {
            $adminBase = defined('ADMIN_SITE_URL') ? ADMIN_SITE_URL : (getenv('ADMIN_SITE_URL') ?: null);
            if ($adminBase) {
                $url = rtrim($adminBase, '/') . '/api/returns.php';
                $headers = ['Content-Type: application/json'];
                if (defined('ADMIN_API_KEY') && ADMIN_API_KEY) $headers[] = 'Authorization: Bearer ' . ADMIN_API_KEY;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
                curl_setopt($ch, CURLOPT_TIMEOUT, defined('ADMIN_API_TIMEOUT') ? ADMIN_API_TIMEOUT : 8);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, defined('ADMIN_API_CONNECT_TIMEOUT') ? ADMIN_API_CONNECT_TIMEOUT : 4);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($raw === false) {
                    $adminResponse = ['http' => $httpCode, 'raw' => false, 'error' => curl_error($ch)];
                } else {
                    $json = json_decode($raw, true);
                    $adminResponse = ['http' => $httpCode, 'json' => $json, 'raw' => $raw];
                }
                curl_close($ch);
            }
        }
    } catch (Throwable $e) {
        error_log('create_return: admin forwarding failed: ' . $e->getMessage());
        $adminResponse = ['success' => false, 'error' => $e->getMessage()];
    }
}

if (is_array($adminResponse) && !empty($adminResponse['json']) && is_array($adminResponse['json'])) {
    $j = $adminResponse['json'];
    $externalAwbFromAdmin = $j['rvp_awb'] ?? ($j['external_awb'] ?? null) ?? null;
    if (!empty($externalAwbFromAdmin)) {
        try {
            $u = $pdo->prepare("UPDATE returns SET external_awb = :awb, status = :status, updated_at = NOW() WHERE id = :id");
            $u->execute([':awb' => $externalAwbFromAdmin, ':status' => 'processing', ':id' => $return_id]);
        } catch (Throwable $e) {
            error_log('create_return: failed to save external_awb from admin: ' . $e->getMessage());
        }
    }
}

$out = ['success' => true, 'return_id' => $return_id, 'message' => 'Return requested successfully'];
if ($adminResponse !== null) $out['admin_response'] = $adminResponse;
echo json_encode($out, JSON_UNESCAPED_SLASHES);
exit;