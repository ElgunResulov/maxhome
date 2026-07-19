<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$q = trim((string) ($_GET['q'] ?? ''));
// Çox qısa sorğularda səs-küy olmasın, amma 1 hərfdən axtarsın
$minLen = 1;

try {
    $pdo = db();
    $catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
    $like = '%' . $q . '%';
    $start = $q . '%';

    $popular = [];
    $categories = [];
    $products = [];

    if ($q !== '' && mb_strlen($q) >= $minLen) {
        $popularSql = "SELECT p.name, p.slug
                       FROM products p
                       LEFT JOIN brands b ON b.id = p.brand_id
                       WHERE p.status = 'active'{$catalogOnlineSql}
                         AND (
                            p.name LIKE :like
                            OR IFNULL(p.short_description, '') LIKE :like2
                            OR IFNULL(p.sku, '') LIKE :like3
                            OR IFNULL(b.name, '') LIKE :like4
                         )
                       ORDER BY
                         CASE WHEN p.name LIKE :start THEN 0 WHEN IFNULL(b.name, '') LIKE :start2 THEN 1 ELSE 2 END,
                         p.rating_count DESC,
                         p.rating_avg DESC,
                         p.id DESC
                       LIMIT 4";
        $popularStmt = $pdo->prepare($popularSql);
        $popularStmt->execute([
            'like' => $like,
            'like2' => $like,
            'like3' => $like,
            'like4' => $like,
            'start' => $start,
            'start2' => $start,
        ]);
        $popular = array_map(static fn(array $r): array => [
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ], $popularStmt->fetchAll() ?: []);

        $categoriesSql = "SELECT c.name, c.slug
                          FROM categories c
                          WHERE c.is_active = 1
                            AND c.name LIKE :cat_like
                          ORDER BY
                            CASE WHEN c.name LIKE :cat_start THEN 0 ELSE 1 END,
                            c.sort_order ASC,
                            c.id ASC
                          LIMIT 4";
        $categoryStmt = $pdo->prepare($categoriesSql);
        $categoryStmt->execute([
            'cat_like' => $like,
            'cat_start' => $start,
        ]);
        $categories = array_map(static fn(array $r): array => [
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ], $categoryStmt->fetchAll() ?: []);

        $productsSql = "SELECT
                            p.name,
                            p.slug,
                            p.base_price,
                            p.compare_at_price,
                            c.name AS category_name,
                            b.name AS brand_name,
                            (SELECT image_url
                             FROM product_images pi
                             WHERE pi.product_id = p.id
                             ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                             LIMIT 1) AS image_url
                        FROM products p
                        LEFT JOIN brands b ON b.id = p.brand_id
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.status = 'active'{$catalogOnlineSql}
                          AND (
                            p.name LIKE :product_like
                            OR IFNULL(p.short_description, '') LIKE :desc_like
                            OR IFNULL(p.sku, '') LIKE :sku_like
                            OR IFNULL(b.name, '') LIKE :brand_like
                          )
                        ORDER BY
                            CASE
                                WHEN p.name LIKE :start_like THEN 0
                                WHEN IFNULL(b.name, '') LIKE :brand_start THEN 1
                                ELSE 2
                            END,
                            p.rating_count DESC,
                            p.id DESC
                        LIMIT 8";
        $productsStmt = $pdo->prepare($productsSql);
        $productsStmt->execute([
            'product_like' => $like,
            'desc_like' => $like,
            'sku_like' => $like,
            'brand_like' => $like,
            'start_like' => $start,
            'brand_start' => $start,
        ]);
        $productRows = $productsStmt->fetchAll() ?: [];

        $products = array_map(static function (array $row): array {
            $category = trim((string) ($row['category_name'] ?? ''));
            $brand = trim((string) ($row['brand_name'] ?? ''));
            if ($category === '' && $brand !== '') {
                $category = $brand;
            } elseif ($category === '') {
                $category = 'Ümumi';
            }

            return [
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'category' => $category,
                'price' => (float) ($row['base_price'] ?? 0),
                'old_price' => $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null,
                'image_url' => trim((string) ($row['image_url'] ?? '')),
            ];
        }, $productRows);
    }

    echo json_encode([
        'ok' => true,
        'query' => $q,
        'popular' => $popular,
        'categories' => $categories,
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
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
