<?php
// get_closed_conversation.php
require_once 'common_start.php';
require_once __DIR__ . "/db.php";
require_once __DIR__ . '/socket_auth.php'; // for verify_socket_token()
header("Content-Type: application/json; charset=utf-8");

// ---------------- Token Auth ----------------
$userId = 0;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    $uid = verify_socket_token($token);
    if ($uid > 0) {
        $userId = $uid;
        // optional: sync into session
        if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
        if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
    }
}

// optional: allow ?token=... in GET for debugging/server calls
if ($userId <= 0 && !empty($_GET['token'])) {
    $uid = verify_socket_token($_GET['token']);
    if ($uid > 0) {
        $userId = $uid;
        if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
        if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
    }
}

// ---------------- Fallback: Session Auth ----------------
if ($userId <= 0) {
    $userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
}

// If not logged in, keep original behavior (return empty array)
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch all closed conversations for this user
    $stmt = $pdo->prepare("
        SELECT cq.id, cq.ticket_id, cq.subject, cq.message, cq.submitted_at, cq.closed_at
        FROM contact_queries cq
        WHERE cq.user_id = ? AND cq.status = 'closed'
        ORDER BY cq.closed_at DESC, cq.submitted_at DESC
    ");
    $stmt->execute([$userId]);
    $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queries)) {
        echo json_encode([
            "success" => true,
            "queries" => [],
            "message" => "No closed conversations"
        ]);
        exit;
    }

    // Collect all contact_query IDs and fetch replies in a single query
    $ids = array_column($queries, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $replySql = "
        SELECT contact_query_id, reply_text, replied_at, is_admin
        FROM contact_replies
        WHERE contact_query_id IN ($placeholders)
        ORDER BY replied_at ASC
    ";
    $replyStmt = $pdo->prepare($replySql);
    $replyStmt->execute($ids);
    $allReplies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group replies by contact_query_id
    $repliesByQuery = [];
    foreach ($allReplies as $r) {
        $repliesByQuery[$r['contact_query_id']][] = [
            'reply_text' => $r['reply_text'],
            'replied_at' => $r['replied_at'],
            'is_admin'   => (bool)$r['is_admin']
        ];
    }

    // Attach replies to each conversation and mark can_reply = false
    foreach ($queries as &$q) {
        $q['replies'] = $repliesByQuery[$q['id']] ?? [];
        $q['can_reply'] = false;
    }
    unset($q);

    echo json_encode([
        "success" => true,
        "queries" => $queries,
        "message" => ""
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log("[get_closed_conversation] Exception: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
    exit;
}
