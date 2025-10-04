<?php
// get_conversation.php
// Returns JSON: success, query, replies, can_reply, open, closed_at (optional)

declare(strict_types=1);

require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// current logged-in user (0 = not logged)
$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
$ticket_public_id = trim((string) ($_GET['ticket_id'] ?? ''));

try {
    $query = null;

    if ($ticket_public_id !== '') {
        // Lookup by public ticket id
        $stmt = $pdo->prepare("SELECT * FROM contact_queries WHERE ticket_id = ? LIMIT 1");
        $stmt->execute([$ticket_public_id]);
        $query = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$query) {
            // not found
            echo json_encode(["success" => true, "open" => false, "replies" => []]);
            exit;
        }

        // If user logged in, enforce ownership: don't let logged-in user read other users' tickets
        if ($userId > 0 && (int)$query['user_id'] !== $userId) {
            // Deny access — user must only view their own tickets on the public site
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            exit;
        }
    } elseif ($userId > 0) {
        // Fallback: latest query for logged-in user
        $stmt = $pdo->prepare("
            SELECT * FROM contact_queries
            WHERE user_id = ? AND status IN ('open','in_progress','closed')
            ORDER BY submitted_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $query = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$query) {
            echo json_encode(["success" => true, "open" => false, "replies" => []]);
            exit;
        }
    } else {
        // No ticket id provided and user not logged in -> nothing to show
        echo json_encode(["success" => true, "open" => false, "replies" => []]);
        exit;
    }

    // fetch replies for the found query id
    $stmt2 = $pdo->prepare("
        SELECT reply_text, replied_at, is_admin, admin_identifier, user_id
        FROM contact_replies
        WHERE contact_query_id = ?
        ORDER BY replied_at ASC
    ");
    $stmt2->execute([(int)$query['id']]);
    $replies = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        "success"   => true,
        "query"     => $query,
        "replies"   => $replies,
        "can_reply" => in_array($query['status'], ['open','in_progress'], true),
        "open"      => in_array($query['status'], ['open','in_progress'], true)
    ];

    if (isset($query['status']) && $query['status'] === 'closed') {
        $response['closed_at'] = $query['closed_at'] ?? null;
    }

    echo json_encode($response);
    exit;
} catch (Throwable $e) {
    error_log("[get_conversation] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error"]);
    exit;
}
