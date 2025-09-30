<?php
require_once 'common_start.php';
require_once 'db.php';

header('Content-Type: application/json');

// Accept either canonical session shape or legacy alias
$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

// If not logged in, keep original behavior (return empty array)
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, house_no, landmark, city, pincode, created_at FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($addresses);
} catch (Throwable $e) {
    // don't expose internal error to client; return empty array to preserve frontend expectations
    // optionally log the error server-side for debugging:
    // error_log("get_addresses error: " . $e->getMessage());
    echo json_encode([]);
}
