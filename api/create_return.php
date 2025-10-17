<?php
// /var/www/html/api/create_return.php
// Creates a local return entry and forwards it to the admin site for RVP/QC handling.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// include project bootstrap and DB config (relative to /var/www/html/api)
$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (file_exists($dbPath)) require_once $dbPath;

// helper: safe get_json_input (common_start may provide it)
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

// Acquire PDO safely
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) { error_log('create_return: safe_pdo failed: ' . $e->getMessage()); }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // try globals that may have been set in db.php
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

// Ensure session started
if (session_status() === PHP_SESSION_NONE) session_start();

// Identify current user (respect common_start helpers if present)
$currentUserId = null;
if (function_exists('get_current_user_id')) {
    try { $currentUserId = get_current_user_id(); } catch (Throwable $e) { $currentUserId = null; }
}
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

// testing fallback (disable in production by setting ALLOW_REQUEST_USER_FALLBACK = false)
$allow_request_fallback = defined('ALLOW_REQUEST_USER_FALLBACK') ? (bool) ALLOW_REQUEST_USER_FALLBACK : true;
if ($currentUserId === null && $allow_request_fallback) {
    if (!empty($_REQUEST['user_id'])) $currentUserId = (int)$_REQUEST['user_id'];
    $jsonProbe = get_json_input();
    if ($currentUserId === null && !empty($jsonProbe['user_id'])) $currentUserId = (int)$jsonProbe['user_id'];
}

if (empty($currentUserId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

// Read input
$input = get_json_input();
if (!$input) $input = $_POST ?? [];

$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$reason = isset($input['reason']) ? trim((string)$input['reason']) : '';
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
$notify_admin = isset($input['notify_admin']) ? (bool)$input['notify_admin'] : true;

if (!$order_id || $reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_parameters', 'message' => 'order_id and reason are required']);
    exit;
}

try {
    // Verify order exists and belongs to user
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

    // Begin transaction
    $pdo->beginTransaction();

    // Insert into returns table (matches your existing schema)
    $ins = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, status, created_at, updated_at) VALUES (:order_id, :user_id, :reason, :status, NOW(), NOW())");
    $ins->execute([
        ':order_id' => $order_id,
        ':user_id' => $currentUserId,
        ':reason' => $reason,
        ':status' => 'requested'
    ]);
    $return_id = (int)$pdo->lastInsertId();

    // Build tracking system event (event_source = 'system')
    $event_status = 'Return Requested';
    $event = 'RETURN_REQUESTED';
    $note = 'Customer requested return: ' . $reason;
    $event_source = 'system';

    // Optional: buyer location hint (city + pincode) — safe fallback; NULL if not available
    $locHint = null;
    try {
        if (!empty($order['address_id'])) {
            $addrStmt = $pdo->prepare("SELECT city, pincode FROM addresses WHERE id = :aid LIMIT 1");
            $addrStmt->execute([':aid' => (int)$order['address_id']]);
            $addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC);
            if ($addrRow) {
                $city = trim((string)($addrRow['city'] ?? ''));
                $pin = trim((string)($addrRow['pincode'] ?? ''));
                $locHint = trim(($city ? $city : '') . ($pin ? ' ' . $pin : ''));
                if ($locHint === '') $locHint = null;
            }
        }
    } catch (Throwable $e) {
        // ignore location hint errors
        $locHint = null;
    }

    // Insert order_tracking row
    $tstmt = $pdo->prepare(
        "INSERT INTO order_tracking
           (order_id, location, event_status, event, note, event_source, occurred_at, updated_at)
         VALUES
           (:oid, :loc, :status, :event, :note, :source, NOW(), NOW())"
    );
    $tstmt->execute([
        ':oid' => $order_id,
        ':loc'  => $locHint, // null when not available
        ':status' => $event_status,
        ':event' => $event,
        ':note' => $note,
        ':source' => $event_source
    ]);

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_return DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_return unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
    exit;
}

// Build payload to forward to admin if configured
$payload = [
    'return_id' => $return_id,
    'order_id' => $order_id,
    'user_id' => $currentUserId,
    'reason' => $reason,
    'items' => $items,
    'awb' => $order['awb'] ?? null,
    'status' => 'requested',
    'source' => 'customer_site'
];

$adminResponse = null;
if ($notify_admin) {
    if (function_exists('admin_api_post')) {
        try {
            // admin_api_post expected to return array with keys 'http_code' and 'json' or throw
            $adminResponse = admin_api_post('/api/returns.php', $payload);
        } catch (Throwable $e) {
            error_log('create_return: admin_api_post failed: ' . $e->getMessage());
            $adminResponse = null;
        }
    } else {
        // fallback to ADMIN_SITE_URL + ADMIN_API_KEY
        $adminBase = defined('ADMIN_SITE_URL') ? ADMIN_SITE_URL : (getenv('ADMIN_SITE_URL') ?: null);
        if ($adminBase) {
            $url = rtrim($adminBase, '/') . '/api/returns.php';
            try {
                $headers = ['Content-Type: application/json'];
                if (defined('ADMIN_API_KEY') && ADMIN_API_KEY) {
                    $headers[] = 'Authorization: Bearer ' . ADMIN_API_KEY;
                }
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, defined('ADMIN_API_TIMEOUT') ? ADMIN_API_TIMEOUT : 8);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, defined('ADMIN_API_CONNECT_TIMEOUT') ? ADMIN_API_CONNECT_TIMEOUT : 4);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($raw === false) {
                    error_log('create_return: curl error while forwarding to admin: ' . curl_error($ch));
                } else {
                    $json = json_decode($raw, true);
                    $adminResponse = ['http_code' => $httpCode, 'json' => $json, 'raw' => $raw];
                }
                curl_close($ch);
            } catch (Throwable $e) {
                error_log('create_return: admin curl exception: ' . $e->getMessage());
            }
        } else {
            // Not configured — admin will pick up returns from DB
            if (function_exists('app_log')) {
                app_log('create_return: admin forwarding not configured (ADMIN_SITE_URL or admin_api_post missing)');
            } else {
                error_log('create_return: admin forwarding not configured');
            }
        }
    }
}

// If admin returned an external AWB, update returns row (best-effort)
if (is_array($adminResponse) && !empty($adminResponse['json']) && is_array($adminResponse['json'])) {
    $j = $adminResponse['json'];
    $externalAwb = $j['rvp_awb'] ?? ($j['external_awb'] ?? null);
    if (!empty($externalAwb)) {
        try {
            $u = $pdo->prepare("UPDATE returns SET external_awb = :awb, status = :status, updated_at = NOW() WHERE id = :id");
            $u->execute([':awb' => $externalAwb, ':status' => 'processing', ':id' => $return_id]);
        } catch (PDOException $e) {
            error_log('create_return: failed to update returns.external_awb: ' . $e->getMessage());
        }
    }
}

// Success response
$response = ['success' => true, 'return_id' => $return_id, 'message' => 'Return requested successfully'];
if ($adminResponse !== null) $response['admin_response'] = $adminResponse;
echo json_encode($response, JSON_UNESCAPED_SLASHES);
exit;
