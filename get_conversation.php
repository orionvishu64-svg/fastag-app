<?php
// get_conversation.php
require_once 'common_start.php';
require_once __DIR__ . "/db.php";
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

// Fetch latest ticket
$stmt = $pdo->prepare("
  SELECT * FROM contact_queries
  WHERE user_id = ? AND status IN ('open','in_progress','closed')
  ORDER BY submitted_at DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
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
  "open" => in_array($query['status'], ['open','in_progress']) // 👈 added
];

if ($query['status'] === 'closed') {
    $response["closed_at"] = $query['closed_at'] ?? null;
}

echo json_encode($response);
