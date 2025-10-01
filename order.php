<?php
require_once 'common_start.php';
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int) ( $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0 );
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['items'], $data['total'], $data['method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO orders (user_id, address_id, items, total_price, payment_method, payment_status)
                       VALUES (?, ?, ?, ?, ?, ?)");
$success = $stmt->execute([
    $data['user_id'],
    $data['address_id'] ?? null,
    json_encode($data['items']),
    $data['total'],
    $data['method'],
    $data['method'] === 'agent-id' ? 'pending' : 'paid'
]);

echo json_encode(['success' => $success]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['order_id']}</td>";
    echo "<td>{$row['order_date']}</td>";
    echo "<td>{$row['total_amount']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>
            <a href='order_details.php?order_id={$row['order_id']}' class='btn btn-info'>
                View Details
            </a>
          </td>";
    echo "</tr>";
}
?>