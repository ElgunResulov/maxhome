<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));

try {
    $pdo = db();
    $catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
    $like = '%' . $q . '%';
    $start = $q . '%';

    $popularSql = "SELECT p.name, p.slug
                   FROM products p
                   WHERE p.status = 'active'{$catalogOnlineSql}";
    $popularParams = [];
    if ($q !== '') {
        $popularSql .= " AND p.name LIKE :like";
        $popularParams['like'] = $like;
    }
    $popularSql .= " ORDER BY p.rating_count DESC, p.rating_avg DESC, p.id DESC LIMIT 3";
    $popularStmt = $pdo->prepare($popularSql);
    $popularStmt->execute($popularParams);
    $popularRows = $popularStmt->fetchAll() ?: [];

    $categoriesSql = "SELECT c.name, c.slug
                      FROM categories c
                      WHERE c.is_active = 1";
    $categoryParams = [];
    if ($q !== '') {
        $categoriesSql .= " AND c.name LIKE :cat_like";
        $categoryParams['cat_like'] = $like;
    }
    $categoriesSql .= " ORDER BY c.sort_order ASC, c.id ASC LIMIT 3";
    $categoryStmt = $pdo->prepare($categoriesSql);
    $categoryStmt->execute($categoryParams);
    $categoryRows = $categoryStmt->fetchAll() ?: [];

    $productsSql = "SELECT
                        p.name,
                        p.slug,
                        p.base_price,
                        p.compare_at_price,
                        c.name AS category_name,
                        (SELECT image_url
                         FROM product_images pi
                         WHERE pi.product_id = p.id
                         ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image_url
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.status = 'active'{$catalogOnlineSql}";
    $productsParams = [];
    if ($q !== '') {
        $productsSql .= " AND (p.name LIKE :product_like OR p.short_description LIKE :desc_like)";
        $productsParams['product_like'] = $like;
        $productsParams['desc_like'] = $like;
    }
    $productsSql .= " ORDER BY
                        CASE WHEN p.name LIKE :start_like THEN 0 ELSE 1 END,
                        p.rating_count DESC,
                        p.id DESC
                      LIMIT 3";
    $productsParams['start_like'] = $start;
    $productsStmt = $pdo->prepare($productsSql);
    $productsStmt->execute($productsParams);
    $productRows = $productsStmt->fetchAll() ?: [];

    $products = array_map(static function (array $row): array {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'category' => (string) ($row['category_name'] ?? 'General'),
            'price' => (float) ($row['base_price'] ?? 0),
            'old_price' => $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null,
            'image_url' => trim((string) ($row['image_url'] ?? '')),
        ];
    }, $productRows);

    echo json_encode([
        'ok' => true,
        'query' => $q,
        'popular' => array_map(static fn(array $r): array => [
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ], $popularRows),
        'categories' => array_map(static fn(array $r): array => [
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ], $categoryRows),
        'products' => $products,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'query' => $q,
        'popular' => [],
        'categories' => [],
        'products' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
