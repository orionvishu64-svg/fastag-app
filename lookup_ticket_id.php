<?php
// lookup_ticket_id.php
require_once __DIR__ . '/db.php';
header("Content-Type: application/json; charset=utf-8");

$ticket_id = $_GET['ticket_id'] ?? '';
$ticket_id = trim($ticket_id);

if ($ticket_id === '') {
    echo json_encode(['error' => 'Missing ticket_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM contact_queries WHERE ticket_id = ? LIMIT 1");
$stmt->execute([$ticket_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'Ticket not found']);
    exit;
}

echo json_encode(['contact_query_id' => (int)$row['id']]);
