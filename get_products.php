<?php
// get_products.php
require_once 'common_start.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Allow optional filters
    $bank     = isset($_GET['bank']) ? trim($_GET['bank']) : null;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $search   = isset($_GET['q']) ? trim($_GET['q']) : null;
    $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

    $sql = "SELECT id, name, description, price, bank, category, product_id, activation, security, tagcost, payout
            FROM products
            WHERE 1=1";
    $params = [];

    if ($bank) {
        $sql .= " AND bank = ?";
        $params[] = $bank;
    }
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if ($search) {
        $sql .= " AND (name LIKE ? OR description LIKE ? OR product_id LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY bank ASC, category ASC, name ASC";

    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numeric strings properly
    foreach ($rows as &$r) {
        $r['price']      = (float)$r['price'];
        $r['tagcost']    = (float)$r['tagcost'];
        $r['activation'] = (int)$r['activation'];
        $r['security']   = (int)$r['security'];
    }

    echo json_encode(['success' => true, 'products' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
