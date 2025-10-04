<?php
// contact_replies.php — robust reply saver with Node emit
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function jsonErr($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => (string)$msg]);
    exit;
}

if (!defined('DEBUG_CONTACT_REPLIES')) define('DEBUG_CONTACT_REPLIES', false);
$debugLog = '/tmp/contact_replies_debug.log';
function dlog($m) {
    global $debugLog;
    if (!defined('DEBUG_CONTACT_REPLIES') || !DEBUG_CONTACT_REPLIES) return;
    @file_put_contents($debugLog, date('[Y-m-d H:i:s] ') . $m . PHP_EOL, FILE_APPEND);
}

dlog("request start");

// include DB config (same fallback list as before)
$dbPaths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/db.php',
    __DIR__ . '/includes/db.php'
];
$included = false;
foreach ($dbPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $included = true;
        dlog("Included DB: $p");
        break;
    }
}
if (!$included) {
    jsonErr('Server error: database include not found', 500);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    jsonErr('Server error: database not initialized', 500);
}

$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    jsonErr('Please log in', 401);
}

// Parse JSON or form
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST ?? [];

$queryId = isset($input['query_id']) ? (int)$input['query_id'] : 0;
$message = trim((string) ($input['message'] ?? $input['reply_text'] ?? ''));
$ticketPublicId = isset($input['ticket_id']) ? trim((string)$input['ticket_id']) : '';

if ($queryId <= 0 && $ticketPublicId !== '') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM contact_queries WHERE ticket_id = ? LIMIT 1");
        $stmt->execute([$ticketPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $queryId = (int)$row['id'];
    } catch (Throwable $e) {
        dlog("ticket lookup failed: " . $e->getMessage());
        jsonErr('Server error', 500);
    }
}

if ($queryId <= 0 || $message === '') {
    jsonErr('Query ID and message are required', 400);
}

// Ownership + status check
try {
    $stmt = $pdo->prepare("SELECT id, status, ticket_id FROM contact_queries WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$queryId, $userId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    dlog("ownership check failed: " . $e->getMessage());
    jsonErr('Server error', 500);
}

if (!$ticket) {
    jsonErr('Ticket not found or not owned by you', 404);
}
if (isset($ticket['status']) && strtolower($ticket['status']) === 'closed') {
    jsonErr('Cannot reply to closed ticket', 403);
}

// Insert reply in transaction
try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
      INSERT INTO contact_replies (contact_query_id, reply_text, replied_at, is_admin)
      VALUES (:cqid, :msg, NOW(), 0)
    ");
    $ok = $ins->execute([
        ':cqid' => $queryId,
        ':msg' => $message
    ]);
    if (!$ok) {
        $pdo->rollBack();
        dlog("insert failed: " . json_encode($ins->errorInfo()));
        jsonErr('Failed to add reply', 500);
    }
    $replyId = (int)$pdo->lastInsertId();

    // Mark viewed = 0 so admin sees new reply (non-fatal)
    try {
        $u = $pdo->prepare("UPDATE contact_queries SET viewed = 0 WHERE id = ?");
        $u->execute([$queryId]);
    } catch (Throwable $e) {
        dlog("failed to update ticket viewed flag: " . $e->getMessage());
    }

    $pdo->commit();

    $repliedAt = date('Y-m-d H:i:s');

    // Build response for client
    $resp = [
        'success' => true,
        'message' => 'Reply added',
        'reply_id' => $replyId,
        'query_id' => $queryId,
        'reply_text' => $message,
        'replied_at' => $repliedAt,
        'is_admin' => 0
    ];

    // --- POST to Socket server to broadcast in real-time ---
    // Default local Node URL. Change if your Node runs elsewhere.
    $socketServer = 'http://127.0.0.1:3000/emit-reply';

    // prepare payload expected by Node server
    $payload = [
        'contact_query_id' => $ticket['ticket_id'] ?? $queryId, // include public ticket id if available
        // include both numeric and public forms so Node can choose; ensure consistent room name on Node
        'contact_query_numeric_id' => $queryId,
        'payload' => [
            'id' => $replyId,
            'contact_query_id' => $queryId,
            'ticket_public_id' => $ticket['ticket_id'] ?? $ticketPublicId,
            'user_id' => $userId,
            'is_admin' => 0,
            'admin_identifier' => null,
            'reply_text' => $message,
            'replied_at' => $repliedAt
        ]
    ];

    // Send non-blocking simple POST (use stream context with short timeout)
    $json = json_encode($payload);
    if ($json !== false) {
        try {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                 "Content-Length: " . strlen($json) . "\r\n",
                    'content' => $json,
                    'timeout' => 2 // 2 seconds
                ]
            ];
            $context  = stream_context_create($opts);
            // @ suppress so even if failure occurs we continue; log when debug enabled
            $result = @file_get_contents($socketServer, false, $context);
            dlog("emit-reply POST to {$socketServer} result: " . ($result === false ? 'FAILED' : substr($result,0,500)));
        } catch (Throwable $e) {
            dlog("emit POST exception: " . $e->getMessage());
            // not fatal for the user — message was saved
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp);
    dlog("reply added id={$replyId} query={$queryId} user={$userId}");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    dlog("exception inserting reply: " . $e->getMessage());
    jsonErr('Server error', 500);
}
