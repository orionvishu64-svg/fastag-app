<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

try {
    // Require login
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in']);
        exit;
    }

    // Decode JSON input
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

    // Fetch user info
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Close any open tickets for this user
    $pdo->prepare("UPDATE contact_queries SET status='closed', closed_at=NOW() 
                   WHERE user_id=? AND status='open'")
        ->execute([$user['id']]);

    // Generate new ticket ID
    $ticket_id = 'TCK-' . date("Y") . strtoupper(substr(uniqid(), -6));

    // Insert new query
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

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
