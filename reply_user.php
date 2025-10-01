<?php
require_once 'common_start.php';
require_once 'db.php';
require_once __DIR__ . '/socket_auth.php'; // for verify_socket_token()

header('Content-Type: application/json; charset=utf-8');

// ---------------- Token Auth ----------------
$userId = 0;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    $uid = verify_socket_token($token);
    if ($uid > 0) {
        $userId = $uid;
        // optional: sync into session for consistency
        if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
        if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
    }
}

// ---------------- Fallback: Session Auth ----------------
if ($userId <= 0) {
    $userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
}

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

// ---------------- Input ----------------
$input = json_decode(file_get_contents("php://input"), true);
$queryId = isset($input['query_id']) ? (int)$input['query_id'] : 0;
$message = trim($input['message'] ?? '');

if ($queryId <= 0 || $message === '') {
    echo json_encode(["success" => false, "message" => "Query ID and message are required"]);
    exit;
}

// ---------------- Ownership check ----------------
// use canonical $userId instead of raw $_SESSION
$stmt = $pdo->prepare("SELECT id, status FROM contact_queries WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$queryId, $userId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(["success" => false, "message" => "Ticket not found"]);
    exit;
}
if (!in_array($ticket['status'], ['open', 'in_progress'])) {
    echo json_encode(["success" => false, "message" => "Ticket is closed"]);
    exit;
}

// ---------------- Insert reply ----------------
$ins = $pdo->prepare("
  INSERT INTO contact_replies (contact_query_id, reply_text, replied_at, is_admin)
  VALUES (?, ?, NOW(), 0)
");
$ok = $ins->execute([$queryId, $message]);

if ($ok) {
    $replyId = $pdo->lastInsertId();
    echo json_encode([
        "success"    => true,
        "message"    => "Reply added",
        "reply_id"   => (int)$replyId,
        "query_id"   => (int)$queryId,
        "reply_text" => $message,
        "replied_at" => date("Y-m-d H:i:s"),
        "is_admin"   => 0
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to add reply"]);
}
