<?php
session_start();
require 'db.php'; // PDO connection

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch orders for logged-in user
$stmt = $pdo->prepare("SELECT o.id, o.amount, o.payment_method, o.status, o.created_at 
                       FROM orders o 
                       WHERE o.user_id = :uid
                       ORDER BY o.created_at DESC");
$stmt->execute(['uid' => $user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Orders</title>
    <link rel="stylesheet" href="track_orders.css">
</head>
<body>

    <div class="orders-container">
        <a href="cart.php" class="back-btn">⬅ Go Back</a>
        <h1>Your Orders</h1>
        <?php if (count($orders) > 0): ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <span>Order ID: <strong>#<?= $order['id'] ?></strong></span>
                        <span class="order-status"><?= ucfirst($order['status']) ?></span>
                    </div>
                    <div class="order-body">
                        <p><strong>Date:</strong> <?= date("d M Y, h:i A", strtotime($order['created_at'])) ?></p>
                        <p><strong>Amount:</strong> ₹<?= number_format($order['amount'], 2) ?></p>
                        <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></p>
                    </div>
                    <div class="order-footer">
                        <a href="order_details.php?order_id=<?= $order['id']; ?>" class="view-all">View All</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-orders">You have no orders yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
