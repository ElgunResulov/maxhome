<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../user_auth.php';

header('Content-Type: application/json; charset=utf-8');

$isAdmin = isAdminLoggedIn();
$isStorekeeper = isUserLoggedIn() && userHasAnyRole(['storekeeper']);
if (!$isAdmin && !$isStorekeeper) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Giris teleb olunur.',
        'products' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
if ($qLen < 2) {
    echo json_encode(['ok' => true, 'products' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $escapedQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $like = '%' . $escapedQ . '%';

    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.sku, p.stock_qty, p.base_price, p.status
         FROM products p
         WHERE p.name LIKE :q_name
            OR p.sku LIKE :q_sku
         ORDER BY p.name ASC
         LIMIT 25"
    );
    $stmt->execute(['q_name' => $like, 'q_sku' => $like]);
    $rows = $stmt->fetchAll() ?: [];

    $products = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sku' => (string) $row['sku'],
            'stock_qty' => (int) $row['stock_qty'],
            'base_price' => (float) $row['base_price'],
            'status' => (string) $row['status'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server xetasi',
        'products' => [],
    ], JSON_UNESCAPED_UNICODE);
}
