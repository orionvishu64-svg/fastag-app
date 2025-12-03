<?php
// place_order.php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/place_order_errors.log';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);

function log_err($msg) {
    global $logFile;
    @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_exit_err($msg, $code = 500, $detail = null) {
    if ($detail) log_err($detail);
    http_response_code($code);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $msg]);
    exit;
}

function make_txn_ref() {
    return 'TXN' . time() . bin2hex(random_bytes(4));
}

$common = __DIR__ . '/config/common_start.php';
$dbconf = __DIR__ . '/config/db.php';
$adminapi = __DIR__ . '/lib/admin_ship_api.php';

if (!file_exists($common)) json_exit_err('server_error', 500, 'missing common_start.php');
if (!file_exists($dbconf)) json_exit_err('server_error', 500, 'missing db.php');
if (!file_exists($adminapi)) json_exit_err('server_error', 500, 'missing lib/admin_ship_api.php');

require_once $common;
require_once $dbconf;
require_once $adminapi;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_exit_err('server_error', 500, 'PDO not initialized (/config/db.php must define $pdo)');
}

if (!function_exists('admin_api_post')) {
    json_exit_err('server_error', 500, 'admin_api_post function not available in lib/admin_ship_api.php');
}

$raw = trim(file_get_contents('php://input'));
$input = [];
if ($raw !== '') {
    $input = json_decode($raw, true);
    if (!is_array($input) && json_last_error() !== JSON_ERROR_NONE) {
        json_exit_err('invalid_json', 400, 'Invalid JSON body: ' . json_last_error_msg());
    }
}
if (empty($input)) $input = $_POST;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    json_exit_err('not_logged_in', 401, 'User not authenticated');
}
$address_id = (int)($input['address_id'] ?? 0);
$payment_method = trim($input['payment_method'] ?? 'upi');
$items = $input['items'] ?? [];
if (!is_array($items)) $items = [];

if (!$user_id || !$address_id || empty($items)) {
    json_exit_err('missing required fields', 400, 'user_id=' . intval($user_id) . ' address_id=' . intval($address_id) . ' items_count=' . count($items));
}

$calculated_total = 0.0;
foreach ($items as $it) {
    $qty = max(1, (int)($it['quantity'] ?? 1));
    $price = (float)($it['price'] ?? 0.0);
    if ($price < 0) $price = 0.0;
    $calculated_total += $qty * $price;
}
$shipping_amount = 0.0;

$is_agent_payment = in_array(strtolower($payment_method), ['agent-id','agent_id','agent'], true);
$amount_to_store_in_orders = $is_agent_payment ? 0.0 : $calculated_total;

$payment_status = $is_agent_payment ? 'paid' : 'pending';

$transaction_id = isset($input['transaction_id']) && trim($input['transaction_id']) !== '' ? trim($input['transaction_id']) : null;
if (!$transaction_id) {
    $transaction_id = make_txn_ref();
}

if (isset($input['client_total'])) {
    $client_total = (float)$input['client_total'];
    if (abs($client_total - $calculated_total) > 0.01) {
        log_err("Client total mismatch for user {$user_id}: client={$client_total} calc={$calculated_total}");
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
  "INSERT INTO orders (user_id, address_id, amount, shipping_amount, payment_method, payment_status, transaction_id, status, created_at, updated_at)
   VALUES (:uid, :aid, :amt, :ship, :pm, :ps, :tx, :st, NOW(), NOW())"
    );
    $stmt->execute([
        ':uid' => $user_id,
        ':aid' => $address_id,
        ':amt' => $amount_to_store_in_orders,
        ':ship' => $shipping_amount,
        ':pm' => $payment_method,
        ':ps' => $payment_status,
        ':tx' => $transaction_id,
        ':st' => 'created'
    ]);

    $order_id = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_name, bank, quantity, price, product_id) VALUES (:oid, :pname, :bank, :qty, :price, :pid)");
    foreach ($items as $it) {
        $product_name = trim($it['product_name'] ?? ($it['name'] ?? ''));
        $bank_value = $it['bank'] ?? '';
        $qty = max(1, (int)($it['quantity'] ?? 1));
        $price = (float)($it['price'] ?? 0.0);
        if ($price < 0) $price = 0.0;
        $product_id = isset($it['product_id']) ? (int)$it['product_id'] : null;

        $insItem->execute([
            ':oid' => $order_id,
            ':pname' => $product_name,
            ':bank' => $bank_value,
            ':qty' => $qty,
            ':price' => $price,
            ':pid' => $product_id
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    try { $pdo->rollBack(); } catch (Exception $_) {}
    json_exit_err('db_error', 500, 'DB exception: ' . $e->getMessage() . ' trace: ' . $e->getTraceAsString());
}

$admin_create_resp = null;
if ($payment_status === 'paid') {
    try {
        $admin_create_resp = admin_api_post('/api/create_shipment.php', ['order_id' => $order_id]);

        $awb = null;
        if (is_array($admin_create_resp)) {
            if (!empty($admin_create_resp['json']) && is_array($admin_create_resp['json'])) {
                $top = $admin_create_resp['json'];
                $candidates = [
                    $top['awb'] ?? null,
                    $top['data']['awb'] ?? null,
                    $top['resp']['awb'] ?? null,
                    $top['resp']['json']['awb'] ?? null,
                    $top['data']['resp']['json']['awb'] ?? null
                ];
                foreach ($candidates as $cand) {
                    if (!empty($cand)) { $awb = $cand; break; }
                }
            }
        }
        if ($awb) {
            try {
                $u = $pdo->prepare("UPDATE orders SET awb = :awb, updated_at = NOW() WHERE id = :id");
                $u->execute([':awb' => $awb, ':id' => $order_id]);
            } catch (Exception $e) {
                log_err("Failed to save AWB for order {$order_id}: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        log_err("admin_api_post failed for order {$order_id}: " . $e->getMessage());
        $admin_create_resp = ['success' => false, 'error' => $e->getMessage()];
    }
}

echo json_encode([
    'success' => true,
    'status' => 'success',
    'order_id' => $order_id,
    'txnRef' => $transaction_id,
    'admin_create' => $admin_create_resp,
    'calculated_total' => $calculated_total,
    'stored_order_amount' => $amount_to_store_in_orders
]);
exit;