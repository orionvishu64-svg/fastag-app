<?php
require_once __DIR__ . '/../config/common_start.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$YESBANK_URL = getenv('YESBANK_UPI_URL') ?: 'https://uatskyway.yesbank.in/app/uat';
$CLIENT_ID = getenv('YESBANK_UPI_CLIENT_ID');
$CLIENT_SECRET = getenv('YESBANK_UPI_CLIENT_SECRET');
$MERCHANT_ID = getenv('YESBANK_UPI_MERCHANT_ID');
$MERCHANT_SECRET = getenv('YESBANK_UPI_MERCHANT_SECRET');

function json_exit(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function aes128EncryptHex(string $plainText, string $key): string {
    $encrypted = openssl_encrypt(
        $plainText,
        'AES-128-ECB',
        $key,
        OPENSSL_RAW_DATA
    );
    return strtoupper(bin2hex($encrypted));
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
        p.created_at,
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

if (strtotime($payment['expires_at']) < time()) {
    $pdo->prepare("UPDATE payments SET status='EXPIRED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'EXPIRED']);
}

$message =
    $MERCHANT_ID . '|' .
    $payment['transaction_id'] .
    '||||||||||||NA|NA';

$encryptedMsg = aes128EncryptHex($message, $MERCHANT_SECRET);

$requestBody = json_encode([
    'requestMsg'    => $encryptedMsg,
    'pgMerchantId'  => $MERCHANT_ID
]);

$ch = curl_init($YESBANK_URL . '/upi/meTransStatusQuery');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => [
        'X-IBM-Client-Id: ' . $CLIENT_ID,
        'X-IBM-Client-Secret: ' . $CLIENT_SECRET,
        'Accept: application/json',
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
if ($response === false) {
    error_log("YESBANK CURL ERROR: " . curl_error($ch));
    curl_close($ch);
    json_exit(['status' => 'PENDING']);
}
curl_close($ch);

$response = trim($response);
if ($response === '' || strpos($response, '|') === false) {
    error_log("YESBANK INVALID RESPONSE | token={$token} | raw={$response}");
    json_exit(['status' => 'PENDING']);
}

$values = explode('|', $response);

if (!isset($values[4])) {
    error_log("YESBANK MALFORMED RESPONSE | token={$token} | raw={$response}");
    json_exit(['status' => 'PENDING']);
}

$statusRaw = strtoupper(trim($values[4]));

if (in_array($statusRaw, ['S', 'SUCCESS'], true)) {
    $finalStatus = 'SUCCESS';
} elseif (in_array($statusRaw, ['F', 'FAILED'], true)) {
    $finalStatus = 'FAILED';
} elseif (in_array($statusRaw, ['T', 'TIMEOUT'], true)) {
    $finalStatus = 'EXPIRED';
} else {
    $finalStatus = 'PENDING';
}

error_log("YESBANK RESPONSE | token={$token} | status={$finalStatus} | raw={$response}");

if ($finalStatus === 'SUCCESS') {
    $pdo->prepare("UPDATE payments SET status='SUCCESS' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit([
        'status'     => 'SUCCESS',
        'order_code' => $payment['transaction_id']
    ]);
}

if ($finalStatus === 'FAILED') {
    $pdo->prepare("UPDATE payments SET status='FAILED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'FAILED']);
}

if ($finalStatus === 'EXPIRED') {
    $pdo->prepare("UPDATE payments SET status='EXPIRED' WHERE id=?")
        ->execute([$payment['payment_id']]);

    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")
        ->execute([$payment['order_id']]);

    json_exit(['status' => 'EXPIRED']);
}

json_exit(['status' => 'PENDING']);
