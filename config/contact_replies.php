<?php
// contact_replies.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) @session_start();

function respond_json($payload, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function dbg($s) {
    @file_put_contents('/tmp/contact_replies_debug.log', date('[Y-m-d H:i:s] ').$s."\n", FILE_APPEND);
}

// Bootstrap DB - adjust path if your db.php is elsewhere
$dbPath = __DIR__ . '/db.php';
if (!file_exists($dbPath)) {
    respond_json(['success' => false, 'message' => 'Server error: DB bootstrap missing'], 500);
}
require_once $dbPath;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    respond_json(['success' => false, 'message' => 'Server error: DB unavailable'], 500);
}

// Accept only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['success' => false, 'message' => 'Invalid method'], 405);
}

// Read POST (form or fetch)
$raw = $_POST;
$ticket_id = isset($raw['ticket_id']) ? trim((string)$raw['ticket_id']) : null;
$query_id  = isset($raw['query_id'])  ? intval($raw['query_id']) : 0;
$message   = isset($raw['message']) ? trim((string)$raw['message']) : (isset($raw['reply_text']) ? trim((string)$raw['reply_text']) : '');
$local_id  = isset($raw['local_id']) ? trim((string)$raw['local_id']) : null;

// Basic validation
if ($message === '') respond_json(['success' => false, 'message' => 'Empty message'], 400);

// message length cap
$MAX = 5000;
if (mb_strlen($message) > $MAX) {
    $message = mb_substr($message, 0, $MAX);
}

// Resolve contact_query_id
$contact_query_id = 0;
try {
    if ($query_id > 0) {
        $contact_query_id = $query_id;
    } elseif ($ticket_id) {
        $stmt = $pdo->prepare('SELECT id FROM contact_queries WHERE ticket_id = ? LIMIT 1');
        $stmt->execute([$ticket_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $contact_query_id = (int)$row['id'];
    }
} catch (Throwable $e) {
    dbg("resolve-query-exc: " . $e->getMessage());
    respond_json(['success' => false, 'message' => 'Server error'], 500);
}

if ($contact_query_id <= 0) respond_json(['success' => false, 'message' => 'Invalid ticket/query id'], 400);

// Determine sender from session (regular user)
$is_admin = 0;
$sender_name = null;
$sender_email = null;
$user_id = null;

if (!empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    // prefer explicit session values if available
    $sender_name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? null;
    $sender_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? null;
} else {
    // anonymous user allowed? You may reject if you want login enforced
    // leave sender details null for anonymous
    $user_id = null;
}

// Idempotency: if local_id is provided, try to find an existing row
try {
    $pdo->beginTransaction();

    $existingId = null;
    if (!empty($local_id)) {
        $s = $pdo->prepare("SELECT id FROM contact_replies WHERE contact_query_id = :cqid AND local_id = :local_id LIMIT 1");
        $s->execute([':cqid' => $contact_query_id, ':local_id' => $local_id]);
        $er = $s->fetch(PDO::FETCH_ASSOC);
        if ($er && !empty($er['id'])) {
            $existingId = (int)$er['id'];
            dbg("Found existing by local_id={$local_id} id={$existingId}");
        }
    }

    // Short duplicate protection for users: same text within 3 seconds
    if ($existingId === null) {
        $dupStmt = $pdo->prepare("SELECT id FROM contact_replies
            WHERE contact_query_id = :cqid AND user_id = :uid AND reply_text = :msg
              AND replied_at >= (NOW() - INTERVAL :win SECOND) LIMIT 1");
        $dupStmt->execute([
            ':cqid' => $contact_query_id,
            ':uid'  => $user_id ?: 0,
            ':msg'  => $message,
            ':win'  => 3
        ]);
        $dupRow = $dupStmt->fetch(PDO::FETCH_ASSOC);
        if ($dupRow && !empty($dupRow['id'])) {
            $existingId = (int)$dupRow['id'];
            dbg("Duplicate detected user id={$user_id} id={$existingId}");
        }
    }

    if ($existingId !== null) {
        $inserted_id = $existingId;
    } else {
        // Insert - ensure admin_identifier is NULL for user-side writes
        $sql = 'INSERT INTO contact_replies
          (contact_query_id, user_id, is_admin, admin_identifier, sender_name, sender_email, reply_text, replied_at, local_id)
          VALUES (:contact_query_id, :user_id, :is_admin, NULL, :sender_name, :sender_email, :reply_text, :replied_at, :local_id)';
        $stmt = $pdo->prepare($sql);
        $now = date('Y-m-d H:i:s');
        $binds = [
            ':contact_query_id' => $contact_query_id,
            ':user_id' => $user_id ?: null,
            ':is_admin' => 0,
            ':sender_name' => $sender_name ?: null,
            ':sender_email' => $sender_email ?: null,
            ':reply_text' => $message,
            ':replied_at' => $now,
            ':local_id' => $local_id ?: null
        ];
        $ok = $stmt->execute($binds);
        if (!$ok) {
            $err = $stmt->errorInfo();
            dbg("insert-failed: " . json_encode($err));
            $pdo->rollBack();
            respond_json(['success' => false, 'message' => 'DB error', 'error' => $err], 500);
        }
        $inserted_id = (int)$pdo->lastInsertId();
        dbg("Inserted user reply id={$inserted_id} ticket={$contact_query_id} local_id=" . ($local_id ?? ''));
    }

    $pdo->commit();
} catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $_) {}
    dbg("exception-insert: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    respond_json(['success' => false, 'message' => 'Server exception', 'error' => $e->getMessage()], 500);
}

// Best-effort socket emit with a normalized payload
try {
    $helper = '/opt/bitnami/apache/htdocs/fastag_website/config/socket_emit.php';
    if (file_exists($helper)) {
        require_once $helper;
        $payload = [
            'id' => $inserted_id,
            'contact_query_id' => $contact_query_id,
            'reply_text' => $message,
            'is_admin' => 0,
            'replied_at' => $now,
            'local_id' => $local_id ?: null,
            'sender_name' => $sender_name ?: null,
            'sender_email' => $sender_email ?: null,
            'admin_identifier' => null
        ];
        list($resp, $err) = socket_emit('ticket_' . $contact_query_id, 'new_reply', $payload);
        if ($err) dbg("socket_emit error: " . $err . " resp=" . substr($resp ?? '', 0, 200));
        else dbg("socket_emit ok resp=" . substr($resp ?? '', 0, 200));
    } else {
        dbg("socket_emit helper missing, skipping emit");
    }
} catch (Throwable $e) {
    dbg("socket_emit exception: " . $e->getMessage());
}

// Return success with id and local_id (for client idempotency)
respond_json([
    'success' => true,
    'inserted_id' => $inserted_id,
    'local_id' => $local_id ?: null,
    'query_id' => $contact_query_id
], 200);
