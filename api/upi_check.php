<?php
require_once __DIR__ . '/../config/common_start.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$YESBANK_URL = getenv('YESBANK_UPI_URL') ?: 'https://uatskyway.yesbank.in/app/uat';
$CLIENT_ID = getenv('YESBANK_UPI_CLIENT_ID');
$CLIENT_SECRET = getenv('YESBANK_UPI_CLIENT_SECRET');
$MERCHANT_ID = getenv('YESBANK_UPI_MERCHANT_ID');
$MERCHANT_SECRET = getenv('YESBANK_UPI_MERCHANT_SECRET');

$MAX_WAIT_SECONDS = 300;

function json_exit(array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data);
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

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? null;

if (!$token) {
    json_exit(['status' => 'INVALID']);
}

$stmt = $pdo->prepare("
    SELECT p.*, o.id AS order_id, o.transaction_id
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.token = :token
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    json_exit(['status' => 'INVALID']);
}

if (!empty($input['action']) && $input['action'] === 'cancel') {
    $pdo->prepare("UPDATE payments SET status='FAILED' WHERE token=?")->execute([$token]);
    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")->execute([$payment['order_id']]);
    json_exit(['status' => 'FAILED']);
}

if (in_array($payment['status'], ['SUCCESS', 'FAILED', 'EXPIRED'], true)) {
    json_exit([
        'status' => $payment['status'],
        'order_code' => $payment['transaction_id']
    ]);
}

$age = time() - strtotime($payment['created_at']);
if ($age > $MAX_WAIT_SECONDS) {
    $pdo->prepare("UPDATE payments SET status='EXPIRED' WHERE token=?")->execute([$token]);
    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")->execute([$payment['order_id']]);
    json_exit(['status' => 'EXPIRED']);
}

$message =
    $MERCHANT_ID . '|' .
    $payment['transaction_id'] .
    '||||||||||||NA|NA';

$encryptedMsg = aes128EncryptHex($message, $MERCHANT_SECRET);

$requestBody = json_encode([
    'requestMsg' => $encryptedMsg,
    'pgMerchantId' => $MERCHANT_ID
]);

$ch = curl_init($YESBANK_URL . '/upi/meTransStatusQuery');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_HTTPHEADER => [
        'X-IBM-Client-Id: ' . $CLIENT_ID,
        'X-IBM-Client-Secret: ' . $CLIENT_SECRET,
        'Accept: application/json',
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    json_exit(['status' => 'PENDING']);
}

$values = explode('|', trim($response));
$status = strtoupper($values[4] ?? 'PENDING');

if ($status === 'SUCCESS' || $status === 'S') {

    $pdo->prepare("UPDATE payments SET status='SUCCESS' WHERE token=?")->execute([$token]);
    $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")->execute([$payment['order_id']]);

    json_exit([
        'status' => 'SUCCESS',
        'order_code' => $payment['transaction_id']
    ]);
}

if ($status === 'FAILED') {

    $pdo->prepare("UPDATE payments SET status='FAILED' WHERE token=?")->execute([$token]);
    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=?")->execute([$payment['order_id']]);

    json_exit(['status' => 'FAILED']);
}

json_exit(['status' => 'PENDING']);
