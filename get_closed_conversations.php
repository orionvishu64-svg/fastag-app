<?php
session_start();
require_once __DIR__ . "/db.php";
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Login required"]);
    exit;
}

$userId = $_SESSION['user_id'];

// ✅ Fetch all closed conversations for this user
$stmt = $pdo->prepare("
    SELECT cq.id, cq.ticket_id, cq.subject, cq.message, cq.submitted_at, cq.closed_at
    FROM contact_queries cq
    WHERE cq.user_id = ? AND cq.status = 'closed'
    ORDER BY cq.closed_at DESC, cq.submitted_at DESC
");
$stmt->execute([$userId]);
$queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Attach replies for each conversation 
foreach ($queries as &$q) {
    $r = $pdo->prepare("
        SELECT reply_text, replied_at, is_admin 
        FROM contact_replies
        WHERE contact_query_id = ?
        ORDER BY replied_at ASC
    ");
    $r->execute([$q['id']]);
    $q['replies'] = $r->fetchAll(PDO::FETCH_ASSOC);

    // Always mark closed conversations as not replyable
    $q['can_reply'] = false;
}

    echo json_encode([
    "success" => true,
    "queries" => $queries,
    "message" => empty($queries) ? "No closed conversations" : ""
]);