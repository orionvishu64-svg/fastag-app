<?php
// reply_user.php
session_start();

require_once __DIR__ . "/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$queryId = isset($input['query_id']) ? (int)$input['query_id'] : 0;
$message = trim($input['message'] ?? '');

if ($queryId <= 0 || $message === '') {
    echo json_encode(["success" => false, "message" => "Query ID and message are required"]);
    exit;
}

// Ensure this ticket belongs to the user and is open or in_progress
$stmt = $pdo->prepare("SELECT id, status FROM contact_queries WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$queryId, $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(["success" => false, "message" => "Ticket not found"]);
    exit;
}
if (!in_array($ticket['status'], ['open', 'in_progress'])) {
    echo json_encode(["success" => false, "message" => "Ticket is closed"]);
    exit;
}

// Insert reply (is_admin = 0 for user)
$ins = $pdo->prepare("
  INSERT INTO contact_replies (contact_query_id, reply_text, replied_at, is_admin)
  VALUES (?, ?, NOW(), 0)
");
$ok = $ins->execute([$queryId, $message]);

if ($ok) {
    $replyId = $pdo->lastInsertId();
    echo json_encode([
        "success" => true,
        "message" => "Reply added",
        "reply_id" => (int)$replyId,
        "query_id" => (int)$queryId,
        "reply_text" => $message,
        "replied_at" => date("Y-m-d H:i:s"),
        "is_admin" => 0
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to add reply"]);
}
