<?php

declare(strict_types=1);

/**
 * category_brands cədvəli mövcuddurmu?
 */
function maxhomeCategoryBrandsTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category_brands' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

/**
 * @return list<array{name: string}>
 */
function maxhomeNormalizeBrandNameRows(array $rows): array
{
    $out = [];
    $seen = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' || mb_strtolower($name, 'UTF-8') === 'bütün markalar') {
            continue;
        }
        if (isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        $out[] = ['name' => $name];
    }

    return $out;
}

/**
 * category_brands üzrə: seçilmiş kateqoriya + alt ağacındakı əlaqələr.
 *
 * @return list<array{name: string}>
 */
function maxhomeShopBrandsFromCategoryBrandsTable(PDO $pdo, string $categorySlug): array
{
    if (!maxhomeCategoryBrandsTableExists($pdo)) {
        return [];
    }

    if ($categorySlug === '') {
        $stmt = $pdo->query(
            "SELECT DISTINCT b.name
             FROM category_brands cb
             INNER JOIN brands b ON b.id = cb.brand_id
             INNER JOIN categories c ON c.id = cb.category_id AND c.is_active = 1
             ORDER BY b.name ASC"
        );

        return maxhomeNormalizeBrandNameRows($stmt->fetchAll() ?: []);
    }

    $stmt = $pdo->prepare(
        "WITH RECURSIVE cat_ancestors AS (
            SELECT id, parent_id FROM categories WHERE slug = :slug AND is_active = 1
            UNION ALL
            SELECT p.id, p.parent_id
            FROM categories p
            INNER JOIN cat_ancestors a ON p.id = a.parent_id
            WHERE p.is_active = 1
        ),
        cat_descendants AS (
            SELECT id FROM categories WHERE slug = :slug_desc AND is_active = 1
            UNION ALL
            SELECT c.id FROM categories c
            INNER JOIN cat_descendants d ON c.parent_id = d.id
            WHERE c.is_active = 1
        ),
        cat_scope AS (
            SELECT id FROM cat_ancestors
            UNION
            SELECT id FROM cat_descendants
        )
        SELECT DISTINCT b.name
        FROM category_brands cb
        INNER JOIN brands b ON b.id = cb.brand_id
        WHERE cb.category_id IN (SELECT id FROM cat_scope)
        ORDER BY b.name ASC"
    );
    $stmt->execute([
        'slug' => $categorySlug,
        'slug_desc' => $categorySlug,
    ]);

    return maxhomeNormalizeBrandNameRows($stmt->fetchAll() ?: []);
}

/**
 * @return list<array{category_id:int, category_name:string, category_slug:string, depth:int, brand_id:int, brand_name:string}>
 */
function maxhomeCategoryBrandRelationsList(PDO $pdo): array
{
    if (!maxhomeCategoryBrandsTableExists($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT
            cb.category_id,
            c.name AS category_name,
            c.slug AS category_slug,
            c.parent_id,
            cb.brand_id,
            b.name AS brand_name,
            cb.created_at
         FROM category_brands cb
         INNER JOIN categories c ON c.id = cb.category_id
         INNER JOIN brands b ON b.id = cb.brand_id
         ORDER BY c.sort_order ASC, c.name ASC, b.name ASC"
    );
    $rows = $stmt->fetchAll() ?: [];
    if ($rows === []) {
        return [];
    }

    $byId = [];
    foreach ($pdo->query('SELECT id, parent_id FROM categories')->fetchAll() ?: [] as $c) {
        $byId[(int) $c['id']] = $c;
    }

    $depthOf = static function (int $id) use ($byId): int {
        $depth = 0;
        $guard = 0;
        while ($id > 0 && isset($byId[$id]) && $guard < 32) {
            $pid = $byId[$id]['parent_id'] ?? null;
            if ($pid === null || (int) $pid <= 0) {
                break;
            }
            $depth++;
            $id = (int) $pid;
            $guard++;
        }

        return $depth;
    };

    foreach ($rows as &$row) {
        $row['depth'] = $depthOf((int) ($row['category_id'] ?? 0));
    }
    unset($row);

    return $rows;
}

/**
 * Bir kateqoriya üçün brend ID-ləri.
 *
 * @return list<int>
 */
function maxhomeCategoryBrandIdsForCategory(PDO $pdo, int $categoryId): array
{
    if ($categoryId <= 0 || !maxhomeCategoryBrandsTableExists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT brand_id FROM category_brands WHERE category_id = :cid ORDER BY brand_id ASC'
    );
    $stmt->execute(['cid' => $categoryId]);

    return array_map(static fn(array $r): int => (int) $r['brand_id'], $stmt->fetchAll() ?: []);
}

/**
 * Kateqoriya üçün brend əlaqələrini yeniləyir (köhnələri silir, yenilərini yazır).
 *
 * @param list<int> $brandIds
 */
function maxhomeSaveCategoryBrandRelations(PDO $pdo, int $categoryId, array $brandIds): void
{
    if (!maxhomeCategoryBrandsTableExists($pdo)) {
        throw new RuntimeException('category_brands cədvəli tapilmadi. Zehmet olmasa migration_category_brands.sql isledin.');
    }
    if ($categoryId <= 0) {
        throw new RuntimeException('Kateqoriya secin.');
    }

    $check = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $check->execute(['id' => $categoryId]);
    if (!$check->fetch()) {
        throw new RuntimeException('Kateqoriya tapilmadi.');
    }

    $unique = [];
    foreach ($brandIds as $bid) {
        $bid = (int) $bid;
        if ($bid > 0) {
            $unique[$bid] = $bid;
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM category_brands WHERE category_id = :cid')->execute(['cid' => $categoryId]);
        if ($unique !== []) {
            $ins = $pdo->prepare(
                'INSERT INTO category_brands (category_id, brand_id) VALUES (:cid, :bid)'
            );
            foreach ($unique as $bid) {
                $ins->execute(['cid' => $categoryId, 'bid' => $bid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * categories.brand_id yarpaqlarını category_brands-ə köçürür.
 */
function maxhomeSyncCategoryBrandsFromLegacyLeaves(PDO $pdo): int
{
    if (!maxhomeCategoryBrandsTableExists($pdo)) {
        return 0;
    }
    $stmt = $pdo->query(
        "INSERT IGNORE INTO category_brands (category_id, brand_id)
         SELECT c.id, c.brand_id
         FROM categories c
         WHERE c.brand_id IS NOT NULL"
    );

    return (int) $stmt->rowCount();
}
