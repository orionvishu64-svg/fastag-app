<?php
// get_products.php
require_once __DIR__ . '/common_start.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// 👇 hardcode the host that actually has your /uploads/ folder
// Use HTTPS if your site is HTTPS
const ASSET_BASE_URL = 'http://15.207.6.169';

function to_absolute_asset_url(?string $p): string {
    if (!$p) return '';
    // already absolute? (http/https or protocol-relative //)
    if (preg_match('~^(?:http?:)?//~i', $p)) return $p;
    // ensure leading slash for relative paths like "uploads/..."
    $p = ($p[0] === '/') ? $p : '/' . $p;
    return rtrim(ASSET_BASE_URL, '/') . $p;
}

try {
    $bank     = isset($_GET['bank']) ? trim($_GET['bank']) : null;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $search   = isset($_GET['q']) ? trim($_GET['q']) : null;
    $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

    // include image AS logo for the frontend
    $sql = "SELECT 
                id, name, description, price, bank, category, product_id,
                activation, balance, security, tagcost, payout,
                image AS logo
            FROM products
            WHERE 1=1";
    $params = [];

    if ($bank !== null && $bank !== '') { $sql .= " AND bank = ?";      $params[] = $bank; }
    if ($category !== null && $category !== '') { $sql .= " AND category = ?";  $params[] = $category; }
    if ($search !== null && $search !== '') {
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

        // 👇 convert DB path like "uploads/products/..." into full URL
        $r['logo'] = to_absolute_asset_url($r['logo'] ?? '');
    }
    unset($r);

    echo json_encode(['success' => true, 'products' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
