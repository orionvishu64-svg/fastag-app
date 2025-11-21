<?php
// place_order.php - defensive, drop-in replacement
header('Content-Type: application/json; charset=utf-8');

// Simple logging for debugging (make sure logs dir exists and writable)
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/place_order_errors.log';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);

function log_err($msg) {
    global $logFile;
    @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Fail-fast helper: returns JSON and logs
function json_exit_err($msg, $code = 500, $detail = null) {
    if ($detail) log_err($detail);
    http_response_code($code);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $msg]);
    exit;
}

// --- includes and sanity checks ---
$common = __DIR__ . '/config/common_start.php';
$dbconf = __DIR__ . '/config/db.php';
$adminapi = __DIR__ . '/lib/admin_ship_api.php';

if (!file_exists($common)) json_exit_err('server_error', 500, 'missing common_start.php');
if (!file_exists($dbconf)) json_exit_err('server_error', 500, 'missing db.php');
if (!file_exists($adminapi)) json_exit_err('server_error', 500, 'missing lib/admin_ship_api.php');

require_once $common;
require_once $dbconf;
require_once $adminapi;

// verify $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_exit_err('server_error', 500, 'PDO not initialized (/config/db.php must define $pdo)');
}

// verify admin_api_post function exists (used later)
if (!function_exists('admin_api_post')) {
    // if the lib uses a different function name, attempt to find it
    json_exit_err('server_error', 500, 'admin_api_post function not available in lib/admin_ship_api.php');
}

// read JSON body or fallback to POST form
$raw = trim(file_get_contents('php://input'));
$input = [];
if ($raw !== '') {
    $input = json_decode($raw, true);
    if (!is_array($input) && json_last_error() !== JSON_ERROR_NONE) {
        json_exit_err('invalid_json', 400, 'Invalid JSON body: ' . json_last_error_msg());
    }
}
if (empty($input)) $input = $_POST;

// --- get authenticated user ---
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

// compute total server-side
$total = 0.0;
foreach ($items as $it) {
    $qty = max(1, (int)($it['quantity'] ?? 1));
    $price = (float)($it['price'] ?? 0.0);
    $total += $qty * $price;
}
$shipping_amount = 0.0;

// payment status
$payment_status = 'pending';
$transaction_id = $input['transaction_id'] ?? null;
if (in_array($payment_method, ['agent-id','agent_id','agent'])) {
    $payment_status = 'paid';
}

// Insert order + items in a transaction
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
  "INSERT INTO orders (user_id, address_id, amount, shipping_amount, payment_method, payment_status, transaction_id, status, created_at, updated_at)
   VALUES (:uid, :aid, :amt, :ship, :pm, :ps, :tx, :st, NOW(), NOW())"
);
$stmt->execute([
    ':uid' => $user_id,
    ':aid' => $address_id,
    ':amt' => $total,
    ':ship' => $shipping_amount,
    ':pm' => $payment_method,
    ':ps' => $payment_status,
    ':tx' => $transaction_id,
    ':st' => 'created'
]);

    $order_id = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_name, bank, quantity, price) VALUES (:oid, :pname, :bank, :qty, :price)");
    foreach ($items as $it) {
        $insItem->execute([
            ':oid' => $order_id,
            ':pname' => $it['product_name'] ?? ($it['name'] ?? ''),
            ':bank' => $it['bank'] ?? '',
            ':qty' => max(1, (int)($it['quantity'] ?? 1)),
            ':price' => (float)($it['price'] ?? 0.0)
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_exit_err('db_error', 500, 'DB exception: ' . $e->getMessage() . ' trace: ' . $e->getTraceAsString());
}

// If already paid (agent), call admin create_shipment
$admin_create_resp = null;
if ($payment_status === 'paid') {
    try {
        $admin_create_resp = admin_api_post('/api/create_shipment.php', ['order_id' => $order_id]);

        // admin_api_post may return structured data in different shapes; handle robustly
        $awb = null;
        if (is_array($admin_create_resp)) {
            // try common shapes
            if (!empty($admin_create_resp['json']) && is_array($admin_create_resp['json'])) {
                // sometimes admin returns { success:true, data:{...} } or nested shapes
                $top = $admin_create_resp['json'];
                // try obvious locations:
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
                // log but do not fail
                log_err("Failed to save AWB for order {$order_id}: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // log admin call failure; still return the order creation success to client
        log_err("admin_api_post failed for order {$order_id}: " . $e->getMessage());
        $admin_create_resp = ['success' => false, 'error' => $e->getMessage()];
    }
}

// Always return JSON — never empty
echo json_encode([
    'success' => true,
    'status' => 'success',
    'order_id' => $order_id,
    'admin_create' => $admin_create_resp
]);
exit;
