<?php
// /config/contact_queries.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Session auth
    $userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please log in']);
        exit;
    }

    // Read JSON input (falls back to POST if needed)
    $input = get_json_input();
    if (empty($input) && $_POST) $input = $_POST;

    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if ($subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit;
    }

    // Fetch user info
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Close any open queries for this user
    $closeStmt = $pdo->prepare("UPDATE contact_queries SET status='closed', closed_at=NOW() WHERE user_id = ? AND status = 'open'");
    $closeStmt->execute([$user['id']]);

    // Generate ticket id and insert new query
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
    error_log('[contact_queries] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}