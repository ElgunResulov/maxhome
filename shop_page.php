<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/category_brands.php';

/**
 * Mağaza filtri: kateqoriya seçilibsə yalnız həmin alt ağaca aid brendlər.
 * 1) categories.brand_id olan yarpaqlar (seed_az_catalog)2) həmin ağacdakı məhsulların brendləri 3) fallback: bütün brendlər.
 *
 * @return list<array{name: string}>
 */
function maxhome_shop_brands_for_category_slug(PDO $pdo, string $categorySlug, bool $categoriesHasBrandIdColumn): array
{
    $normalize = static function (array $rows): array {
        return maxhomeNormalizeBrandNameRows($rows);
    };

    $fromRelations = maxhomeShopBrandsFromCategoryBrandsTable($pdo, $categorySlug);
    if ($fromRelations !== []) {
        return $fromRelations;
    }

    if ($categorySlug === '') {
        // 1) categories.brand_id (kataloq yarpaqları) -> daha stabildir
        if ($categoriesHasBrandIdColumn) {
            $stmt = $pdo->query(
                "SELECT DISTINCT b.name
                 FROM categories c
                 INNER JOIN brands b ON b.id = c.brand_id
                 WHERE c.is_active = 1
                   AND c.brand_id IS NOT NULL
                 ORDER BY b.name ASC"
            );
            $fromCategories = $normalize($stmt->fetchAll() ?: []);
            if ($fromCategories !== []) {
                return $fromCategories;
            }
        }

        // 2) məhsul əlaqələri
        $stmt = $pdo->query(
            "SELECT b.name
             FROM brands b
             LEFT JOIN products p ON p.brand_id = b.id AND p.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'p') . "
             GROUP BY b.id, b.name
             ORDER BY COUNT(p.id) DESC, b.name ASC"
        );
        $fromProducts = $normalize($stmt->fetchAll() ?: []);
        if ($fromProducts !== []) {
            return $fromProducts;
        }

        // 3) son fallback: birbaşa brands cədvəli
        $stmt = $pdo->query("SELECT name FROM brands ORDER BY name ASC");
        return $normalize($stmt->fetchAll() ?: []);
    }

    $slugParam = ['slug' => $categorySlug];

    if ($categoriesHasBrandIdColumn) {
        $stmt = $pdo->prepare(
            "WITH RECURSIVE cat_subtree AS (
                SELECT id FROM categories WHERE slug = :slug AND is_active = 1
                UNION ALL
                SELECT c.id FROM categories c
                INNER JOIN cat_subtree s ON c.parent_id = s.id
                WHERE c.is_active = 1
            )
            SELECT DISTINCT b.name
            FROM categories c
            INNER JOIN brands b ON b.id = c.brand_id
            WHERE c.id IN (SELECT id FROM cat_subtree)
            ORDER BY b.name ASC"
        );
        $stmt->execute($slugParam);
        $fromCategories = $normalize($stmt->fetchAll() ?: []);
        if ($fromCategories !== []) {
            return $fromCategories;
        }
    }

    $stmt = $pdo->prepare(
        "WITH RECURSIVE cat_subtree AS (
            SELECT id FROM categories WHERE slug = :slug AND is_active = 1
            UNION ALL
            SELECT c.id FROM categories c
            INNER JOIN cat_subtree s ON c.parent_id = s.id
            WHERE c.is_active = 1
        )
        SELECT DISTINCT b.name
        FROM products p
        INNER JOIN brands b ON b.id = p.brand_id
        WHERE p.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'p') . "
          AND p.brand_id IS NOT NULL
          AND p.category_id IN (SELECT id FROM cat_subtree)
        ORDER BY b.name ASC"
    );
    $stmt->execute($slugParam);
    $fromProducts = $normalize($stmt->fetchAll() ?: []);
    if ($fromProducts !== []) {
        return $fromProducts;
    }

    $stmt = $pdo->query(
        "SELECT b.name
         FROM brands b
         LEFT JOIN products p ON p.brand_id = b.id AND p.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'p') . "
         GROUP BY b.id, b.name
         ORDER BY COUNT(p.id) DESC, b.name ASC"
    );
    $fallback = $normalize($stmt->fetchAll() ?: []);
    if ($fallback !== []) {
        return $fallback;
    }
    $stmt = $pdo->query("SELECT name FROM brands ORDER BY name ASC");
    return $normalize($stmt->fetchAll() ?: []);
}

/**
 * Marka siyahısını valideyn kateqoriya (məs. Smartfonlar, Planşetlər) üzrə qruplaşdırır;
 * yalnız `categories.brand_id` olan yarpaq sətirlərindən; icazə verilən brend adları ilə kəsişir.
 *
 * @param list<string> $allowedBrandNames
 * @return list<array{label: string, brands: list<array{name: string}>}>
 */
function maxhome_shop_brand_optgroups(
    PDO $pdo,
    bool $categoriesHasBrandIdColumn,
    string $categorySlug,
    array $allowedBrandNames
): array {
    if (!$categoriesHasBrandIdColumn || $allowedBrandNames === []) {
        return [];
    }
    $allow = array_fill_keys($allowedBrandNames, true);
    if ($categorySlug === '') {
        $stmt = $pdo->query(
            "SELECT
                p.id AS group_id,
                p.name AS group_label,
                p.sort_order AS group_sort,
                b.name AS brand_name,
                leaf.sort_order AS leaf_sort
             FROM categories leaf
             INNER JOIN categories p ON p.id = leaf.parent_id AND p.is_active = 1
             INNER JOIN brands b ON b.id = leaf.brand_id
             WHERE leaf.is_active = 1
               AND leaf.brand_id IS NOT NULL
             ORDER BY p.sort_order ASC, p.name ASC, leaf.sort_order ASC, b.name ASC"
        );
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $stmt = $pdo->prepare(
            "WITH RECURSIVE cat_subtree AS (
                SELECT id FROM categories WHERE slug = :slug AND is_active = 1
                UNION ALL
                SELECT c.id FROM categories c
                INNER JOIN cat_subtree s ON c.parent_id = s.id
                WHERE c.is_active = 1
            )
            SELECT
                p.id AS group_id,
                p.name AS group_label,
                p.sort_order AS group_sort,
                b.name AS brand_name,
                leaf.sort_order AS leaf_sort
            FROM categories leaf
            INNER JOIN cat_subtree st ON st.id = leaf.id
            INNER JOIN categories p ON p.id = leaf.parent_id AND p.is_active = 1
            INNER JOIN brands b ON b.id = leaf.brand_id
            WHERE leaf.is_active = 1
              AND leaf.brand_id IS NOT NULL
            ORDER BY p.sort_order ASC, p.name ASC, leaf.sort_order ASC, b.name ASC"
        );
        $stmt->execute(['slug' => $categorySlug]);
        $rows = $stmt->fetchAll() ?: [];
    }
    $groupOrder = [];
    $byId = [];
    foreach ($rows as $r) {
        $bname = (string) ($r['brand_name'] ?? '');
        if ($bname === '' || !isset($allow[$bname])) {
            continue;
        }
        $gid = (int) ($r['group_id'] ?? 0);
        if ($gid <= 0) {
            continue;
        }
        if (!isset($byId[$gid])) {
            $groupOrder[] = $gid;
            $byId[$gid] = [
                'label' => (string) ($r['group_label'] ?? ''),
                'brands' => [],
                'seen' => [],
            ];
        }
        if (isset($byId[$gid]['seen'][$bname])) {
            continue;
        }
        $byId[$gid]['seen'][$bname] = true;
        $byId[$gid]['brands'][] = ['name' => $bname];
    }
    $out = [];
    foreach ($groupOrder as $gid) {
        $g = $byId[$gid];
        if ($g['brands'] === []) {
            continue;
        }
        $out[] = [
            'label' => $g['label'],
            'brands' => $g['brands'],
        ];
    }

    return $out;
}

/**
 * Mega menyu linklərindəki category_slug-u mağaza filtri (root / group / child / brand) ilə uyğunlaşdırır.
 *
 * @param array<int, array<string, mixed>> $categoryById
 * @return array{root_slug:string, group_slug:string, child_slug:string, brand:string}
 */
function maxhome_resolve_category_slug_filters(
    string $categorySlug,
    array $categoryById,
    PDO $pdo,
    bool $categoriesHasBrandIdColumn
): array {
    $empty = [
        'root_slug' => '',
        'group_slug' => '',
        'child_slug' => '',
        'brand' => '',
    ];
    if ($categorySlug === '') {
        return $empty;
    }

    $bySlug = [];
    foreach ($categoryById as $row) {
        $s = (string) ($row['slug'] ?? '');
        if ($s !== '') {
            $bySlug[$s] = $row;
        }
    }
    if (!isset($bySlug[$categorySlug])) {
        return $empty;
    }

    $chain = [];
    $cur = $bySlug[$categorySlug];
    while (true) {
        array_unshift($chain, $cur);
        $pid = $cur['parent_id'] ?? null;
        if ($pid === null) {
            break;
        }
        $parent = $categoryById[(int) $pid] ?? null;
        if ($parent === null) {
            break;
        }
        $cur = $parent;
    }

    $len = count($chain);
    if ($len < 1) {
        return $empty;
    }

    $result = $empty;
    $result['root_slug'] = (string) ($chain[0]['slug'] ?? '');
    if ($len === 2) {
        $result['group_slug'] = (string) ($chain[1]['slug'] ?? '');
    } elseif ($len >= 3) {
        $result['group_slug'] = (string) ($chain[$len - 2]['slug'] ?? '');
        $result['child_slug'] = (string) ($chain[$len - 1]['slug'] ?? '');
    }

    $leaf = $bySlug[$categorySlug];
    if ($categoriesHasBrandIdColumn) {
        $brandId = $leaf['brand_id'] ?? null;
        if ($brandId !== null && $brandId !== '' && (int) $brandId > 0) {
            $stmt = $pdo->prepare('SELECT name FROM brands WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $brandId]);
            $bname = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($bname !== '') {
                $result['brand'] = $bname;
            }
        }
    } else {
        $result['brand'] = trim((string) ($leaf['name'] ?? ''));
    }

    return $result;
}

$pdo = db();
$flashMessage = '';
$flashType = 'success';
$selectedBrand = trim((string) ($_GET['brand'] ?? ''));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$selectedCategorySlug = trim((string) ($_GET['category_slug'] ?? '')); // legacy/backward compatibility
$selectedRootSlug = trim((string) ($_GET['root_slug'] ?? ''));
$selectedGroupSlug = trim((string) ($_GET['group_slug'] ?? ''));
$selectedChildSlug = trim((string) ($_GET['child_slug'] ?? ''));
$rawMinPrice = trim((string) ($_GET['min_price'] ?? ''));
$rawMaxPrice = trim((string) ($_GET['max_price'] ?? ''));
$minPrice = ($rawMinPrice !== '' && is_numeric($rawMinPrice)) ? (float) $rawMinPrice : null;
$maxPrice = ($rawMaxPrice !== '' && is_numeric($rawMaxPrice)) ? (float) $rawMaxPrice : null;

$allowedSortValues = ['newest', 'price_low', 'price_high', 'rating'];
$sortRaw = trim((string) ($_GET['sort'] ?? 'newest'));
$sort = in_array($sortRaw, $allowedSortValues, true) ? $sortRaw : 'newest';
$perPage = 51;
$page = max(1, (int) ($_GET['page'] ?? 1));

$categoriesHasBrandIdColumn = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'categories'
       AND COLUMN_NAME = 'brand_id'
     LIMIT 1"
)->fetchColumn();

$categorySelect = $categoriesHasBrandIdColumn
    ? 'id, parent_id, brand_id, name, slug, sort_order'
    : 'id, parent_id, name, slug, sort_order';

$categoryRows = $pdo->query(
    "SELECT {$categorySelect}
     FROM categories
     WHERE is_active = 1 AND slug <> ''
     ORDER BY sort_order ASC, name ASC"
)->fetchAll() ?: [];

/** @var array<int, array<string, mixed>> */
$categoryById = [];
foreach ($categoryRows as $row) {
    $categoryById[(int) $row['id']] = $row;
}

if (
    $selectedCategorySlug !== ''
    && $selectedRootSlug === ''
    && $selectedGroupSlug === ''
    && $selectedChildSlug === ''
) {
    $resolvedFromMega = maxhome_resolve_category_slug_filters(
        $selectedCategorySlug,
        $categoryById,
        $pdo,
        $categoriesHasBrandIdColumn
    );
    $selectedRootSlug = $resolvedFromMega['root_slug'];
    $selectedGroupSlug = $resolvedFromMega['group_slug'];
    $selectedChildSlug = $resolvedFromMega['child_slug'];
    if ($selectedBrand === '' && $resolvedFromMega['brand'] !== '') {
        $selectedBrand = $resolvedFromMega['brand'];
    }
    $selectedCategorySlug = '';
}

// Clean URL: yalnız filtr parametrlərini müqayisə et (PHPSESSID, utm_* və s. redirect döngəsinə səbəb olurdu).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $clean = [];

    if ($selectedBrand !== '') {
        $clean['brand'] = $selectedBrand;
    }
    if ($selectedRootSlug !== '') {
        $clean['root_slug'] = $selectedRootSlug;
    }
    if ($selectedGroupSlug !== '') {
        $clean['group_slug'] = $selectedGroupSlug;
    }
    if ($selectedChildSlug !== '') {
        $clean['child_slug'] = $selectedChildSlug;
    }
    if ($selectedCategory !== '') {
        $clean['category'] = $selectedCategory;
    }
    if ($minPrice !== null && $minPrice >= 0) {
        $clean['min_price'] = (string) $minPrice;
    }
    if ($maxPrice !== null && $maxPrice >= 0) {
        $clean['max_price'] = (string) $maxPrice;
    }
    if ($sort !== 'newest') {
        $clean['sort'] = $sort;
    }
    if ($page > 1) {
        $clean['page'] = (string) $page;
    }

    $filterQueryKeys = ['brand', 'category_slug', 'root_slug', 'group_slug', 'child_slug', 'category', 'min_price', 'max_price', 'sort', 'page'];
    $current = [];
    foreach ($filterQueryKeys as $key) {
        if (!isset($_GET[$key])) {
            continue;
        }
        $value = $_GET[$key];
        if (is_array($value)) {
            continue;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            continue;
        }
        if (($key === 'min_price' || $key === 'max_price') && is_numeric($trimmed)) {
            $trimmed = (string) (float) $trimmed;
        }
        if ($key === 'sort' && !in_array($trimmed, $allowedSortValues, true)) {
            continue;
        }
        if ($key === 'sort' && $trimmed === 'newest') {
            continue;
        }
        if ($key === 'page') {
            $pageNum = (int) $trimmed;
            if ($pageNum <= 1) {
                continue;
            }
            $trimmed = (string) $pageNum;
        }
        $current[$key] = $trimmed;
    }

    ksort($clean);
    ksort($current);
    if ($clean !== $current) {
        $target = 'shop_page.php';
        if (!empty($clean)) {
            $target .= '?' . http_build_query($clean);
        }
        header('Location: ' . $target);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 1);
        addToCart($pdo, $productId, $qty);
        header('Location: shopping_cart.php');
        exit;
    } catch (Throwable $e) {
        $flashMessage = $e->getMessage();
        $flashType = 'error';
    }
}

/** @var array<int, list<array<string, mixed>>> */
$categoriesByParent = [];
foreach ($categoryRows as $row) {
    $pid = $row['parent_id'];
    $key = $pid === null ? 0 : (int) $pid;
    $categoriesByParent[$key][] = $row;
}

if ($categoriesHasBrandIdColumn) {
    /** Brend yarpağı: categories.brand_id — filtr siyahısında göstərilmir. */
    $isBrandLeafCategory = static function (array $c): bool {
        $bid = $c['brand_id'] ?? null;

        return $bid !== null && $bid !== '' && (int) $bid > 0;
    };
} else {
    /**
     * Köhnə şema (brand_id yoxdur): valideyn slug siyahısı — migration_categories_brand_id.sql işlədəndən sonra DB yeniləyin.
     *
     * @var list<string>
     */
    $brandLeafParentSlugs = [
        'sf-smartfonlar',
        'pl-planshetler',
        'dt-duymeli-telefonlar',
        'eo-ev-ofis-telefonlari',
        'ek-elektron-kitablar',
        'ss-smart-saatlar',
    ];
    $isBrandLeafCategory = static function (array $c, array $categoryById) use ($brandLeafParentSlugs): bool {
        $pid = $c['parent_id'];
        if ($pid === null) {
            return false;
        }
        $parent = $categoryById[(int) $pid] ?? null;
        if ($parent === null) {
            return false;
        }
        $pslug = (string) ($parent['slug'] ?? '');
        $cslug = (string) ($c['slug'] ?? '');
        if ($pslug === 'ss-smart-saatlar' && $cslug === 'ss-smart-saatlar-aksesuarlar') {
            return false;
        }
        if ($pslug === 'oyun-konsollar-b-01-oyun-konsollari' && $cslug === 'gm-butun-konsol') {
            return false;
        }
        if (in_array($pslug, $brandLeafParentSlugs, true)) {
            return true;
        }
        if ($pslug === 'oyun-konsollar-b-01-oyun-konsollari') {
            return true;
        }

        return false;
    };
}

/** seed_az_catalog.sql-dəki ara qruplaşdırma düyünləri (-b-01-, -b-02- və s.); filtr siyahısında göstərilmir, alt kateqoriyalar bir səviyyə yuxarı qalır. */
$isStructuralCategoryWrapper = static function (array $c): bool {
    $slug = (string) ($c['slug'] ?? '');

    return (bool) preg_match('/-b-\d+-/', $slug);
};

/**
 * @param array<int, list<array<string, mixed>>> $byParent
 * @param array<int, array<string, mixed>> $categoryById
 * @param list<array{label:string, slug:string}> $out
 */
$flattenCategories = static function (
    array $byParent,
    array $categoryById,
    int $parentKey,
    int $depth,
    array &$out
) use (&$flattenCategories, $isBrandLeafCategory, $isStructuralCategoryWrapper, $categoriesHasBrandIdColumn): void {
    $children = $byParent[$parentKey] ?? [];
    usort(
        $children,
        static function (array $a, array $b): int {
            $so = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
            return $so !== 0 ? $so : strcmp((string) $a['name'], (string) $b['name']);
        }
    );
    foreach ($children as $c) {
        $slug = (string) ($c['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $isBrandLeaf = $categoriesHasBrandIdColumn
            ? $isBrandLeafCategory($c)
            : $isBrandLeafCategory($c, $categoryById);
        if ($isBrandLeaf) {
            continue;
        }
        if ($isStructuralCategoryWrapper($c)) {
            $flattenCategories($byParent, $categoryById, (int) $c['id'], $depth, $out);
            continue;
        }
        $name = (string) ($c['name'] ?? '');
        // Tire/em dash prefiks yox: yalnız boşluq (brauzerlərdə option padding etibarsızdır).
        $indent = $depth > 0 ? str_repeat("\u{2003}", $depth) : '';
        $out[] = [
            'label' => $indent . $name,
            'slug' => $slug,
        ];
        $flattenCategories($byParent, $categoryById, (int) $c['id'], $depth + 1, $out);
    }
};

$categoryFilterOptions = [];
$flattenCategories($categoriesByParent, $categoryById, 0, 0, $categoryFilterOptions);

/**
 * Admin paneldəki kimi: yalnız birbaşa alt kateqoriyalar.
 *
 * @return list<array{slug:string,label:string}>
 */
$collectDirectSubcategories = static function (int $rootId) use ($categoriesByParent): array {
    $children = $categoriesByParent[$rootId] ?? [];
    usort(
        $children,
        static function (array $a, array $b): int {
            $so = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
            return $so !== 0 ? $so : strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        }
    );
    $out = [];
    foreach ($children as $c) {
        $slug = (string) ($c['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $out[] = [
            'slug' => $slug,
            'label' => (string) ($c['name'] ?? $slug),
        ];
    }
    return $out;
};

/** @var list<array{slug:string,label:string}> $rootCategoryOptions */
$rootCategoryOptions = [];
foreach (($categoriesByParent[0] ?? []) as $root) {
    $rootSlug = (string) ($root['slug'] ?? '');
    if ($rootSlug === '') {
        continue;
    }
    $rootCategoryOptions[] = [
        'slug' => $rootSlug,
        'label' => (string) ($root['name'] ?? $rootSlug),
    ];
}

$rootExists = false;
foreach ($rootCategoryOptions as $r) {
    if ($r['slug'] === $selectedRootSlug) {
        $rootExists = true;
        break;
    }
}
if (!$rootExists) {
    $selectedRootSlug = '';
}

$selectedRootId = 0;
if ($selectedRootSlug !== '') {
    foreach (($categoriesByParent[0] ?? []) as $root) {
        if ((string) ($root['slug'] ?? '') === $selectedRootSlug) {
            $selectedRootId = (int) ($root['id'] ?? 0);
            break;
        }
    }
}

$groupOptionsForRoot = $selectedRootId > 0 ? $collectDirectSubcategories($selectedRootId) : [];
$groupAllowedSlugs = array_map(static fn(array $g): string => $g['slug'], $groupOptionsForRoot);
if ($selectedGroupSlug !== '' && !in_array($selectedGroupSlug, $groupAllowedSlugs, true)) {
    $selectedGroupSlug = '';
}

$categoryIdBySlug = [];
foreach ($categoryRows as $row) {
    $slug = (string) ($row['slug'] ?? '');
    $id = (int) ($row['id'] ?? 0);
    if ($slug !== '' && $id > 0) {
        $categoryIdBySlug[$slug] = $id;
    }
}

$selectedGroupId = $selectedGroupSlug !== '' ? (int) ($categoryIdBySlug[$selectedGroupSlug] ?? 0) : 0;
$childOptionsForGroup = $selectedGroupId > 0 ? $collectDirectSubcategories($selectedGroupId) : [];
$childAllowedSlugs = array_map(static fn(array $g): string => $g['slug'], $childOptionsForGroup);
if ($selectedChildSlug !== '' && !in_array($selectedChildSlug, $childAllowedSlugs, true)) {
    $selectedChildSlug = '';
}

$effectiveCategorySlug = $selectedChildSlug !== ''
    ? $selectedChildSlug
    : ($selectedGroupSlug !== ''
    ? $selectedGroupSlug
    : ($selectedRootSlug !== '' ? $selectedRootSlug : $selectedCategorySlug));

$brands = maxhome_shop_brands_for_category_slug($pdo, $effectiveCategorySlug, $categoriesHasBrandIdColumn);
$allowedBrandNamesForCategory = array_map(static fn(array $r): string => (string) ($r['name'] ?? ''), $brands);
if ($selectedBrand !== '' && !in_array($selectedBrand, $allowedBrandNamesForCategory, true)) {
    $selectedBrand = '';
}

// Dynamic chain filter datası (root -> groups, scope -> brands)
$groupOptionsByRootSlug = [];
foreach (($categoriesByParent[0] ?? []) as $rootRow) {
    $rSlug = (string) ($rootRow['slug'] ?? '');
    $rId = (int) ($rootRow['id'] ?? 0);
    if ($rSlug === '' || $rId <= 0) {
        continue;
    }
    $groupOptionsByRootSlug[$rSlug] = $collectDirectSubcategories($rId);
}

$childOptionsByGroupSlug = [];
foreach ($categoryRows as $row) {
    $slug = (string) ($row['slug'] ?? '');
    $id = (int) ($row['id'] ?? 0);
    if ($slug === '' || $id <= 0) {
        continue;
    }
    $childOptionsByGroupSlug[$slug] = $collectDirectSubcategories($id);
}

$brandsByScope = [
    '' => array_values(array_map(static fn(array $r): string => (string) ($r['name'] ?? ''), maxhome_shop_brands_for_category_slug($pdo, '', $categoriesHasBrandIdColumn))),
];
foreach ($rootCategoryOptions as $rootOpt) {
    $rSlug = (string) ($rootOpt['slug'] ?? '');
    if ($rSlug === '') {
        continue;
    }
    $brandsByScope[$rSlug] = array_values(array_map(static fn(array $r): string => (string) ($r['name'] ?? ''), maxhome_shop_brands_for_category_slug($pdo, $rSlug, $categoriesHasBrandIdColumn)));
    foreach (($groupOptionsByRootSlug[$rSlug] ?? []) as $gOpt) {
        $gSlug = (string) ($gOpt['slug'] ?? '');
        if ($gSlug === '') {
            continue;
        }
        $brandsByScope[$gSlug] = array_values(array_map(static fn(array $r): string => (string) ($r['name'] ?? ''), maxhome_shop_brands_for_category_slug($pdo, $gSlug, $categoriesHasBrandIdColumn)));
        foreach (($childOptionsByGroupSlug[$gSlug] ?? []) as $cOpt) {
            $cSlug = (string) ($cOpt['slug'] ?? '');
            if ($cSlug === '') {
                continue;
            }
            $brandsByScope[$cSlug] = array_values(array_map(static fn(array $r): string => (string) ($r['name'] ?? ''), maxhome_shop_brands_for_category_slug($pdo, $cSlug, $categoriesHasBrandIdColumn)));
        }
    }
}

$allowedSort = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_low' => 'p.base_price ASC, p.id DESC',
    'price_high' => 'p.base_price DESC, p.id DESC',
    'rating' => 'p.rating_avg DESC, p.rating_count DESC, p.id DESC',
];
$orderBy = $allowedSort[$sort] ?? $allowedSort['newest'];

$catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
$joinSql = "
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN categories c ON c.id = p.category_id";
$whereSql = " WHERE p.status = 'active'" . $catalogOnlineSql;
$params = [];

if ($selectedBrand !== '') {
    $whereSql .= " AND b.name = :brand";
    $params['brand'] = $selectedBrand;
}
if ($effectiveCategorySlug !== '') {
    $whereSql .= " AND p.category_id IN (
        WITH RECURSIVE cat_subtree AS (
            SELECT id FROM categories WHERE slug = :category_slug AND is_active = 1
            UNION ALL
            SELECT c.id FROM categories c
            INNER JOIN cat_subtree s ON c.parent_id = s.id
            WHERE c.is_active = 1
        )
        SELECT id FROM cat_subtree
    )";
    $params['category_slug'] = $effectiveCategorySlug;
} elseif ($selectedCategory !== '') {
    $whereSql .= " AND c.name = :category";
    $params['category'] = $selectedCategory;
}
if ($minPrice !== null && $minPrice >= 0) {
    $whereSql .= " AND p.base_price >= :min_price";
    $params['min_price'] = $minPrice;
}
if ($maxPrice !== null && $maxPrice >= 0) {
    $whereSql .= " AND p.base_price <= :max_price";
    $params['max_price'] = $maxPrice;
}
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    $tmp = $minPrice;
    $minPrice = $maxPrice;
    $maxPrice = $tmp;
    $params['min_price'] = $minPrice;
    $params['max_price'] = $maxPrice;
}

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $whereSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rangeStart = $totalProducts > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $perPage, $totalProducts);

$shopPaginationQuery = [];
if ($selectedBrand !== '') {
    $shopPaginationQuery['brand'] = $selectedBrand;
}
if ($selectedRootSlug !== '') {
    $shopPaginationQuery['root_slug'] = $selectedRootSlug;
}
if ($selectedGroupSlug !== '') {
    $shopPaginationQuery['group_slug'] = $selectedGroupSlug;
}
if ($selectedChildSlug !== '') {
    $shopPaginationQuery['child_slug'] = $selectedChildSlug;
}
if ($selectedCategory !== '') {
    $shopPaginationQuery['category'] = $selectedCategory;
}
if ($minPrice !== null && $minPrice >= 0) {
    $shopPaginationQuery['min_price'] = (string) $minPrice;
}
if ($maxPrice !== null && $maxPrice >= 0) {
    $shopPaginationQuery['max_price'] = (string) $maxPrice;
}
if ($sort !== 'newest') {
    $shopPaginationQuery['sort'] = $sort;
}

$shopPaginationUrl = static function (array $baseQuery, int $targetPage): string {
    $query = $baseQuery;
    if ($targetPage > 1) {
        $query['page'] = (string) $targetPage;
    }
    $qs = http_build_query($query);

    return 'shop_page.php' . ($qs !== '' ? '?' . $qs : '');
};

$sql = "SELECT
            p.id,
            p.slug,
            p.name,
            p.short_description,
            p.base_price,
            p.compare_at_price,
            p.rating_avg,
            p.rating_count,
            p.stock_qty,
            b.name AS brand_name,
            c.name AS category_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS image_url"
    . $joinSql
    . $whereSql
    . " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
$productsStmt = $pdo->prepare($sql);
$productsStmt->execute($params);
$products = $productsStmt->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>MAXHOME - Premium Elektronika Mağazası</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/shop_page.css">
    <link rel="stylesheet" href="assets/css/product_compare.css">
</head>

<body class="shop-is-loading">
    <?php $currentPage = 'shop page'; ?>
    <?php include 'navbar.php'; ?>
    <main class="container main-content">
        <header class="hero">
            <div>
                <nav class="breadcrumb">
                    <span>Mağaza</span> / <span>Kataloq</span> / <span>Bütün məhsullar</span>
                </nav>
                <h1 class="hero__title">Yeni Nəsil Mobil Texnologiya</h1>
                <p class="hero__description" hidden="true">Products are now served directly from database.</p>
            </div>
            <div class="product-count">
                <?php if ($totalProducts > 0): ?>
                    <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> / <?php echo $totalProducts; ?> məhsul
                    <?php if ($totalPages > 1): ?>
                        · Səhifə <?php echo $page; ?> / <?php echo $totalPages; ?>
                    <?php endif; ?>
                <?php else: ?>
                    0 məhsul tapıldı
                <?php endif; ?>
            </div>
        </header>

        <?php if ($flashMessage !== ''): ?>
            <div style="margin: 12px 0; padding: 12px; border-radius: 10px; color: #fff; background: <?php echo $flashType === 'error' ? '#b91c1c' : '#065f46'; ?>;">
                <?php echo e($flashMessage); ?>
            </div>
        <?php endif; ?>

        <div class="shop-layout">
            <aside class="sidebar">
                <form method="get" id="filter_form" class="filter-form">


                    <div class="filter-category-wrap" style="margin-top:14px;">
                        <h3 class="filter-section__title">Əsas kateqoriya</h3>
                        <select class="sort-select filter-category-select" id="root_slug" name="root_slug" style="width:100%;">
                            <option value="">Bütün kateqoriyalar</option>
                            <?php foreach ($rootCategoryOptions as $opt): ?>
                                <option value="<?php echo e($opt['slug']); ?>" <?php echo $selectedRootSlug === $opt['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-top:14px;">
                        <h3 class="filter-section__title">Alt kateqoriya</h3>
                        <select class="sort-select filter-category-select" id="group_slug" name="group_slug" style="width:100%;">
                            <option value="">Bütün alt kateqoriyalar</option>
                            <?php foreach ($groupOptionsForRoot as $opt): ?>
                                <option value="<?php echo e($opt['slug']); ?>" <?php echo $selectedGroupSlug === $opt['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-top:14px;">
                        <h3 class="filter-section__title">Alt-alt kateqoriya</h3>
                        <select class="sort-select filter-category-select" id="child_slug" name="child_slug" style="width:100%;">
                            <option value="">Bütün alt-alt kateqoriyalar</option>
                            <?php foreach ($childOptionsForGroup as $opt): ?>
                                <option value="<?php echo e($opt['slug']); ?>" <?php echo $selectedChildSlug === $opt['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <h3 class="filter-section__title">Marka</h3>
                        <select class="sort-select filter-brand-select" id="brand" name="brand" style="width:100%;">
                            <option value="">Bütün markalar</option>
                            <?php foreach ($brands as $brand): ?>
                                <?php $bname = (string) ($brand['name'] ?? ''); ?>
                                <option value="<?php echo e($bname); ?>" <?php echo $selectedBrand === $bname ? 'selected' : ''; ?>>
                                    <?php echo e($bname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-block" style="margin-top:14px;">
                        <h3 class="filter-section__title">Qiymət aralığı</h3>
                        <div style="display:flex; gap:8px;">
                            <input class="sort-select" name="min_price" type="number" min="0" step="0.01" placeholder="Min." value="<?php echo $minPrice !== null ? e((string) $minPrice) : ''; ?>" style="width:50%;">
                            <input class="sort-select" name="max_price" type="number" min="0" step="0.01" placeholder="Maks." value="<?php echo $maxPrice !== null ? e((string) $maxPrice) : ''; ?>" style="width:50%;">
                        </div>
                    </div>

                    <div style="margin-top:14px;">
                        <h3 class="filter-section__title">Sırala</h3>
                        <select class="sort-select" name="sort" style="width:100%;">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Ən yenilər</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Qiymət: Aşağıdan yuxarı</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Qiymət: Yuxarıdan aşağı</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Müştəri reytinqi</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:8px; margin-top:16px;">
                        <button class="btn-add" type="submit" style="padding:10px 12px;">Tətbiq et</button>
                        <a href="shop_page.php" class="btn-add" style="padding:10px 12px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">Sıfırla</a>
                    </div>
                </form>
            </aside>
            <div class="product-grid-container">
                <div class="shop-skeleton" aria-hidden="true">
                    <div class="shop-skeleton-grid">
                        <?php for ($skeletonIndex = 0; $skeletonIndex < 9; $skeletonIndex++): ?>
                            <article class="shop-skeleton-card">
                                <div class="shop-skeleton-block shop-skeleton-block--image"></div>
                                <div class="shop-skeleton-card__body">
                                    <div class="shop-skeleton-block shop-skeleton-block--title"></div>
                                    <div class="shop-skeleton-block shop-skeleton-block--subtitle"></div>
                                    <div class="shop-skeleton-block shop-skeleton-block--desc"></div>
                                    <div class="shop-skeleton-block shop-skeleton-block--desc-short"></div>
                                    <div class="shop-skeleton-card__footer">
                                        <div class="shop-skeleton-block shop-skeleton-block--price"></div>
                                        <div class="shop-skeleton-block shop-skeleton-block--btn"></div>
                                    </div>
                                </div>
                            </article>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="shop-content">
                <div class="product-grid">
                    <?php if (empty($products)): ?>
                        <p style="padding: 24px; color: #475569;">Aktiv məhsul tapılmadı.</p>
                    <?php else: ?>
                        <?php $shopPlaceholderImage = 'assets/defaultP.jpg'; ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $productImage = trim((string) ($product['image_url'] ?? ''));
                            $cardImageSrc = $productImage !== '' ? $productImage : $shopPlaceholderImage;
                            $imgClass = 'product-card__img product-card__img--primary' . ($productImage === '' ? ' product-card__img--placeholder' : '');
                            $cardBasePrice = (float) $product['base_price'];
                            $cardComparePrice = (float) ($product['compare_at_price'] ?? 0);
                            $cardListPrice = $cardComparePrice > $cardBasePrice ? $cardComparePrice : null;
                            ?>
                            <div
                                class="product-card js-product-card"
                                data-href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>"
                                role="link"
                                tabindex="0">
                                <div class="product-card__image-box">
                                    <button
                                        class="product-compare-btn js-product-compare-btn"
                                        type="button"
                                        aria-label="Müqayisə et"
                                        data-product-id="<?php echo (int) $product['id']; ?>"
                                        data-product-slug="<?php echo e((string) $product['slug']); ?>"
                                        data-product-name="<?php echo e((string) $product['name']); ?>">
                                        <span class="material-symbols-outlined">balance</span>
                                    </button>
                                    <img
                                        alt="<?php echo e($product['name']); ?>"
                                        class="<?php echo e($imgClass); ?>"
                                        src="<?php echo e($cardImageSrc); ?>"
                                        <?php if ($productImage !== ''): ?>
                                            onerror="this.onerror=null;this.src='<?php echo e($shopPlaceholderImage); ?>';"
                                        <?php endif; ?> />
                                    <div class="badge badge--primary"><?php echo e($product['brand_name'] ?: 'MAXHOME'); ?></div>
                                </div>
                                <div class="product-card__content">
                                    <div class="product-card__header">
                                        <h3 class="product-card__title"><?php echo e($product['name']); ?></h3>
                                        <p class="product-card__subtitle"><?php echo e($product['category_name'] ?: 'Ümumi'); ?></p>
                                    </div>
                                    <div class="rating">
                                        <span class="review-count">
                                            Reytinq: <?php echo number_format((float) $product['rating_avg'], 1); ?> (<?php echo (int) $product['rating_count']; ?>)
                                        </span>
                                    </div>
                                    <p class="product-card__desc">
                                        <?php echo e(maxhomeProductPlainText((string) ($product['short_description'] ?: 'Premium keyfiyyətli məhsul.'))); ?>
                                    </p>
                                    <div class="product-card__meta-row">
                                        <a
                                            class="product-card__link"
                                            href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                                            Ətraflı
                                        </a>
                                        <div class="product-card__price-block">
                                            <?php if ($cardListPrice !== null): ?>
                                                <span class="product-card__price-compare"><?php echo number_format($cardListPrice, 2); ?> ₼</span>
                                            <?php endif; ?>
                                            <span class="product-card__price<?php echo $cardListPrice !== null ? ' product-card__price--sale' : ''; ?>">
                                                <?php echo number_format($cardBasePrice, 2); ?> ₼
                                            </span>
                                        </div>
                                    </div>
                                    <form method="post" class="product-card__cart-form">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button class="btn-add" type="submit" name="add_to_cart" <?php echo (int) $product['stock_qty'] < 1 ? 'disabled' : ''; ?>>
                                            <span class="material-symbols-outlined" style="font-size: 14px;">shopping_bag</span>
                                            <?php echo (int) $product['stock_qty'] < 1 ? 'Stokda yoxdur' : 'Səbətə əlavə et'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Məhsul səhifələri">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="<?php echo e($shopPaginationUrl($shopPaginationQuery, $page - 1)); ?>" aria-label="Əvvəlki səhifə">
                                <span class="material-symbols-outlined" style="font-size: 20px;">chevron_left</span>
                            </a>
                        <?php else: ?>
                            <span class="page-btn" style="opacity: 0.4; pointer-events: none;" aria-hidden="true">
                                <span class="material-symbols-outlined" style="font-size: 20px;">chevron_left</span>
                            </span>
                        <?php endif; ?>

                        <?php
                        $pageWindow = 2;
                        $pagesToShow = [];
                        $pagesToShow[] = 1;
                        for ($p = max(2, $page - $pageWindow); $p <= min($totalPages - 1, $page + $pageWindow); $p++) {
                            $pagesToShow[] = $p;
                        }
                        if ($totalPages > 1) {
                            $pagesToShow[] = $totalPages;
                        }
                        $pagesToShow = array_values(array_unique($pagesToShow));
                        sort($pagesToShow);
                        $prevShown = 0;
                        foreach ($pagesToShow as $pNum):
                            if ($prevShown > 0 && $pNum - $prevShown > 1): ?>
                                <span class="page-dots">…</span>
                            <?php endif;
                            $prevShown = $pNum;
                            if ($pNum === $page): ?>
                                <span class="page-btn page-btn--active" aria-current="page"><?php echo $pNum; ?></span>
                            <?php else: ?>
                                <a class="page-btn" href="<?php echo e($shopPaginationUrl($shopPaginationQuery, $pNum)); ?>"><?php echo $pNum; ?></a>
                            <?php endif;
                        endforeach;
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="page-btn" href="<?php echo e($shopPaginationUrl($shopPaginationQuery, $page + 1)); ?>" aria-label="Növbəti səhifə">
                                <span class="material-symbols-outlined" style="font-size: 20px;">chevron_right</span>
                            </a>
                        <?php else: ?>
                            <span class="page-btn" style="opacity: 0.4; pointer-events: none;" aria-hidden="true">
                                <span class="material-symbols-outlined" style="font-size: 20px;">chevron_right</span>
                            </span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script>
    (function () {
        var body = document.body;

        function showContent() {
            body.classList.remove('shop-is-loading');
        }

        function showSkeleton() {
            body.classList.add('shop-is-loading');
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showContent);
        } else {
            showContent();
        }

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                showContent();
            }
        });

        var filterForm = document.getElementById('filter_form');
        if (filterForm) {
            filterForm.addEventListener('submit', showSkeleton);
        }

        document.querySelectorAll('.pagination a[href]').forEach(function (link) {
            link.addEventListener('click', showSkeleton);
        });
    })();
    </script>
    <script>
        (function () {
            var filterForm = document.getElementById('filter_form');
            var rootSelect = document.getElementById('root_slug');
            var groupSelect = document.getElementById('group_slug');
            var childSelect = document.getElementById('child_slug');
            var brandSelect = document.getElementById('brand');
            if (!filterForm) {
                filterForm = document.querySelector('aside.sidebar form[method="get"]');
            }
            var sortSelect = filterForm ? filterForm.querySelector('select[name="sort"]') : null;
            var minPriceInput = filterForm ? filterForm.querySelector('input[name="min_price"]') : null;
            var maxPriceInput = filterForm ? filterForm.querySelector('input[name="max_price"]') : null;
            if (!filterForm || !rootSelect || !groupSelect || !childSelect || !brandSelect) {
                return;
            }

            var groupsByRoot = <?php echo json_encode($groupOptionsByRootSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var childrenByGroup = <?php echo json_encode($childOptionsByGroupSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var brandsByScope = <?php echo json_encode($brandsByScope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var initialGroup = <?php echo json_encode($selectedGroupSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var initialChild = <?php echo json_encode($selectedChildSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var initialBrand = <?php echo json_encode($selectedBrand, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            function resetOptions(selectEl, placeholderText) {
                selectEl.innerHTML = '';
                var first = document.createElement('option');
                first.value = '';
                first.textContent = placeholderText;
                selectEl.appendChild(first);
            }

            function fillGroups(selectedRoot, preferredGroup) {
                resetOptions(groupSelect, 'Bütün alt kateqoriyalar');
                var groups = groupsByRoot[selectedRoot] || [];
                groups.forEach(function (g) {
                    var opt = document.createElement('option');
                    opt.value = g.slug || '';
                    opt.textContent = g.label || g.slug || '';
                    if (preferredGroup && preferredGroup === opt.value) {
                        opt.selected = true;
                    }
                    groupSelect.appendChild(opt);
                });
            }

            function fillChildren(selectedGroup, preferredChild) {
                resetOptions(childSelect, 'Bütün alt-alt kateqoriyalar');
                var children = childrenByGroup[selectedGroup] || [];
                children.forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = c.slug || '';
                    opt.textContent = c.label || c.slug || '';
                    if (preferredChild && preferredChild === opt.value) {
                        opt.selected = true;
                    }
                    childSelect.appendChild(opt);
                });
            }

            function fillBrands(scopeSlug, preferredBrand) {
                resetOptions(brandSelect, 'Bütün markalar');
                var brands = brandsByScope[scopeSlug] || brandsByScope[''] || [];
                brands.forEach(function (name) {
                    if (!name) {
                        return;
                    }
                    var opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    if (preferredBrand && preferredBrand === name) {
                        opt.selected = true;
                    }
                    brandSelect.appendChild(opt);
                });
            }

            function currentScope() {
                return childSelect.value || groupSelect.value || rootSelect.value || '';
            }

            var submitTimer = null;
            function submitFilters() {
                if (submitTimer) {
                    clearTimeout(submitTimer);
                    submitTimer = null;
                }
                if (typeof filterForm.requestSubmit === 'function') {
                    filterForm.requestSubmit();
                    return;
                }
                filterForm.submit();
            }

            function submitFiltersDebounced(delay) {
                if (submitTimer) {
                    clearTimeout(submitTimer);
                }
                submitTimer = setTimeout(submitFilters, delay);
            }

            rootSelect.addEventListener('change', function () {
                fillGroups(rootSelect.value, '');
                fillChildren('', '');
                fillBrands(currentScope(), '');
                submitFilters();
            });

            groupSelect.addEventListener('change', function () {
                fillChildren(groupSelect.value, '');
                fillBrands(currentScope(), '');
                submitFilters();
            });

            childSelect.addEventListener('change', function () {
                fillBrands(currentScope(), '');
                submitFilters();
            });

            brandSelect.addEventListener('change', submitFilters);
            if (sortSelect) {
                sortSelect.addEventListener('change', submitFilters);
            }

            [minPriceInput, maxPriceInput].forEach(function (inputEl) {
                if (!inputEl) {
                    return;
                }
                inputEl.addEventListener('input', function () {
                    submitFiltersDebounced(550);
                });
                inputEl.addEventListener('change', submitFilters);
            });

            fillGroups(rootSelect.value, initialGroup || '');
            fillChildren(groupSelect.value, initialChild || '');
            fillBrands(currentScope(), initialBrand || '');
        })();
    </script>
    <script>
        (function () {
            var cards = document.querySelectorAll('.js-product-card');
            if (!cards.length) {
                return;
            }

            function isInteractiveElement(target) {
                return !!target.closest('a, button, input, select, textarea, label, form');
            }

            cards.forEach(function (card) {
                var targetUrl = card.getAttribute('data-href');
                if (!targetUrl) {
                    return;
                }

                card.addEventListener('click', function (event) {
                    if (isInteractiveElement(event.target)) {
                        return;
                    }
                    window.location.href = targetUrl;
                });

                card.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    event.preventDefault();
                    window.location.href = targetUrl;
                });
            });
        })();
    </script>
    <script src="assets/js/product_compare.js"></script>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer__grid">
            <div>
                <span class="footer__brand">MAXHOME</span>
                <p class="footer__desc">Şəxsi hesablamada və inteqrə olunmuş rəqəmsal həyat tərzi təcrübələrində yeni dövrü formalaşdırırıq.</p>
            </div>
            <div>
                <h4 class="footer__heading">Mağaza</h4>
                <ul class="footer__list">
                    <li><a class="footer__link" href="shop_page.php">Yeni gələnlər</a></li>
                    <li><a class="footer__link" href="shop_page.php">Ən çox satılanlar</a></li>
                    <li><a class="footer__link" href="shop_page.php">Eksklüziv seriyalar</a></li>
                    <li><a class="footer__link" href="shop_page.php">Yenilənmiş məhsullar</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer__heading">Dəstək</h4>
                <ul class="footer__list">
                    <li><a class="footer__link" href="support_center.php">Çatdırılma</a></li>
                    <li><a class="footer__link" href="support_center.php">Geri qaytarma</a></li>
                    <li><a class="footer__link" href="support_center.php">Sifarişi izlə</a></li>
                    <li><a class="footer__link" href="support_center.php">Rəqəmsal konsyerj</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer__heading">Xəbər bülleteni</h4>
                <p class="footer__desc" style="margin-bottom: 1rem;">Erkən çıxış və texnoloji yeniliklər üçün daxili icmamıza qoşulun.</p>
                <div class="newsletter-form">
                    <input class="newsletter-input" placeholder="E-poçt ünvanı" type="email" />
                    <button class="newsletter-btn">
                        <span class="material-symbols-outlined" style="font-size: 14px;">send</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="footer__bottom">
            <span class="footer__copy">© 2024 MAXHOME Electronics. Rəqəmsal konsyerj təcrübəsi.</span>
            <div class="footer__legal">
                <a class="legal-link" href="support_center.php">Etibar mərkəzi</a>
                <a class="legal-link" href="support_center.php">Məxfilik siyasəti</a>
            </div>
        </div>
    </footer>
</body>

</html>