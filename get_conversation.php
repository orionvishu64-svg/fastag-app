<?php
// get_conversation.php
require_once 'common_start.php';
require_once __DIR__ . "/db.php";
// socket_auth.php defines verify_socket_token() and session helpers
require_once __DIR__ . '/socket_auth.php';

header("Content-Type: application/json; charset=utf-8");

// ---------------- Try token auth first ----------------
$userId = 0;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    $uid = verify_socket_token($token);
    if ($uid > 0) {
        $userId = $uid;
        // optional: populate session so other code relying on $_SESSION sees the user
        if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
        if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
    }
}

// Also accept token via GET param as a fallback for server-to-server calls (optional)
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

// If not logged in, keep original behaviour (return empty array)
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch latest ticket for this user
    $stmt = $pdo->prepare("
      SELECT * FROM contact_queries
      WHERE user_id = ? AND status IN ('open','in_progress','closed')
      ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $query = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$query) {
        echo json_encode(["success" => true, "open" => false]);
        exit;
    }

    // Fetch replies
    $stmt2 = $pdo->prepare("
      SELECT reply_text, replied_at, is_admin
      FROM contact_replies
      WHERE contact_query_id = ?
      ORDER BY replied_at ASC
    ");
    $stmt2->execute([$query['id']]);
    $replies = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Build response
    $response = [
      "success" => true,
      "query"   => $query,
      "replies" => $replies,
      "can_reply" => in_array($query['status'], ['open','in_progress']),
      "open" => in_array($query['status'], ['open','in_progress'])
    ];

    if ($query['status'] === 'closed') {
        $response["closed_at"] = $query['closed_at'] ?? null;
    }

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    // Keep message generic for clients, but optionally log $e->getMessage() to error log
    error_log("[get_conversation] Exception: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
    exit;
}
