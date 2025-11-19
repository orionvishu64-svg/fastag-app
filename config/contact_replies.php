<?php
// /contact_replies.php — robust reply saver with Node emit
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

// Toggle for debug logging — set define('DEBUG_CONTACT_REPLIES', true) above or export via env if you prefer.
if (!defined('DEBUG_CONTACT_REPLIES')) define('DEBUG_CONTACT_REPLIES', false);
$debugLog = '/tmp/contact_replies_debug.log';
function dlog($m) {
    global $debugLog;
    if (!defined('DEBUG_CONTACT_REPLIES') || !DEBUG_CONTACT_REPLIES) return;
    @file_put_contents($debugLog, date('[Y-m-d H:i:s] ') . $m . PHP_EOL, FILE_APPEND);
}

dlog("request start: " . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));

// include DB config (tries a few common locations)
$dbPaths = [
    __DIR__ . '/../config/db.php',
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
    dlog("DB include not found");
    jsonErr('Server error: database include not found', 500);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dlog("PDO not available after include");
    jsonErr('Server error: database not initialized', 500);
}

// require authentication / session user
$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    jsonErr('Please log in', 401);
}

// Parse JSON body or fallback to POST form
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST ?? [];
}

// Accept both names used by various clients
$queryId = isset($input['query_id']) ? (int)$input['query_id'] : (isset($input['contact_query_id']) ? (int)$input['contact_query_id'] : 0);
$message = trim((string) ($input['message'] ?? $input['reply_text'] ?? $input['reply'] ?? ''));

// public ticket id (ticket id string)
$ticketPublicId = isset($input['ticket_id']) ? trim((string)$input['ticket_id']) : (isset($input['ticket_public_id']) ? trim((string)$input['ticket_public_id']) : '');

// local_id forwarded by the client for optimistic rendering correlation (optional)
$localId = null;
if (!empty($input['local_id'])) $localId = trim((string)$input['local_id']);

// Also accept local_id as form param if not in JSON
if (empty($localId) && !empty($_POST['local_id'])) $localId = trim((string)$_POST['local_id']);

dlog("parsed input: queryId={$queryId} ticketPublicId={$ticketPublicId} localId=" . ($localId ?: 'NULL'));

// If only public ticket provided, try to lookup numeric id
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

// Ownership + ticket status check
try {
    $stmt = $pdo->prepare("SELECT id, status, ticket_id, user_id FROM contact_queries WHERE id = ? LIMIT 1");
    $stmt->execute([$queryId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    dlog("ownership check failed: " . $e->getMessage());
    jsonErr('Server error', 500);
}

if (!$ticket) {
    jsonErr('Ticket not found', 404);
}

// If ticket has an owner check that for some flows you may want to ensure the current user owns it.
// If this table stores user_id for owner, allow the owner or staff — current code only checks existence.
// If you require strict ownership uncomment the next block:
// if ((int)($ticket['user_id'] ?? 0) !== $userId) { jsonErr('Ticket not owned by you', 403); }

if (isset($ticket['status']) && strtolower((string)$ticket['status']) === 'closed') {
    jsonErr('Cannot reply to closed ticket', 403);
}

// Insert reply
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

    // Update ticket viewed flag non-fatally
    try {
        $u = $pdo->prepare("UPDATE contact_queries SET viewed = 0 WHERE id = ?");
        $u->execute([$queryId]);
    } catch (Throwable $e) {
        dlog("failed to update ticket viewed flag: " . $e->getMessage());
    }

    $pdo->commit();

    // Use DB time for replied_at if you want; using PHP time for payload consistency
    $repliedAt = date('Y-m-d H:i:s');

    // Build response to client
    $resp = [
        'success' => true,
        'message' => 'Reply added',
        'reply_id' => $replyId,
        'query_id' => $queryId,
        'reply_text' => $message,
        'replied_at' => $repliedAt,
        'is_admin' => 0
    ];

    dlog("reply added id={$replyId} query={$queryId} user={$userId} localId=" . ($localId ?: 'NULL'));

    // --- Prepare emit to Node socket server ---
    // Use environment variable SOCKET_SERVER to override default host (ex: http://15.207.50.101:3000)
    $socketHost = getenv('SOCKET_SERVER') ?: '';
    $socketHost = rtrim($socketHost, '/');
    $socketServer = $socketHost . '/emit-reply';

    // Optionally include emitter auth token from env (EMIT_AUTH_TOKEN)
    $emitAuthToken = getenv('EMIT_AUTH_TOKEN') ?: '';

    // prepare payload expected by Node server
    $emitPayload = [
        // prefer ticket public id if available (node's room logic can use either)
        'contact_query_id' => $ticket['ticket_id'] ?? null,
        'contact_query_numeric_id' => $queryId,
        // nested payload with saved reply
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

    // include local_id top-level and inside payload if provided (helps client dedupe)
    if (!empty($localId)) {
        $emitPayload['local_id'] = $localId;
        $emitPayload['payload']['local_id'] = $localId;
    }

    dlog("emit payload prepared: " . json_encode($emitPayload));

    $jsonEmit = json_encode($emitPayload);
    if ($jsonEmit !== false) {
        try {
            $headers = [
                "Content-Type: application/json",
                "Content-Length: " . strlen($jsonEmit)
            ];
            if ($emitAuthToken) {
                $headers[] = "x-emit-token: " . $emitAuthToken;
            }

            // use a slightly larger timeout to avoid intermittent failures
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => implode("\r\n", $headers) . "\r\n",
                    'content' => $jsonEmit,
                    'timeout' => 5
                ]
            ];
            $context  = stream_context_create($opts);

            // call node endpoint — don't fail if it is down (reply already saved)
            $result = @file_get_contents($socketServer, false, $context);
            if ($result === false) {
                dlog("emit-reply POST to {$socketServer} FAILED or returned no body");
            } else {
                // log small portion
                dlog("emit-reply POST result: " . substr($result, 0, 500));
            }
        } catch (Throwable $e) {
            dlog("emit POST exception: " . $e->getMessage());
            // non-fatal
        }
    } else {
        dlog("Failed to json_encode emit payload");
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    dlog("exception inserting reply: " . $e->getMessage());
    jsonErr('Server error', 500);
}
