<?php
require_once 'common_start.php';
// fastag_website/get_products.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

try {
    // Optional search ?q=
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = 'WHERE p.name LIKE :q OR p.bank LIKE :q OR p.description LIKE :q OR p.category LIKE :q';
        $params[':q'] = "%$q%";
    }

    
    $sql = "SELECT 
                p.id AS product_id,
                p.name,
                p.description,
                p.price,
                p.bank,
                p.category,
            FROM products p
            $where
            ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'data'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'SERVER_ERROR',
        'message' => $e->getMessage()
    ]);
}