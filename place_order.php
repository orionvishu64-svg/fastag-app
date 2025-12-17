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

if (!file_exists($common)) json_exit_err('server_error', 500, 'missing common_start.php');
if (!file_exists($dbconf)) json_exit_err('server_error', 500, 'missing db.php');

require_once $common;
require_once $dbconf;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_exit_err('server_error', 500, 'PDO not initialized (/config/db.php must define $pdo)');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($headers['X-CSRF-Token'] ?? '');
$raw = trim(file_get_contents('php://input'));
$input = [];
if ($raw !== '') {
    $input = json_decode($raw, true);
    if (!is_array($input) && json_last_error() !== JSON_ERROR_NONE) {
        json_exit_err('invalid_json', 400, 'Invalid JSON body: ' . json_last_error_msg());
    }
} else {
    $input = $_POST;
}
$csrfPayload = isset($input['csrf']) ? (string)$input['csrf'] : '';
$csrf = $csrfHeader ?: $csrfPayload;

if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    json_exit_err('invalid_csrf', 403, 'CSRF validation failed');
}

$user_id = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    json_exit_err('not_logged_in', 401, 'User not authenticated');
}

$address_id = (int)($input['address_id'] ?? 0);
$payment_method = trim((string)($input['payment_method'] ?? 'upi'));
$items = $input['items'] ?? [];
if (!is_array($items)) $items = [];

if (!$address_id || empty($items)) {
    json_exit_err('missing_required_fields', 400, 'address_id or items missing');
}

try {
    $st = $pdo->prepare("SELECT id, pincode FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1");
    $st->execute([':id' => $address_id, ':uid' => $user_id]);
    $addr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$addr) {
        json_exit_err('invalid_address', 403, 'Address does not belong to user');
    }
} catch (Throwable $e) {
    json_exit_err('db_error', 500, 'DB error verifying address: ' . $e->getMessage());
}

$calculated_total = 0.0;
$items_to_insert = [];
$productPriceStmt = $pdo->prepare("SELECT id, price, name FROM products WHERE id = :id LIMIT 1");

foreach ($items as $idx => $it) {
    $qty = max(1, (int)($it['quantity'] ?? ($it['qty'] ?? 1)));
    $client_price = isset($it['price']) ? (float)$it['price'] : null;
    $product_id = isset($it['product_id']) ? (int)$it['product_id'] : 0;
    $product_name = trim($it['product_name'] ?? ($it['name'] ?? ''));

    $unit_price = 0.0;
    if ($product_id > 0) {
        try {
            $productPriceStmt->execute([':id' => $product_id]);
            $prow = $productPriceStmt->fetch(PDO::FETCH_ASSOC);
            if ($prow) {
                $unit_price = (float)$prow['price'];
                if ($product_name === '') $product_name = $prow['name'] ?? $product_name;
            } else {
                log_err("place_order: product_id {$product_id} not found for user {$user_id}");
                $unit_price = $client_price !== null ? $client_price : 0.0;
            }
        } catch (Throwable $e) {
            log_err("place_order product lookup error: " . $e->getMessage());
            $unit_price = $client_price !== null ? $client_price : 0.0;
        }
    } else {
        if ($client_price === null) {
            log_err("place_order: missing product_id and client price at index {$idx} for user {$user_id}");
        }
        $unit_price = $client_price !== null ? (float)$client_price : 0.0;
    }

    if ($unit_price < 0) $unit_price = 0.0;
    $line_total = $unit_price * $qty;
    $calculated_total += $line_total;

    $items_to_insert[] = [
        'product_id' => $product_id > 0 ? $product_id : null,
        'product_name' => $product_name,
        'bank' => $it['bank'] ?? '',
        'quantity' => $qty,
        'unit_price' => $unit_price
    ];
}
$shipping_amount = 0.0;

$is_agent_payment = in_array(strtolower($payment_method), ['agent-id','agent_id','agent'], true);
$amount_to_store_in_orders = $is_agent_payment ? 0.0 : $calculated_total;
$payment_status = $is_agent_payment ? 'paid' : 'pending';

$transaction_id = isset($input['transaction_id']) && trim($input['transaction_id']) !== '' ? trim($input['transaction_id']) : make_txn_ref();

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

$order_code = 'AFT' . date('ymd_Hi') . ($order_id % 10);

$pdo->prepare(
    "UPDATE orders SET order_code = :oc WHERE id = :id"
)->execute([
    ':oc' => $order_code,
    ':id' => $order_id
]);

    $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_name, bank, quantity, price, product_id) VALUES (:oid, :pname, :bank, :qty, :price, :pid)");
    foreach ($items_to_insert as $ii) {
        $insItem->execute([
            ':oid' => $order_id,
            ':pname' => $ii['product_name'],
            ':bank' => $ii['bank'],
            ':qty' => $ii['quantity'],
            ':price' => $ii['unit_price'],
            ':pid' => $ii['product_id']
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $_) {}
    json_exit_err('db_error', 500, 'DB exception: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'status' => 'success',
    'order_id' => $order_id,
    'order_code' => $order_code,
    'txnRef' => $transaction_id,
    'calculated_total' => $calculated_total,
    'stored_order_amount' => $amount_to_store_in_orders
], JSON_UNESCAPED_SLASHES);
exit;