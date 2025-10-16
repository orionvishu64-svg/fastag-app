<?php
// /var/www/html/api/create_return.php
// Create a return request (customer site) and forward to admin for RVP/QC handling.
// - Uses ../config/common_start.php and ../config/db.php
// - Safe, schema-adaptive, transactional, and logs errors (no raw debug to clients)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// include bootstrap + db config (relative paths)
$commonPath = __DIR__ . '/../config/common_start.php';
$dbPath     = __DIR__ . '/../config/db.php';
if (file_exists($commonPath)) require_once $commonPath;
if (file_exists($dbPath))     require_once $dbPath;

/**
 * Ensure helper get_json_input exists (common_start provides it usually)
 */
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $dec = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
    }
}

/**
 * Acquire PDO safely:
 * 1) Use $pdo if db.php created it
 * 2) Try safe_pdo() helper
 * 3) Try common global names ($pdo, $db, $db_conn)
 * 4) Try to build from discovered constants/vars
 */
if (isset($pdo) && $pdo instanceof PDO) {
    // use existing PDO
} else {
    $pdo = null;
    if (function_exists('safe_pdo')) {
        try { $pdo = safe_pdo(); } catch (Throwable $e) { error_log('create_return: safe_pdo failed: ' . $e->getMessage()); }
    }
    if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $pdo = $GLOBALS['pdo'];
    if ($pdo === null && isset($db) && $db instanceof PDO) $pdo = $db;
    if ($pdo === null && isset($db_conn) && $db_conn instanceof PDO) $pdo = $db_conn;

    // gather credentials if available
    $foundHost = defined('DB_HOST') ? DB_HOST : (defined('MYSQL_HOST') ? MYSQL_HOST : null);
    $foundUser = defined('DB_USER') ? DB_USER : (defined('MYSQL_USER') ? MYSQL_USER : null);
    $foundPass = defined('DB_PASS') ? DB_PASS : (defined('MYSQL_PASS') ? MYSQL_PASS : null);
    $foundName = defined('DB_NAME') ? DB_NAME : (defined('MYSQL_DB') ? MYSQL_DB : null);

    if ($pdo === null) {
        if (isset($db_host) && !$foundHost) $foundHost = $db_host;
        if (isset($db_user) && !$foundUser) $foundUser = $db_user;
        if (isset($db_pass) && !$foundPass) $foundPass = $db_pass;
        if (isset($db_name) && !$foundName) $foundName = $db_name;

        if (isset($DB_HOST) && !$foundHost) $foundHost = $DB_HOST;
        if (isset($DB_USER) && !$foundUser) $foundUser = $DB_USER;
        if (isset($DB_PASS) && !$foundPass) $foundPass = $DB_PASS;
        if (isset($DB_NAME) && !$foundName) $foundName = $DB_NAME;
    }

    if ($pdo === null && $foundHost && $foundUser && $foundName) {
        try {
            $dsn = 'mysql:host=' . $foundHost . ';dbname=' . $foundName . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $foundUser, $foundPass ?? '', $options);
        } catch (Throwable $e) {
            error_log('create_return: fallback PDO connect failed: ' . $e->getMessage());
            $pdo = null;
        }
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

// Ensure session (common_start usually started it)
if (session_status() === PHP_SESSION_NONE) session_start();

// Determine current user id
$currentUserId = null;
if (function_exists('get_current_user_id')) {
    try { $currentUserId = get_current_user_id(); } catch (Throwable $e) { $currentUserId = null; }
}
if ($currentUserId === null && !empty($_SESSION['user']['id'])) $currentUserId = (int)$_SESSION['user']['id'];
if ($currentUserId === null && !empty($_SESSION['user_id'])) $currentUserId = (int)$_SESSION['user_id'];

// Fallback from request/fallback policy (controlled by common_start)
$allow_request_fallback = defined('ALLOW_REQUEST_USER_FALLBACK') ? (bool) ALLOW_REQUEST_USER_FALLBACK : true;
if ($currentUserId === null && $allow_request_fallback) {
    // check form / query / request first
    if (!empty($_REQUEST['user_id'])) $currentUserId = (int)$_REQUEST['user_id'];

    // if still null, check JSON body explicitly (safe for testing only if allowed)
    if ($currentUserId === null) {
        $jsonProbe = get_json_input();
        if (!empty($jsonProbe['user_id'])) $currentUserId = (int)$jsonProbe['user_id'];
    }
}

if (empty($currentUserId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

// Read input (JSON preferred)
$input = get_json_input();
if (empty($input)) $input = $_POST ?? [];

// Normalize input params
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : (isset($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0);
$reason   = isset($input['reason']) ? trim((string)$input['reason']) : (isset($_REQUEST['reason']) ? trim((string)$_REQUEST['reason']) : '');
$items    = $input['items'] ?? [];
$notify_admin = isset($input['notify_admin']) ? (bool)$input['notify_admin'] : true;

if (!$order_id || $reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_parameters', 'message' => 'order_id and reason are required']);
    exit;
}

// Verify order and ownership
try {
    $ost = $pdo->prepare("SELECT id, user_id, awb FROM orders WHERE id = :id LIMIT 1");
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
} catch (PDOException $e) {
    error_log('create_return: order lookup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

// Insert into returns and order_tracking — adapt to actual schema at runtime
try {
    $pdo->beginTransaction();

    // discover returns columns
    $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'returns'");
    $colStmt->execute();
    $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $have = array_flip($cols);

    // map data to present columns
    $data = [];
    if (isset($have['order_id']))   $data['order_id'] = $order_id;
    if (isset($have['user_id']))    $data['user_id']  = $currentUserId;
    if (isset($have['reason']))     $data['reason']   = $reason;
    if (isset($have['items_json'])) $data['items_json'] = json_encode($items);
    if (isset($have['status']))     $data['status']   = 'requested';
    if (isset($have['created_at'])) $data['created_at'] = date('Y-m-d H:i:s');
    if (isset($have['updated_at'])) $data['updated_at'] = date('Y-m-d H:i:s');

    if (empty($data)) {
        throw new RuntimeException('returns table has no expected columns to write');
    }

    // build dynamic insert
    $colsList = implode(', ', array_map(function($c){ return "`$c`"; }, array_keys($data)));
    $placeholders = implode(', ', array_map(function($c){ return ':' . $c; }, array_keys($data)));
    $insertSql = "INSERT INTO `returns` ({$colsList}) VALUES ({$placeholders})";
    $ins = $pdo->prepare($insertSql);
    foreach ($data as $k => $v) $ins->bindValue(':' . $k, $v);
    $ins->execute();
    $return_id = (int)$pdo->lastInsertId();

    // insert tracking note: discover order_tracking columns
    $otStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_tracking'");
    $otStmt->execute();
    $ot_cols = $otStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $ot_have = array_flip($ot_cols);

    $trkFields = [];
    $trkParams = [];
    if (isset($ot_have['order_id']))    { $trkFields[]='order_id';    $trkParams['order_id'] = $order_id; }
    if (isset($ot_have['location']))    { $trkFields[]='location';    $trkParams['location'] = null; }
    if (isset($ot_have['event_status'])){ $trkFields[]='event_status';$trkParams['event_status'] = 'Return Requested'; }
    if (isset($ot_have['event']))       { $trkFields[]='event';       $trkParams['event'] = 'RETURN_REQUESTED'; }
    if (isset($ot_have['note']))        { $trkFields[]='note';        $trkParams['note'] = 'Customer requested return: ' . $reason; }
    if (isset($ot_have['occurred_at'])) { $trkFields[]='occurred_at'; $trkParams['occurred_at'] = date('Y-m-d H:i:s'); }
    if (isset($ot_have['updated_at']))  { $trkFields[]='updated_at';  $trkParams['updated_at'] = date('Y-m-d H:i:s'); }

    if (!empty($trkFields)) {
        $colsList2 = implode(', ', array_map(function($c){ return "`$c`"; }, $trkFields));
        $placeholders2 = implode(', ', array_map(function($c){ return ':' . $c; }, $trkFields));
        $tins = $pdo->prepare("INSERT INTO `order_tracking` ({$colsList2}) VALUES ({$placeholders2})");
        foreach ($trkParams as $k => $v) $tins->bindValue(':' . $k, $v);
        $tins->execute();
    } else {
        error_log('create_return: order_tracking table missing expected columns, skipping tracking insert.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_return DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error']);
    exit;
}

// Build payload for admin forward
$payload = [
    'return_id' => $return_id,
    'order_id'  => $order_id,
    'user_id'   => $currentUserId,
    'reason'    => $reason,
    'items'     => $items,
    'awb'       => $order['awb'] ?? null,
    'status'    => 'requested',
    'source'    => 'customer_site'
];

$adminResponse = null;
if ($notify_admin) {
    if (function_exists('admin_api_post')) {
        try {
            $adminResponse = admin_api_post('/api/returns.php', $payload);
        } catch (Throwable $e) {
            error_log('create_return: admin_api_post failed: ' . $e->getMessage());
            $adminResponse = null;
        }
    } else {
        $adminBase = defined('ADMIN_SITE_URL') ? ADMIN_SITE_URL : (getenv('ADMIN_SITE_URL') ?: null);
        if ($adminBase) {
            $url = rtrim($adminBase, '/') . '/api/returns.php';
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($raw === false) {
                    error_log('create_return: curl forward error: ' . curl_error($ch));
                } else {
                    $json = json_decode($raw, true);
                    $adminResponse = ['http_code' => $httpCode, 'json' => $json];
                }
                curl_close($ch);
            } catch (Throwable $e) {
                error_log('create_return: admin forward exception: ' . $e->getMessage());
            }
        } else {
            app_log('create_return: admin forwarding not configured; returns will be handled from DB.');
        }
    }
}

// If admin returned an external AWB, update the returns row (best-effort)
if (is_array($adminResponse) && !empty($adminResponse['json']) && is_array($adminResponse['json'])) {
    $j = $adminResponse['json'];
    $externalAwb = $j['rvp_awb'] ?? ($j['external_awb'] ?? null);
    if (!empty($externalAwb)) {
        try {
            $u = $pdo->prepare("UPDATE `returns` SET external_awb = :awb, status = :status, updated_at = NOW() WHERE id = :id");
            $u->execute([':awb' => $externalAwb, ':status' => 'processing', ':id' => $return_id]);
        } catch (PDOException $e) {
            error_log('create_return: failed to update returns.external_awb: ' . $e->getMessage());
        }
    }
}

// Final response
$response = ['success' => true, 'return_id' => $return_id, 'message' => 'Return requested successfully'];
if ($adminResponse !== null) $response['admin_response'] = $adminResponse;
echo json_encode($response);
exit;
