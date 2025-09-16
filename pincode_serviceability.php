<?php
require_once __DIR__ . '/db.php'; // user site DB
require_once __DIR__ . '/delhivery.php'; // where we’ll add helper

header('Content-Type: application/json');

// Read input
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
$pincode = $data['pincode'] ?? null;

if (!$pincode) {
    echo json_encode(['success' => false, 'message' => 'Missing pincode']);
    exit;
}

// Call Delhivery API helper
$result = delhivery_check_pincode($pincode);

if (!empty($result['success'])) {
    echo json_encode(['success' => true, 'data' => $result['data']]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
