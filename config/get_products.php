<?php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

const IMAGE_HOST = 'http://15.207.6.169'; // server where uploads/ actually exist

function fix_image_url(?string $path): string {
    if (!$path) return '';
    if (preg_match('~^(?:https?:)?//~i', $path)) return $path;
    $path = ($path[0] === '/') ? $path : '/' . $path;
    return rtrim(IMAGE_HOST, '/') . $path;
}

try {
    $bank = $_GET['bank'] ?? null;
    $category = $_GET['category'] ?? null;
    $search = $_GET['q'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

    $sql = "SELECT 
                id, name, description, price, bank, category, product_id,
                activation, balance, security, tagcost, payout,
                image AS logo
            FROM products
            WHERE 1=1";
    $params = [];

    if ($bank) { $sql .= " AND bank = ?"; $params[] = $bank; }
    if ($category) { $sql .= " AND category = ?"; $params[] = $category; }
    if ($search) {
        $sql .= " AND (name LIKE ? OR description LIKE ? OR product_id LIKE ?)";
        $like = "%{$search}%";
        array_push($params, $like, $like, $like);
    }

    $sql .= " ORDER BY bank ASC, category ASC, name ASC";
    if ($limit > 0) $sql .= " LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['price']      = (float)($r['price'] ?? 0);
        $r['tagcost']    = (float)($r['tagcost'] ?? 0);
        $r['activation'] = (int)($r['activation'] ?? 0);
        $r['balance']    = (int)($r['balance'] ?? 0);
        $r['security']   = (int)($r['security'] ?? 0);
        $r['payout']     = (string)($r['payout'] ?? '');
        $r['logo']       = fix_image_url($r['logo'] ?? '');
    }
    unset($r);

    echo json_encode(['success' => true, 'products' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
