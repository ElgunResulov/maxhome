<?php
/**
 * Navbar mega menyu: sol əsas kateqoriyalar və hər biri üçün alt başlıqlar.
 * Linklər shop_page.php?category_slug=... ilə uyğun gəlir (seed_az_catalog.sql).
 */
declare(strict_types=1);

function maxhome_mega_shop_url(?string $slug = null): string
{
    if ($slug === null || $slug === '') {
        return 'shop_page.php';
    }
    return 'shop_page.php?' . http_build_query(['category_slug' => $slug]);
}

/** @var array<string, string> */
function maxhome_mega_root_icon_map(): array
{
    return [
        'smartfonlar-ve-planshetler' => 'smartphone',
        'noutbuklar-ve-komp' => 'laptop_mac',
        'oyun-konsollar' => 'sports_esports',
        'tv-audio-video' => 'tv',
        'boyuk-meiset' => 'kitchen',
        'kicik-meiset' => 'nutrition',
        'iqlim-texnikasi' => 'ac_unit',
        'gozellik-baxim' => 'health_and_beauty',
        'tibbi-mehsullar' => 'medical_services',
        'ev-bag' => 'chair',
        'mebel-tekstil-dekor' => 'weekend',
        'qab-qacaq' => 'restaurant',
    ];
}

function maxhome_mega_root_icon(string $slug): string
{
    return maxhome_mega_root_icon_map()[$slug] ?? 'category';
}

function maxhome_mega_is_structural_category(array $category): bool
{
    return (bool) preg_match('/-b-\d+-/', (string) ($category['slug'] ?? ''));
}

/**
 * @param array<string, mixed> $category
 * @param array<int, array<string, mixed>> $categoryById
 */
function maxhome_mega_is_brand_leaf_category(array $category, array $categoryById, bool $hasBrandIdColumn): bool
{
    if ($hasBrandIdColumn) {
        $brandId = $category['brand_id'] ?? null;

        return $brandId !== null && $brandId !== '' && (int) $brandId > 0;
    }

    $brandLeafParentSlugs = [
        'sf-smartfonlar',
        'pl-planshetler',
        'dt-duymeli-telefonlar',
        'eo-ev-ofis-telefonlari',
        'ek-elektron-kitablar',
        'ss-smart-saatlar',
    ];
    $parentId = $category['parent_id'] ?? null;
    if ($parentId === null) {
        return false;
    }
    $parent = $categoryById[(int) $parentId] ?? null;
    if ($parent === null) {
        return false;
    }
    $parentSlug = (string) ($parent['slug'] ?? '');
    $categorySlug = (string) ($category['slug'] ?? '');
    if ($parentSlug === 'ss-smart-saatlar' && $categorySlug === 'ss-smart-saatlar-aksesuarlar') {
        return false;
    }
    if ($parentSlug === 'oyun-konsollar-b-01-oyun-konsollari' && $categorySlug === 'gm-butun-konsol') {
        return false;
    }
    if (in_array($parentSlug, $brandLeafParentSlugs, true)) {
        return true;
    }

    return $parentSlug === 'oyun-konsollar-b-01-oyun-konsollari';
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, list<array<string, mixed>>>
 */
function maxhome_mega_categories_by_parent(array $rows): array
{
    $byParent = [];
    foreach ($rows as $row) {
        $pid = $row['parent_id'] ?? null;
        $key = $pid === null ? 0 : (int) $pid;
        $byParent[$key][] = $row;
    }

    return $byParent;
}

/**
 * @param array<int, list<array<string, mixed>>> $byParent
 * @return list<array<string, mixed>>
 */
function maxhome_mega_sorted_children(array $byParent, int $parentId): array
{
    $children = $byParent[$parentId] ?? [];
    usort(
        $children,
        static function (array $a, array $b): int {
            $sortOrder = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
            if ($sortOrder !== 0) {
                return $sortOrder;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        }
    );

    return $children;
}

/**
 * @param list<array{title:string, links:list<array{label:string, slug:string}>}> $blocks
 * @return array<int, list<array{title:string, links:list<array{label:string, slug:string}>}>>
 */
function maxhome_mega_distribute_columns(array $blocks, int $columnCount = 4): array
{
    $columns = array_fill(0, $columnCount, []);
    if ($blocks === []) {
        return $columns;
    }
    foreach ($blocks as $index => $block) {
        $columns[$index % $columnCount][] = $block;
    }

    return $columns;
}

/**
 * @param array<int, array<string, mixed>> $categoryById
 * @param array<string, int> $categoryIdBySlug
 */
/**
 * Admin panel ilə eyni: kökün birbaşa uşaqları = alt kateqoriya (h4).
 *
 * @return list<array{title:string, links:list<array{label:string, slug:string}>}>
 */
function maxhome_mega_collect_alt_blocks(
    int $rootId,
    array $byParent,
    array $categoryById,
    bool $hasBrandIdColumn,
    callable $collectAltAltLinks
): array {
    $blocks = [];
    foreach (maxhome_mega_sorted_children($byParent, $rootId) as $child) {
        if (maxhome_mega_is_brand_leaf_category($child, $categoryById, $hasBrandIdColumn)) {
            continue;
        }
        $childId = (int) ($child['id'] ?? 0);
        if ($childId <= 0) {
            continue;
        }
        $blocks[] = [
            'title' => (string) ($child['name'] ?? ''),
            'links' => $collectAltAltLinks($childId),
        ];
    }

    return $blocks;
}

/**
 * Alt kateqoriyanın birbaşa uşaqları = alt-alt kateqoriya (link).
 *
 * @return list<array{label:string, slug:string}>
 */
function maxhome_mega_collect_alt_alt_links(
    int $altCategoryId,
    array $byParent
): array {
    $links = [];
    foreach (maxhome_mega_sorted_children($byParent, $altCategoryId) as $child) {
        if (maxhome_mega_is_structural_category($child)) {
            foreach (maxhome_mega_collect_alt_alt_links((int) $child['id'], $byParent) as $link) {
                $links[] = $link;
            }
            continue;
        }
        $slug = (string) ($child['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $links[] = [
            'label' => (string) ($child['name'] ?? $slug),
            'slug' => $slug,
        ];
    }

    return $links;
}

function maxhome_mega_find_group_id_for_block(
    array $block,
    int $rootId,
    array $categoryById,
    array $categoryIdBySlug
): int {
    foreach ($block['links'] ?? [] as $link) {
        $slug = (string) ($link['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $cur = $categoryIdBySlug[$slug] ?? 0;
        while ($cur > 0) {
            $row = $categoryById[$cur] ?? null;
            if ($row === null) {
                break;
            }
            if ((int) ($row['parent_id'] ?? 0) === $rootId) {
                return $cur;
            }
            $cur = (int) ($row['parent_id'] ?? 0);
        }
    }

    $title = trim((string) ($block['title'] ?? ''));
    if ($title === '') {
        return 0;
    }
    foreach ($categoryById as $row) {
        if ((int) ($row['parent_id'] ?? 0) !== $rootId) {
            continue;
        }
        if (strcasecmp((string) ($row['name'] ?? ''), $title) === 0) {
            return (int) ($row['id'] ?? 0);
        }
    }

    return 0;
}

/**
 * Dizayn şablonundakı sütun quruluşunu saxlayır, məzmunu DB-dən doldurur.
 *
 * @param array<int, list<array{title:string, links:list<array{label:string, slug:?string}>}>> $staticPanel
 * @return array<int, list<array{title:string, links:list<array{label:string, slug:string}>}>>
 */
function maxhome_mega_hydrate_panel(
    array $staticPanel,
    int $rootId,
    array $categoryById,
    array $categoryIdBySlug,
    array $byParent,
    bool $hasBrandIdColumn,
    callable $collectLinks
): array {
    $hydrated = [];
    $usedGroupIds = [];

    foreach ($staticPanel as $col) {
        $newCol = [];
        foreach ($col as $block) {
            $groupId = maxhome_mega_find_group_id_for_block($block, $rootId, $categoryById, $categoryIdBySlug);
            if ($groupId > 0) {
                $usedGroupIds[$groupId] = true;
                $group = $categoryById[$groupId] ?? null;
                $links = $collectLinks($groupId);
                $newCol[] = [
                    'title' => (string) (($group['name'] ?? null) ?: ($block['title'] ?? '')),
                    'links' => $links !== [] ? $links : array_map(
                        static fn(array $link): array => [
                            'label' => (string) ($link['label'] ?? ''),
                            'slug' => (string) ($link['slug'] ?? ''),
                        ],
                        $block['links'] ?? []
                    ),
                ];
            } else {
                $newCol[] = [
                    'title' => (string) ($block['title'] ?? ''),
                    'links' => array_map(
                        static fn(array $link): array => [
                            'label' => (string) ($link['label'] ?? ''),
                            'slug' => (string) ($link['slug'] ?? ''),
                        ],
                        $block['links'] ?? []
                    ),
                ];
            }
        }
        $hydrated[] = $newCol;
    }

    $extraBlocks = [];
    foreach (maxhome_mega_collect_alt_blocks($rootId, $byParent, $categoryById, $hasBrandIdColumn, $collectLinks) as $block) {
        $groupId = maxhome_mega_find_group_id_for_block(
            ['title' => $block['title'], 'links' => $block['links']],
            $rootId,
            $categoryById,
            $categoryIdBySlug
        );
        if ($groupId > 0 && isset($usedGroupIds[$groupId])) {
            continue;
        }
        $extraBlocks[] = $block;
    }

    if ($extraBlocks !== []) {
        $columnCount = max(1, count($hydrated));
        foreach ($extraBlocks as $index => $block) {
            $hydrated[$index % $columnCount][] = $block;
        }
    }

    return $hydrated;
}

/**
 * Statik dizayn şablonu + DB məzmunu (sütunlar referans maket kimidir).
 *
 * @return list<array{icon:string,label:string,slug:string,panel:array<int, list<array{title:string, links:list<array{label:string, slug:string}>}>>}>
 */
function maxhome_mega_menu_panels_hydrated(PDO $pdo): array
{
    $staticPanels = maxhome_mega_menu_panels_static();
    $fromDb = maxhome_mega_menu_panels_load_category_tree($pdo);
    if ($fromDb === null) {
        return $staticPanels;
    }

    [
        'byParent' => $byParent,
        'categoryById' => $categoryById,
        'categoryIdBySlug' => $categoryIdBySlug,
        'hasBrandIdColumn' => $hasBrandIdColumn,
        'collectLinks' => $collectLinks,
        'collectBlocks' => $collectBlocks,
        'rootSlugToId' => $rootSlugToId,
        'rootSlugToName' => $rootSlugToName,
    ] = $fromDb;

    $knownRootSlugs = [];
    $panels = [];

    foreach ($staticPanels as $main) {
        $rootSlug = (string) ($main['slug'] ?? '');
        if ($rootSlug === '') {
            continue;
        }
        $knownRootSlugs[$rootSlug] = true;
        $rootId = (int) ($rootSlugToId[$rootSlug] ?? 0);
        $panel = $rootId > 0
            ? maxhome_mega_hydrate_panel(
                $main['panel'],
                $rootId,
                $categoryById,
                $categoryIdBySlug,
                $byParent,
                $hasBrandIdColumn,
                $collectLinks
            )
            : $main['panel'];

        $panels[] = [
            'icon' => maxhome_mega_root_icon($rootSlug),
            'label' => (string) ($rootSlugToName[$rootSlug] ?? $main['label']),
            'slug' => $rootSlug,
            'panel' => $panel,
        ];
    }

    foreach (maxhome_mega_sorted_children($byParent, 0) as $root) {
        $slug = (string) ($root['slug'] ?? '');
        if ($slug === '' || isset($knownRootSlugs[$slug])) {
            continue;
        }
        $rootId = (int) ($root['id'] ?? 0);
        if ($rootId <= 0) {
            continue;
        }
        $panels[] = [
            'icon' => maxhome_mega_root_icon($slug),
            'label' => (string) ($root['name'] ?? $slug),
            'slug' => $slug,
            'panel' => maxhome_mega_distribute_columns($collectBlocks($rootId)),
        ];
    }

    return $panels;
}

/**
 * @return array{
 *   byParent: array<int, list<array<string, mixed>>>,
 *   categoryById: array<int, array<string, mixed>>,
 *   categoryIdBySlug: array<string, int>,
 *   hasBrandIdColumn: bool,
 *   collectLinks: callable,
 *   collectBlocks: callable,
 *   rootSlugToId: array<string, int>,
 *   rootSlugToName: array<string, string>
 * }|null
 */
function maxhome_mega_menu_panels_load_category_tree(PDO $pdo): ?array
{
    $hasBrandIdColumn = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'categories'
           AND COLUMN_NAME = 'brand_id'
         LIMIT 1"
    )->fetchColumn();

    $categorySelect = $hasBrandIdColumn
        ? 'id, parent_id, brand_id, name, slug, sort_order'
        : 'id, parent_id, name, slug, sort_order';

    $categoryRows = $pdo->query(
        "SELECT {$categorySelect}
         FROM categories
         WHERE is_active = 1 AND slug <> ''
         ORDER BY sort_order ASC, name ASC"
    )->fetchAll() ?: [];

    if ($categoryRows === []) {
        return null;
    }

    /** @var array<int, array<string, mixed>> $categoryById */
    $categoryById = [];
    /** @var array<string, int> $categoryIdBySlug */
    $categoryIdBySlug = [];
    foreach ($categoryRows as $row) {
        $id = (int) $row['id'];
        $categoryById[$id] = $row;
        $slug = (string) ($row['slug'] ?? '');
        if ($slug !== '') {
            $categoryIdBySlug[$slug] = $id;
        }
    }

    $byParent = maxhome_mega_categories_by_parent($categoryRows);

    /** @var array<string, int> $rootSlugToId */
    $rootSlugToId = [];
    /** @var array<string, string> $rootSlugToName */
    $rootSlugToName = [];
    foreach (maxhome_mega_sorted_children($byParent, 0) as $root) {
        $slug = (string) ($root['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $rootSlugToId[$slug] = (int) ($root['id'] ?? 0);
        $rootSlugToName[$slug] = (string) ($root['name'] ?? $slug);
    }

    $collectLinks = static fn(int $altCategoryId): array => maxhome_mega_collect_alt_alt_links($altCategoryId, $byParent);

    $collectBlocks = static fn(int $rootId): array => maxhome_mega_collect_alt_blocks(
        $rootId,
        $byParent,
        $categoryById,
        $hasBrandIdColumn,
        $collectLinks
    );

    return [
        'byParent' => $byParent,
        'categoryById' => $categoryById,
        'categoryIdBySlug' => $categoryIdBySlug,
        'hasBrandIdColumn' => $hasBrandIdColumn,
        'collectLinks' => $collectLinks,
        'collectBlocks' => $collectBlocks,
        'rootSlugToId' => $rootSlugToId,
        'rootSlugToName' => $rootSlugToName,
    ];
}

/** @return list<array{icon:string,label:string,slug:?string,panel:array<int, array<int, array{title:string, links: list<array{label:string, slug:?string}>}>>}> */
function maxhome_mega_menu_panels(): array
{
    try {
        require_once __DIR__ . '/../db.php';
        return maxhome_mega_menu_panels_hydrated(db());
    } catch (Throwable $e) {
        return maxhome_mega_menu_panels_static();
    }
}

/** @return list<array{icon:string,label:string,slug:?string,panel:array<int, array<int, array{title:string, links: list<array{label:string, slug:?string}>}>>}> */
function maxhome_mega_menu_panels_static(): array
{
    return [
        [
            'icon' => 'smartphone',
            'label' => 'Smartfonlar və planşetlər',
            'slug' => 'smartfonlar-ve-planshetler',
            'panel' => [
                [
                    [
                        'title' => 'Smartfonlar',
                        'links' => [
                            ['label' => 'Apple', 'slug' => 'sf-smartfonlar-apple'],
                            ['label' => 'Samsung', 'slug' => 'sf-smartfonlar-samsung'],
                            ['label' => 'Xiaomi', 'slug' => 'sf-smartfonlar-xiaomi'],
                            ['label' => 'Huawei', 'slug' => 'sf-smartfonlar-huawei'],
                            ['label' => 'Motorola', 'slug' => 'sf-smartfonlar-motorola'],
                            ['label' => 'Honor', 'slug' => 'sf-smartfonlar-honor'],
                            ['label' => 'Poco', 'slug' => 'sf-smartfonlar-poco'],
                            ['label' => 'Infinix', 'slug' => 'sf-smartfonlar-infinix'],
                            ['label' => 'Tecno', 'slug' => 'sf-smartfonlar-tecno'],
                            ['label' => 'Vivo', 'slug' => 'sf-smartfonlar-vivo'],
                            ['label' => 'Nokia', 'slug' => 'sf-smartfonlar-nokia'],
                            ['label' => 'Bütün brendlər', 'slug' => 'sf-smartfonlar-butun-brendler'],
                        ],
                    ],
                    [
                        'title' => 'Elektron kitablar',
                        'links' => [
                            ['label' => 'PocketBook', 'slug' => 'ek-pocketbook'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Planşetlər',
                        'links' => [
                            ['label' => 'Apple', 'slug' => 'pl-planshetler-apple'],
                            ['label' => 'Samsung', 'slug' => 'pl-planshetler-samsung'],
                            ['label' => 'Xiaomi', 'slug' => 'pl-planshetler-xiaomi'],
                            ['label' => 'Lenovo', 'slug' => 'pl-planshetler-lenovo'],
                            ['label' => 'Huawei', 'slug' => 'pl-planshetler-huawei'],
                            ['label' => 'Poco', 'slug' => 'pl-planshetler-poco'],
                            ['label' => 'Bütün brendlər', 'slug' => 'pl-planshetler-butun-brendler'],
                        ],
                    ],
                    [
                        'title' => 'Qulaqlıqlar',
                        'links' => [
                            ['label' => 'Simsiz qulaqlıqlar', 'slug' => 'ql-simsiz'],
                            ['label' => 'Simli qulaqlıqlar', 'slug' => 'ql-simli'],
                            ['label' => 'Uşaqlar üçün qulaqlıqlar', 'slug' => 'ql-usaq'],
                            ['label' => 'Oyun üçün qulaqlıqlar', 'slug' => 'ql-oyun'],
                            ['label' => 'Bütün qulaqlıqlar', 'slug' => 'ql-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Düyməli telefonlar',
                        'links' => [
                            ['label' => 'Energizer', 'slug' => 'dt-duymeli-energizer'],
                            ['label' => 'Nokia', 'slug' => 'dt-duymeli-nokia'],
                            ['label' => 'Panasonic', 'slug' => 'dt-duymeli-panasonic'],
                            ['label' => 'Bütün brendlər', 'slug' => 'dt-duymeli-butun-brendler'],
                        ],
                    ],
                    [
                        'title' => 'Qol saatları',
                        'links' => [
                            ['label' => 'Kişi saatları', 'slug' => 'qs-kisi'],
                            ['label' => 'Qadın saatları', 'slug' => 'qs-qadin'],
                            ['label' => 'Uşaq saatları', 'slug' => 'qs-usaq'],
                            ['label' => 'Bütün saatlar', 'slug' => 'qs-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Smart saatlar',
                        'links' => [
                            ['label' => 'Apple', 'slug' => 'ss-smart-saatlar-apple'],
                            ['label' => 'Borofone', 'slug' => 'ss-smart-saatlar-borofone'],
                            ['label' => 'Garmin', 'slug' => 'ss-smart-saatlar-garmin'],
                            ['label' => 'Huawei', 'slug' => 'ss-smart-saatlar-huawei'],
                            ['label' => 'Samsung', 'slug' => 'ss-smart-saatlar-samsung'],
                            ['label' => 'Ttec', 'slug' => 'ss-smart-saatlar-ttec'],
                            ['label' => 'Wonlex', 'slug' => 'ss-smart-saatlar-wonlex'],
                            ['label' => 'Xiaomi', 'slug' => 'ss-smart-saatlar-xiaomi'],
                            ['label' => 'Smart saatlar üçün aksesuarlar', 'slug' => 'ss-smart-saatlar-aksesuarlar'],
                            ['label' => 'Bütün brendlər', 'slug' => 'ss-smart-saatlar-butun-brendler'],
                        ],
                    ],
                    [
                        'title' => 'Ev və ofis telefonları',
                        'links' => [
                            ['label' => 'Gigaset', 'slug' => 'eo-gigaset'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'laptop_mac',
            'label' => 'Noutbuklar və kompüter texnikası',
            'slug' => 'noutbuklar-ve-komp',
            'panel' => [
                [
                    [
                        'title' => 'Noutbuklar',
                        'links' => [
                            ['label' => 'Oyun noutbukları', 'slug' => 'nb-oyun'],
                            ['label' => 'İş üçün noutbuklar', 'slug' => 'nb-is'],
                            ['label' => 'Ultra yüngül', 'slug' => 'nb-ultra-yungul'],
                            ['label' => 'Bütün noutbuklar', 'slug' => 'nb-butun'],
                        ],
                    ],
                    [
                        'title' => 'Masaüstü kompüterlər',
                        'links' => [
                            ['label' => 'Hazır sistemlər', 'slug' => 'pc-hazir-sistem'],
                            ['label' => 'Monobloklar', 'slug' => 'pc-monoblok'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Monitorlar',
                        'links' => [
                            ['label' => 'Oyun monitorları', 'slug' => 'mn-oyun'],
                            ['label' => 'Ofis və dizayn', 'slug' => 'mn-ofis'],
                            ['label' => 'Bütün monitorlar', 'slug' => 'mn-butun'],
                        ],
                    ],
                    [
                        'title' => 'Printer və ofis',
                        'links' => [
                            ['label' => 'Printerlər', 'slug' => 'pr-printer'],
                            ['label' => 'Skanerlər', 'slug' => 'pr-skaner'],
                            ['label' => 'Sərəncəm', 'slug' => 'pr-serencam'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Şəbəkə və saxlama',
                        'links' => [
                            ['label' => 'Router və Wi‑Fi', 'slug' => 'nw-router'],
                            ['label' => 'NAS', 'slug' => 'nw-nas'],
                            ['label' => 'HDD / SSD', 'slug' => 'nw-disk'],
                        ],
                    ],
                    [
                        'title' => 'Periferiya',
                        'links' => [
                            ['label' => 'Siçan və klaviatura', 'slug' => 'ak-sican-klaviatura'],
                            ['label' => 'Noutbuk çantaları', 'slug' => 'ak-canta'],
                            ['label' => 'Digər aksesuarlar', 'slug' => 'ak-diger'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Proqram təminatı',
                        'links' => [
                            ['label' => 'Əməliyyat sistemləri', 'slug' => 'sw-os'],
                            ['label' => 'Antivirus', 'slug' => 'sw-antivirus'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'sports_esports',
            'label' => 'Oyun konsolları və aksesuarlar',
            'slug' => 'oyun-konsollar',
            'panel' => [
                [
                    [
                        'title' => 'Oyun konsolları',
                        'links' => [
                            ['label' => 'PlayStation', 'slug' => 'gm-playstation'],
                            ['label' => 'Xbox', 'slug' => 'gm-xbox'],
                            ['label' => 'Nintendo Switch', 'slug' => 'gm-nintendo'],
                            ['label' => 'Bütün konsollar', 'slug' => 'gm-butun-konsol'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Oyun aksesuarları',
                        'links' => [
                            ['label' => 'Qeympadlar', 'slug' => 'ga-qeympad'],
                            ['label' => 'Oyun üçün qulaqlıqlar', 'slug' => 'ga-qulaqliq'],
                            ['label' => 'Uşaq üçün', 'slug' => 'ga-usaq'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'VR və əyləncə',
                        'links' => [
                            ['label' => 'VR eynəkləri', 'slug' => 'vr-eynek'],
                            ['label' => 'Racing və stendlər', 'slug' => 'vr-racing'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Oyun üçün məhsullar',
                        'links' => [
                            ['label' => 'Oyun kresloları', 'slug' => 'gx-kreslo'],
                            ['label' => 'Masaüstü işıqlandırma', 'slug' => 'gx-isiq'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'tv',
            'label' => 'TV, audio və video',
            'slug' => 'tv-audio-video',
            'panel' => [
                [
                    [
                        'title' => 'Televizorlar',
                        'links' => [
                            ['label' => 'LED TV', 'slug' => 'tv-led'],
                            ['label' => 'OLED / QLED', 'slug' => 'tv-oled-qled'],
                            ['label' => 'Bütün televizorlar', 'slug' => 'tv-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Audio',
                        'links' => [
                            ['label' => 'Saundbar', 'slug' => 'au-saundbar'],
                            ['label' => 'Ev kinoteatrı', 'slug' => 'au-kinoteatr'],
                            ['label' => 'Kalonkalar', 'slug' => 'au-kalonka'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Video texnika',
                        'links' => [
                            ['label' => 'Proyektorlar', 'slug' => 'vd-proyektor'],
                            ['label' => 'Media pleyerlər', 'slug' => 'vd-pleyer'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Aksesuarlar',
                        'links' => [
                            ['label' => 'TV asılqanları', 'slug' => 'tv-ak-asilqan'],
                            ['label' => 'Kabellər və adapterlər', 'slug' => 'tv-ak-kabel'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'kitchen',
            'label' => 'Böyük məişət texnikası',
            'slug' => 'boyuk-meiset',
            'panel' => [
                [
                    [
                        'title' => 'Mətbəx',
                        'links' => [
                            ['label' => 'Soyuducular', 'slug' => 'bk-soyuducu'],
                            ['label' => 'Dondurucu kameralar', 'slug' => 'bk-dondurucu-kamera'],
                            ['label' => 'Qabyuyan maşınlar', 'slug' => 'bk-qabyuyan'],
                            ['label' => 'Bişirmə panelləri', 'slug' => 'bk-panel'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Təmizlik',
                        'links' => [
                            ['label' => 'Paltaryuyan maşınlar', 'slug' => 'bk-paltaryuyan'],
                            ['label' => 'Quruducular', 'slug' => 'bk-quruducu'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Bişirmə',
                        'links' => [
                            ['label' => 'Sobalar və plitələr', 'slug' => 'bk-soba'],
                            ['label' => 'Mikrodalğa sobalar', 'slug' => 'bk-mikro'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Aspiratorlar', 'slug' => 'bk-aspirator'],
                            ['label' => 'Bütün böyük məişət', 'slug' => 'bk-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'nutrition',
            'label' => 'Kiçik məişət texnikası',
            'slug' => 'kicik-meiset',
            'panel' => [
                [
                    [
                        'title' => 'Yemək hazırlanması',
                        'links' => [
                            ['label' => 'Fritozlar', 'slug' => 'km-fritoz'],
                            ['label' => 'Tosterlər', 'slug' => 'km-toster'],
                            ['label' => 'Çörək bişirən', 'slug' => 'km-corek-bisiren'],
                            ['label' => 'Multibişiricilər', 'slug' => 'km-multibisirici'],
                            ['label' => 'Mikrodalğalı sobalar', 'slug' => 'km-mikrodalga-soba'],
                            ['label' => 'Tərəvəz və meyvə qurudan', 'slug' => 'km-terevez-meyve-qurudan'],
                            ['label' => 'Elektrik sobalar', 'slug' => 'km-elektrik-soba'],
                            ['label' => 'Buxarlı bişiricilər', 'slug' => 'km-buxarli-bisirici'],
                            ['label' => 'Bütün yemək hazırlanması məhsulları', 'slug' => 'km-yemek-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Təmizlik',
                        'links' => [
                            ['label' => 'Tozsoranlar', 'slug' => 'km-tozsoran'],
                            ['label' => 'Ütülər', 'slug' => 'km-utu'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Şəxsi qulluq',
                        'links' => [
                            ['label' => 'Fenlər', 'slug' => 'km-fen'],
                            ['label' => 'Trimmer və maşınlar', 'slug' => 'km-trimmer'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Tərəzi', 'slug' => 'km-terezi'],
                            ['label' => 'Bütün kiçik məişət', 'slug' => 'km-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'ac_unit',
            'label' => 'İqlim texnikası',
            'slug' => 'iqlim-texnikasi',
            'panel' => [
                [
                    [
                        'title' => 'Soyutma və isitmə',
                        'links' => [
                            ['label' => 'Kondisionerlər', 'slug' => 'iq-kondisioner'],
                            ['label' => 'Ventilyatorlar', 'slug' => 'iq-ventilyator'],
                            ['label' => 'İsitmə cihazları', 'slug' => 'iq-isitme'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Hava keyfiyyəti',
                        'links' => [
                            ['label' => 'Təmizləyicilər', 'slug' => 'iq-temizleyici'],
                            ['label' => 'Nəmləndiricilər', 'slug' => 'iq-nemlendirici'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Su qızdırıcıları',
                        'links' => [
                            ['label' => 'Boilerlər', 'slug' => 'iq-boiler'],
                            ['label' => 'Qızdırıcılar', 'slug' => 'iq-qizdirici'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Bütün iqlim texnikası', 'slug' => 'iq-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'health_and_beauty',
            'label' => 'Gözəllik və baxım',
            'slug' => 'gozellik-baxim',
            'panel' => [
                [
                    [
                        'title' => 'Saç qulluğu',
                        'links' => [
                            ['label' => 'Fen və ütülər', 'slug' => 'gz-sac-fen'],
                            ['label' => 'Maşınlar və trimmerlər', 'slug' => 'gz-sac-masin'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Üz və bədən',
                        'links' => [
                            ['label' => 'Epilyatorlar', 'slug' => 'gz-epilyator'],
                            ['label' => 'Masaj cihazları', 'slug' => 'gz-masaj'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Kişi qulluğu',
                        'links' => [
                            ['label' => 'Üzqırxan', 'slug' => 'gz-kisi-qirxan'],
                            ['label' => 'Trimmer', 'slug' => 'gz-kisi-trimmer'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Bütün gözəllik', 'slug' => 'gz-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'medical_services',
            'label' => 'Tibbi məhsullar',
            'slug' => 'tibbi-mehsullar',
            'panel' => [
                [
                    [
                        'title' => 'Ölçü cihazları',
                        'links' => [
                            ['label' => 'Tonometrlər', 'slug' => 'tb-tonometr'],
                            ['label' => 'Termometrlər', 'slug' => 'tb-termometr'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Ortoopediya',
                        'links' => [
                            ['label' => 'Bandaj və korsetlər', 'slug' => 'tb-ortopediya'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Reabilitasiya',
                        'links' => [
                            ['label' => 'İnhalyatorlar', 'slug' => 'tb-inhalyator'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Bütün tibbi məhsullar', 'slug' => 'tb-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'chair',
            'label' => 'Ev və bağ',
            'slug' => 'ev-bag',
            'panel' => [
                [
                    [
                        'title' => 'Bağ',
                        'links' => [
                            ['label' => 'Bağ alətləri', 'slug' => 'eb-bag-alt'],
                            ['label' => 'Suvarma', 'slug' => 'eb-suvarma'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'İşıqlandırma',
                        'links' => [
                            ['label' => 'Lampalar', 'slug' => 'eb-lampa'],
                            ['label' => 'Xarici işıqlar', 'slug' => 'eb-xarici'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Təmir və alət',
                        'links' => [
                            ['label' => 'Elektrik alətləri', 'slug' => 'eb-elektrik-alt'],
                            ['label' => 'Əl alətləri', 'slug' => 'eb-el-alt'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Bütün ev və bağ', 'slug' => 'eb-butun'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'weekend',
            'label' => 'Mebel, tekstil və dekor',
            'slug' => 'mebel-tekstil-dekor',
            'panel' => [
                [
                    [
                        'title' => 'Qonaq otağı',
                        'links' => [
                            ['label' => 'Dəstlər', 'slug' => 'mb-qonaq-destler'],
                            ['label' => 'Masa', 'slug' => 'mb-qonaq-masa'],
                            ['label' => 'Konsollar', 'slug' => 'mb-qonaq-konsollar'],
                            ['label' => 'Vitrin', 'slug' => 'mb-qonaq-vitrin'],
                            ['label' => 'Oturacaqlar', 'slug' => 'mb-qonaq-oturacaqlar'],
                            ['label' => 'Güzgülər', 'slug' => 'mb-qonaq-guzguler'],
                            ['label' => 'Bütün qonaq otağı mebelləri', 'slug' => 'mb-qonaq-butun'],
                        ],
                    ],
                    [
                        'title' => 'TV stendlər',
                        'links' => [
                            ['label' => 'Dəst TV stendlər', 'slug' => 'mb-tv-dest'],
                            ['label' => 'Alt TV stendlər', 'slug' => 'mb-tv-alt'],
                            ['label' => 'Üst TV stendlər', 'slug' => 'mb-tv-ust'],
                            ['label' => 'Bütün TV stendlər', 'slug' => 'mb-tv-butun'],
                        ],
                    ],
                    [
                        'title' => 'Jurnal masaları',
                        'links' => [
                            ['label' => 'Bütün brendlər', 'slug' => 'mb-jurnal-butun-brendler'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Yataq otağı',
                        'links' => [
                            ['label' => 'Dəstlər', 'slug' => 'mb-yataq-destler'],
                            ['label' => 'Dolablar', 'slug' => 'mb-yataq-dolablar'],
                            ['label' => 'Komod və makiyaj masası', 'slug' => 'mb-yataq-komod-makiyaj'],
                            ['label' => 'Yataq yanı tumbalar', 'slug' => 'mb-yataq-tumba'],
                            ['label' => 'Yataqlar', 'slug' => 'mb-yataq-yataqlar'],
                            ['label' => 'Döşəklər', 'slug' => 'mb-yataq-dosekler'],
                            ['label' => 'Güzgülər', 'slug' => 'mb-yataq-guzguler'],
                            ['label' => 'Puflar', 'slug' => 'mb-yataq-puflar'],
                            ['label' => 'Bütün yataq otağı mebelləri', 'slug' => 'mb-yataq-butun'],
                        ],
                    ],
                    [
                        'title' => 'Ev tekstili',
                        'links' => [
                            ['label' => 'Yataq dəstləri', 'slug' => 'tx-yataq-destleri'],
                            ['label' => 'Yataq örtükləri', 'slug' => 'tx-yataq-ortukleri'],
                            ['label' => 'Yastıqlar', 'slug' => 'tx-yastiqlar'],
                            ['label' => 'Yorğanlar', 'slug' => 'tx-yorganlar'],
                            ['label' => 'Hamam üçün tekstil', 'slug' => 'tx-hamam'],
                            ['label' => 'Mətbəx tekstili', 'slug' => 'tx-metbex'],
                            ['label' => 'Süfrələr', 'slug' => 'tx-sufreler'],
                            ['label' => 'Xalçalar', 'slug' => 'tx-xalcalar'],
                            ['label' => 'Müxtəlif', 'slug' => 'tx-muxtelif'],
                            ['label' => 'Bütün ev tekstili', 'slug' => 'tx-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Yumşaq mebel',
                        'links' => [
                            ['label' => 'Dəstlər', 'slug' => 'mb-yumsaq-destler'],
                            ['label' => 'Künc divanlar', 'slug' => 'mb-yumsaq-kunc-divanlar'],
                            ['label' => 'Divanlar', 'slug' => 'mb-yumsaq-divanlar'],
                            ['label' => 'Kreslolar', 'slug' => 'mb-yumsaq-kreslolar'],
                            ['label' => 'Puflar', 'slug' => 'mb-yumsaq-puflar'],
                            ['label' => 'Bütün yumşaq mebel məhsulları', 'slug' => 'mb-yumsaq-butun'],
                        ],
                    ],
                    [
                        'title' => 'Dekor və interyer',
                        'links' => [
                            ['label' => 'Çərçivələr', 'slug' => 'dk-cercive'],
                            ['label' => 'Vazalar və aksesuarlar', 'slug' => 'dk-vaza'],
                            ['label' => 'Bütün dekor və interyer', 'slug' => 'dk-butun'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Gənc otağı',
                        'links' => [
                            ['label' => 'Dolab', 'slug' => 'mb-genc-dolab'],
                            ['label' => 'Kitab dolabı', 'slug' => 'mb-genc-kitab-dolabi'],
                            ['label' => 'İş masası', 'slug' => 'mb-genc-is-masasi'],
                            ['label' => 'Komod', 'slug' => 'mb-genc-komod'],
                            ['label' => 'Yataq', 'slug' => 'mb-genc-yataq'],
                            ['label' => 'Dəstlər', 'slug' => 'mb-genc-destler'],
                            ['label' => 'Güzgü', 'slug' => 'mb-genc-guzgu'],
                            ['label' => 'Rəflər', 'slug' => 'mb-genc-refler'],
                            ['label' => 'Oturacaq', 'slug' => 'mb-genc-oturacaq'],
                            ['label' => 'Bütün gənc otağı mebelləri', 'slug' => 'mb-genc-butun'],
                        ],
                    ],
                    [
                        'title' => 'Bağ mebeli',
                        'links' => [
                            ['label' => 'Bütün brendlər', 'slug' => 'mb-bag-butun-brendler'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'icon' => 'restaurant',
            'label' => 'Qab-qacaq, tava-qazan',
            'slug' => 'qab-qacaq',
            'panel' => [
                [
                    [
                        'title' => 'Qab-qacaq',
                        'links' => [
                            ['label' => 'Qab dəstləri', 'slug' => 'qq-dest'],
                            ['label' => 'Fincan və stəkan', 'slug' => 'qq-fincan'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Bişirmə',
                        'links' => [
                            ['label' => 'Tavalar', 'slug' => 'qq-tava'],
                            ['label' => 'Qazanlar', 'slug' => 'qq-qazan'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Bıçaq və lövhə',
                        'links' => [
                            ['label' => 'Bıçaq dəstləri', 'slug' => 'qq-bicak'],
                            ['label' => 'Kəsmə lövhələri', 'slug' => 'qq-lovhe'],
                        ],
                    ],
                ],
                [
                    [
                        'title' => 'Digər',
                        'links' => [
                            ['label' => 'Bütün qab-qacaq', 'slug' => 'qq-butun'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function maxhome_render_mega_menu(): void
{
    require_once __DIR__ . '/../db.php';
    $panels = maxhome_mega_menu_panels();
    echo '<div class="maxhome-mega-menu" aria-hidden="true">';
    echo '<div class="maxhome-mega-menu__left">';
    foreach ($panels as $idx => $main) {
        $pid = 'mega-panel-' . $idx;
        $active = $idx === 0 ? ' maxhome-mega-menu__left-item--active' : '';
        $url = maxhome_mega_shop_url($main['slug'] ?? null);
        echo '<a class="maxhome-mega-menu__left-item' . $active . '" href="' . e($url) . '" data-mega-target="' . e($pid) . '">';
        echo '<span class="material-symbols-outlined">' . e($main['icon']) . '</span>';
        echo e($main['label']);
        echo '</a>';
    }
    echo '</div>';

    echo '<div class="maxhome-mega-menu__panels">';
    foreach ($panels as $idx => $main) {
        $pid = 'mega-panel-' . $idx;
        $pactive = $idx === 0 ? ' maxhome-mega-menu__panel--active' : '';
        echo '<div id="' . e($pid) . '" class="maxhome-mega-menu__panel' . $pactive . '">';
        foreach ($main['panel'] as $col) {
            echo '<div class="maxhome-mega-menu__col">';
            foreach ($col as $block) {
                echo '<h4 class="maxhome-mega-menu__alt">' . e($block['title']) . '</h4>';
                foreach ($block['links'] as $link) {
                    $href = maxhome_mega_shop_url($link['slug'] ?? null);
                    echo '<a class="maxhome-mega-menu__alt-alt" href="' . e($href) . '">' . e($link['label']) . '</a>';
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
