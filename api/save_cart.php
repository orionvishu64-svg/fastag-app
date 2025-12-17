<?php
require_once __DIR__ . '/../config/common_start.php';
header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);
$cart = $data['cart'] ?? [];

if (!is_array($cart) || empty($cart)) {
    echo json_encode(['success'=>false]);
    exit;
}

$_SESSION['cart'] = $cart;

echo json_encode(['success'=>true]);
