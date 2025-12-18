<?php
require_once __DIR__ . '/../config/common_start.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_exit(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    json_exit(['status' => 'INVALID'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');

if ($token === '') {
    json_exit(['status' => 'INVALID'], 400);
}

$stmt = $pdo->prepare("
    SELECT 
        p.id AS payment_id,
        p.token,
        p.status,
        p.expires_at,
        o.id AS order_id,
        o.transaction_id
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.token = :token
      AND o.user_id = :uid
    LIMIT 1
");
$stmt->execute([
    ':token' => $token,
    ':uid'   => $userId
]);

$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    json_exit(['status' => 'INVALID']);
}

if (($input['action'] ?? '') === 'cancel') {
    $pdo->prepare("UPDATE payments SET status='FAILED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'FAILED']);
}

if (in_array($payment['status'], ['SUCCESS', 'FAILED', 'EXPIRED'], true)) {
    json_exit([
        'status'     => $payment['status'],
        'order_code' => $payment['transaction_id']
    ]);
}

if ($payment['expires_at'] && strtotime($payment['expires_at']) < time()) {
    $pdo->prepare("UPDATE payments SET status='EXPIRED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'EXPIRED']);
}

$apiUrl = 'https://api.gadivan.com/api/upi/status?transaction_id=' .
          urlencode($payment['transaction_id']);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10
]);
$response = curl_exec($ch);

if ($response === false) {
    error_log("SENIOR API CURL ERROR: " . curl_error($ch));
    json_exit(['status' => 'PENDING']);
}

$pdo->prepare("
    UPDATE payments
    SET raw_response = :raw
    WHERE id = :pid
")->execute([
    ':raw' => $response,
    ':pid' => $payment['payment_id']
]);

error_log("SENIOR API RAW RESPONSE: " . $response);

$data = json_decode($response, true);
if (!isset($data['status'][4])) {
    error_log("UPI STATUS INVALID RESPONSE: " . $response);
    json_exit(['status' => 'PENDING']);
}

$statusRaw = strtoupper(trim($data['status'][4]));

if (in_array($statusRaw, ['S', 'SUCCESS'], true)) {

    $pdo->prepare("UPDATE payments SET status='SUCCESS' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit([
        'status'     => 'SUCCESS',
        'order_code' => $payment['transaction_id']
    ]);
}

if (in_array($statusRaw, ['F', 'FAILED'], true)) {
    $pdo->prepare("UPDATE payments SET status='FAILED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'FAILED']);
}

json_exit(['status' => 'PENDING']);
