<?php
// contact_replies.php - minimal, robust API for posting a reply (returns JSON)
// Safely tries to include DB bootstrap, then inserts a reply and returns JSON.

if (session_status() === PHP_SESSION_NONE) @session_start();

function respond_json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function log_dbg($s) {
    @file_put_contents('/tmp/contact_replies_debug.log', date('[Y-m-d H:i:s] ').$s."\n", FILE_APPEND);
}

require_once __DIR__ . '/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['success' => false, 'message' => 'Invalid method'], 405);
}

// Accept either query_id or ticket_id (ticket_id might be public id)
$raw = $_POST;
$ticket_id = isset($raw['ticket_id']) ? trim((string)$raw['ticket_id']) : null;
$query_id  = isset($raw['query_id'])  ? intval($raw['query_id']) : 0;
$message   = isset($raw['message']) ? trim((string)$raw['message']) : (isset($raw['reply_text']) ? trim((string)$raw['reply_text']) : '');
$local_id  = isset($raw['local_id']) ? trim((string)$raw['local_id']) : null;

if (!$message) respond_json(['success' => false, 'message' => 'Empty message'], 400);

// Resolve contact_query_id from ticket_id if necessary
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
    log_dbg("resolve-query-exc: " . $e->getMessage());
    respond_json(['success' => false, 'message' => 'Server error'], 500);
}

if ($contact_query_id <= 0) {
    respond_json(['success' => false, 'message' => 'Invalid ticket/query id'], 400);
}

// Determine is_admin and admin_identifier (if admin is logged in)
$is_admin = 0;
$admin_identifier = null;
$user_id = null; // optional: if you store admin's user id

if (!empty($_SESSION['admin_id'])) {
    $is_admin = 1;
    $user_id = intval($_SESSION['admin_id']);
    $admin_identifier = $_SESSION['admin_email'] ?? $_SESSION['email'] ?? $_SESSION['username'] ?? null;
} elseif (!empty($_SESSION['user_id'])) {
    // logged-in regular user (if applicable)
    $is_admin = 0;
    $user_id = intval($_SESSION['user_id']);
}

// Insert into DB
try {
    $sql = 'INSERT INTO contact_replies
      (contact_query_id, user_id, is_admin, admin_identifier, reply_text, replied_at, local_id)
      VALUES (:contact_query_id, :user_id, :is_admin, :admin_identifier, :reply_text, :replied_at, :local_id)';

    $stmt = $pdo->prepare($sql);
    $now = date('Y-m-d H:i:s');

    $binds = [
        ':contact_query_id' => $contact_query_id,
        ':user_id' => $user_id ?: null,
        ':is_admin' => $is_admin,
        ':admin_identifier' => $admin_identifier ?: null,
        ':reply_text' => $message,
        ':replied_at' => $now,
        ':local_id' => $local_id ?: null
    ];

    $ok = $stmt->execute($binds);
    if (!$ok) {
        $err = $stmt->errorInfo();
        log_dbg("insert-failed: " . json_encode($err));
        respond_json(['success' => false, 'message' => 'DB error', 'error' => $err], 500);
    }

    $inserted_id = (int)$pdo->lastInsertId();

    // Optionally: update contact_queries last_replied_at / status etc. (not mandatory)
    respond_json([
        'success' => true,
        'inserted_id' => $inserted_id,
        'local_id' => $local_id ?: null,
        'query_id' => $contact_query_id
    ], 200);

} catch (Throwable $e) {
    log_dbg("exception-insert: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    respond_json(['success' => false, 'message' => 'Server exception', 'error' => $e->getMessage()], 500);
}
