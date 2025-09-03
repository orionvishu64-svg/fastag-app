<?php
require 'db.php';

// Check if order_id is passed
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    die("Order ID not provided.");
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.id, o.amount, o.payment_method, o.status, o.created_at,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           a.house_no, a.landmark, a.city, a.pincode
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Fetch order items
$stmt = $pdo->prepare("SELECT bank, product_name, quantity, price FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tracking updates
$stmt = $pdo->prepare("SELECT location, updated_at FROM order_tracking WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$order_id]);
$tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details - #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="order_details.css">
</head>
<body>
<div class="order-details-container">
    <h2>Order Details - #<?php echo $order_id; ?></h2>

    <div class="section">
        <h3>Order Info</h3>
        <p><strong>Date:</strong> <?php echo date("d M Y, h:i A", strtotime($order['created_at'])); ?></p>
        <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
        <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
        <p><strong>Total Amount:</strong> ₹<?php echo number_format($order['amount'], 2); ?></p>
    </div>

    <div class="section">
        <h3>Customer Info</h3>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['user_name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['user_email']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['user_phone']); ?></p>
    </div>

    <div class="section">
        <h3>Shipping Address</h3>
        <p>
            <?php echo htmlspecialchars($order['house_no']) . ", " .
                       htmlspecialchars($order['landmark']) . ", " .
                       htmlspecialchars($order['city']) . " - " .
                       htmlspecialchars($order['pincode']); ?>
        </p>
    </div>

    <div class="section">
        <h3>Order Tracking</h3>
        <?php if ($tracking): ?>
            <div class="tracking-timeline">
                <?php foreach ($tracking as $i => $t): ?>
                    <div class="tracking-step <?php echo $i === count($tracking) - 1 ? 'active' : ''; ?>">
                        <div class="icon">
                            <?php echo $i === count($tracking) - 1 ? "🚚" : "●"; ?>
                        </div>
                        <div class="details">
                            <p><?php echo htmlspecialchars($t['location']); ?></p>
                            <small><?php echo date("d M Y, h:i A", strtotime($t['updated_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No tracking updates yet.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3>Order Items</h3>
        <table>
            <thead>
                <tr>
                    <th>Bank</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price (₹)</th>
                    <th>Total (₹)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($order_items)): ?>
                <?php foreach ($order_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['bank']); ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No items found for this order.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="track_orders.php" class="back-btn">⬅ Back to Orders</a>
</div>

</body>
</html>
