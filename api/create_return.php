<?php
// api/create_return.php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../logs';
@mkdir($logDir, 0750, true);
$logFile = $logDir . '/create_return.log';
function log_msg($s){ global $logFile; @file_put_contents($logFile, date('c') . ' ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX); }

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_authenticated', 'message' => 'You must be logged in to create a return.']);
    exit;
}

$raw = trim(file_get_contents('php://input'));
$input = [];
if ($raw !== '') {
    $input = json_decode($raw, true);
    if (!is_array($input) && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'invalid_json','message'=>json_last_error_msg()]);
        exit;
    }
} else {
    $input = $_POST;
}

$orderId = (int)($input['order_id'] ?? 0);
$reason = trim((string)($input['reason'] ?? ''));
$externalAwb = trim((string)($input['external_awb'] ?? ''));

if ($orderId <= 0 || $reason === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'missing_fields','message'=>'order_id and reason are required.']);
    exit;
}

$dbconf = __DIR__ . '/../config/db.php';
if (!file_exists($dbconf)) {
    log_msg("db.php missing when creating return");
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server_error','message'=>'Server misconfiguration.']);
    exit;
}
require_once $dbconf;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    log_msg("PDO not initialized in create_return");
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server_error','message'=>'DB not available.']);
    exit;
}

try {
    $s = $pdo->prepare("SELECT id,user_id,status FROM orders WHERE id = :id LIMIT 1");
    $s->execute([':id' => $orderId]);
    $ord = $s->fetch(PDO::FETCH_ASSOC);
    if (!$ord) {
        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>'order_not_found','message'=>'Order not found.']);
        exit;
    }
    if ((int)$ord['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>'forbidden','message'=>'You do not own this order.']);
        exit;
    }
} catch (Throwable $e) {
    log_msg("order lookup failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server_error','message'=>'DB query failed.']);
    exit;
}

try {
    $ins = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, external_awb, status, created_at, updated_at) VALUES (:oid, :uid, :reason, :awb, :status, NOW(), NOW())");
    $ins->execute([
        ':oid' => $orderId,
        ':uid' => $userId,
        ':reason' => $reason,
        ':awb' => $externalAwb ?: null,
        ':status' => 'requested'
    ]);
    $returnId = (int)$pdo->lastInsertId();

    echo json_encode(['success'=>true, 'return_id' => $returnId, 'message' => 'Return request created.']);
    exit;
} catch (Throwable $e) {
    log_msg("create_return db error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'db_error','message'=>'Failed to create return.']);
    exit;
}