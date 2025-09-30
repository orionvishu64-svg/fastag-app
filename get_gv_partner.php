<?php
// get_gv_partner.php
require_once 'common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$stmt = $pdo->prepare("SELECT gv_partner_id 
                       FROM gv_partners 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 1");
$stmt->execute([$userId]);
$gv = $stmt->fetchColumn();

if ($gv) {
    echo json_encode(['success' => true, 'gv_partner_id' => $gv]);
} else {
    echo json_encode(['success' => false, 'message' => 'No GV Partner ID found']);
}
