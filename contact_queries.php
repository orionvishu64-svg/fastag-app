<?php
// contact_queries.php
require_once 'common_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/socket_auth.php'; // provides verify_socket_token()
header('Content-Type: application/json; charset=utf-8');

try {
    // ---------------- Token Auth ----------------
    $userId = 0;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
        $uid = verify_socket_token($token);
        if ($uid > 0) {
            $userId = $uid;
            // optional: populate session for compatibility
            if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = $uid;
            if (empty($_SESSION['user']))     $_SESSION['user'] = ['id' => $uid];
        }
    }

    // Accept ?token=... for server-to-server convenience/debug
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

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please log in']);
        exit;
    }

    // ---------------- Input ----------------
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if ($subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit;
    }

    // ---------------- Fetch user info by canonical $userId ----------------
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // ---------------- Close any open tickets for this user ----------------
    $closeStmt = $pdo->prepare("UPDATE contact_queries SET status='closed', closed_at=NOW() WHERE user_id=? AND status='open'");
    $closeStmt->execute([$user['id']]);

    // ---------------- Generate ticket id and insert ----------------
    $ticket_id = 'TCK-' . date("Y") . strtoupper(substr(uniqid(), -6));

    $stmt = $pdo->prepare("
        INSERT INTO contact_queries
        (ticket_id, user_id, name, email, phone, subject, message, submitted_at, viewed, status, priority)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 'open', 'medium')
    ");

    $ok = $stmt->execute([
        $ticket_id,
        $user['id'],
        $user['name'],
        $user['email'],
        $user['phone'],
        $subject,
        $message
    ]);

    if ($ok) {
        echo json_encode([
            'success'   => true,
            'message'   => 'Ticket submitted successfully',
            'ticket_id' => $ticket_id,
            'id'        => (int)$pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save ticket']);
    }

} catch (Throwable $e) {
    // Log internal error; keep client-facing message generic
    error_log('[contact_queries] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
