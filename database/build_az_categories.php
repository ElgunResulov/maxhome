<?php
/**
 * includes/mega_menu.php ilə uyğun kateqoriya + brend INSERT-ləri stdout-a yazır.
 * İşlətmək: php database/build_az_categories.php > database/seed_az_catalog.sql
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/mega_menu.php';

$panel0BlockParents = [
    'Smartfonlar' => 'sf-smartfonlar',
    'Planşetlər' => 'pl-planshetler',
    'Düyməli telefonlar' => 'dt-duymeli-telefonlar',
    'Smart saatlar' => 'ss-smart-saatlar',
    'Elektron kitablar' => 'ek-elektron-kitablar',
    'Qulaqlıqlar' => 'ql-qulaqliqlar',
    'Qol saatları' => 'qs-qol-saatlari',
    'Ev və ofis telefonları' => 'eo-ev-ofis-telefonlari',
];

/** Panel 0: bu blokların linkləri brend cədvəlinə düşür */
$panel0BrandBlockTitles = [
    'Smartfonlar',
    'Planşetlər',
    'Düyməli telefonlar',
    'Smart saatlar',
    'Elektron kitablar',
    'Ev və ofis telefonları',
];

echo "-- Azərbaycan kataloqu: kateqoriya ağacı + brendlər (utf8mb4)\n";
echo "USE maxhome_db;\n\n";

$emit = static function (string $sql): void {
    echo $sql . "\n";
};

$emitCat = static function (
    ?string $parentSlug,
    string $slug,
    string $name,
    int $sortOrder,
    callable $emit
): void {
    if ($parentSlug === null) {
        $emit(
            "INSERT INTO categories (parent_id, name, slug, description, image_url, is_active, sort_order)\n" .
            "SELECT NULL, " . sqlQuote($name) . ", " . sqlQuote($slug) . ", NULL, NULL, 1, {$sortOrder}\n" .
            "WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = " . sqlQuote($slug) . ");"
        );
        return;
    }
    $emit(
        "INSERT INTO categories (parent_id, name, slug, description, image_url, is_active, sort_order)\n" .
        "SELECT p.id, " . sqlQuote($name) . ", " . sqlQuote($slug) . ", NULL, NULL, 1, {$sortOrder}\n" .
        "FROM categories p\n" .
        "WHERE p.slug = " . sqlQuote($parentSlug) . "\n" .
        "AND NOT EXISTS (SELECT 1 FROM categories WHERE slug = " . sqlQuote($slug) . ");"
    );
};

function sqlQuote(string $s): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";
}

function asciiFoldAz(string $text): string
{
    $map = [
        "\u{0259}" => 'e', "\u{0131}" => 'i', "\u{00F6}" => 'o', "\u{00FC}" => 'u', "\u{011F}" => 'g', "\u{015F}" => 's', "\u{00E7}" => 'c',
        "\u{018F}" => 'e', "\u{0130}" => 'i', "\u{00D6}" => 'o', "\u{00DC}" => 'u', "\u{011E}" => 'g', "\u{015E}" => 's', "\u{00C7}" => 'c',
        "\u{00C2}" => 'a', "\u{00E2}" => 'a',
    ];
    return strtr($text, $map);
}

function slugifyAz(string $text): string
{
    $text = asciiFoldAz($text);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) preg_replace('/-+/', '-', $text), '-');
    return $text !== '' ? $text : 'bolme';
}

function slugifyBrandName(string $name): string
{
    $s = asciiFoldAz($name);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = (string) preg_replace('/[^a-z0-9]+/i', '-', $s);
    return trim((string) preg_replace('/-+/', '-', $s), '-') ?: 'brend';
}

function azNormalizeLower(string $s): string
{
    $s = asciiFoldAz($s);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = (string) preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/**
 * @param list<array<string, mixed>> $panels
 * @param list<string> $panel0BrandBlockTitles
 * @return array<string, string> name => slug
 */
function collectManufacturerBrands(array $panels, array $panel0BrandBlockTitles): array
{
    $out = [];

    $excludeNormalized = [
        'butun brendler',
        'smart saatlar ucun aksesuarlar',
        'butun konsollar',
    ];

    $panel0 = $panels[0];
    foreach ($panel0['panel'] as $col) {
        foreach ($col as $block) {
            $bt = (string) $block['title'];
            if (!in_array($bt, $panel0BrandBlockTitles, true)) {
                continue;
            }
            foreach ($block['links'] as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $norm = azNormalizeLower($label);
                if (in_array($norm, $excludeNormalized, true)) {
                    continue;
                }
                if (str_contains($norm, 'butun') && str_contains($norm, 'brend')) {
                    continue;
                }
                if (str_contains($norm, 'butun') && str_contains($norm, 'konsol')) {
                    continue;
                }
                $out[$label] = slugifyBrandName($label);
            }
        }
    }

    if (isset($panels[2]['panel'])) {
        foreach ($panels[2]['panel'] as $col) {
            foreach ($col as $block) {
                if ((string) $block['title'] !== 'Oyun konsolları') {
                    continue;
                }
                foreach ($block['links'] as $link) {
                    $label = trim((string) ($link['label'] ?? ''));
                    if ($label === '' || $label === 'Bütün konsollar') {
                        continue;
                    }
                    $out[$label] = slugifyBrandName($label);
                }
            }
        }
    }

    return $out;
}

/**
 * Mega menyuda brend kimi işlənən kateqoriya yarpaqları: category.slug → brands.slug.
 *
 * @return list<array{cat_slug: string, brand_slug: string}>
 */
function collectCategoryBrandLinks(array $panels, array $panel0BrandBlockTitles): array
{
    $out = [];

    $excludeNormalized = [
        'butun brendler',
        'smart saatlar ucun aksesuarlar',
        'butun konsollar',
    ];

    $panel0 = $panels[0];
    foreach ($panel0['panel'] as $col) {
        foreach ($col as $block) {
            $bt = (string) $block['title'];
            if (!in_array($bt, $panel0BrandBlockTitles, true)) {
                continue;
            }
            foreach ($block['links'] as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $ls = (string) ($link['slug'] ?? '');
                if ($label === '' || $ls === '') {
                    continue;
                }
                $norm = azNormalizeLower($label);
                if (in_array($norm, $excludeNormalized, true)) {
                    continue;
                }
                if (str_contains($norm, 'butun') && str_contains($norm, 'brend')) {
                    continue;
                }
                if (str_contains($norm, 'butun') && str_contains($norm, 'konsol')) {
                    continue;
                }
                $out[] = ['cat_slug' => $ls, 'brand_slug' => slugifyBrandName($label)];
            }
        }
    }

    if (isset($panels[2]['panel'])) {
        foreach ($panels[2]['panel'] as $col) {
            foreach ($col as $block) {
                if ((string) $block['title'] !== 'Oyun konsolları') {
                    continue;
                }
                foreach ($block['links'] as $link) {
                    $label = trim((string) ($link['label'] ?? ''));
                    $ls = (string) ($link['slug'] ?? '');
                    if ($label === '' || $ls === '' || $label === 'Bütün konsollar') {
                        continue;
                    }
                    $out[] = ['cat_slug' => $ls, 'brand_slug' => slugifyBrandName($label)];
                }
            }
        }
    }

    return $out;
}

$panels = maxhome_mega_menu_panels_static();

foreach ($panels as $pi => $main) {
    $sort = $pi + 1;
    $rootSlug = (string) $main['slug'];
    $rootName = (string) $main['label'];
    $emitCat(null, $rootSlug, $rootName, $sort, $emit);

    if ($pi === 0) {
        $subSort = 0;
        foreach ($main['panel'] as $col) {
            foreach ($col as $block) {
                $title = (string) $block['title'];
                if (!isset($panel0BlockParents[$title])) {
                    fwrite(STDERR, "Missing panel0 map: {$title}\n");
                    exit(1);
                }
                $midSlug = $panel0BlockParents[$title];
                $subSort++;
                $emitCat($rootSlug, $midSlug, $title, $subSort, $emit);
                $leafSort = 0;
                foreach ($block['links'] as $link) {
                    $leafSort++;
                    $ls = (string) ($link['slug'] ?? '');
                    $ln = (string) $link['label'];
                    if ($ls === '') {
                        continue;
                    }
                    $emitCat($midSlug, $ls, $ln, $leafSort, $emit);
                }
            }
        }
        continue;
    }

    $sectionOrder = 0;
    foreach ($main['panel'] as $col) {
        foreach ($col as $block) {
            $sectionOrder++;
            $title = (string) $block['title'];
            $secSlug = $rootSlug . '-b-' . sprintf('%02d', $sectionOrder) . '-' . slugifyAz($title);
            $emitCat($rootSlug, $secSlug, $title, $sectionOrder, $emit);
            $leafSort = 0;
            foreach ($block['links'] as $link) {
                $leafSort++;
                $ls = (string) ($link['slug'] ?? '');
                $ln = (string) $link['label'];
                if ($ls === '') {
                    continue;
                }
                $emitCat($secSlug, $ls, $ln, $leafSort, $emit);
            }
        }
    }
}

$brands = collectManufacturerBrands($panels, $panel0BrandBlockTitles);
uksort($brands, 'strnatcasecmp');

echo "\n-- Brendlər (products.brand_id; mega menyudakı istehsalçılar)\n";
foreach ($brands as $bName => $bSlug) {
    $emit(
        "INSERT INTO brands (name, slug)\nVALUES (" . sqlQuote($bName) . ", " . sqlQuote($bSlug) . ")\n" .
        "ON DUPLICATE KEY UPDATE name = VALUES(name);"
    );
}

$catBrandLinks = collectCategoryBrandLinks($panels, $panel0BrandBlockTitles);
echo "\n-- Kateqoriya brend yarpaqları → brands (categories.brand_id)\n";
foreach ($catBrandLinks as $row) {
    $cs = sqlQuote($row['cat_slug']);
    $bs = sqlQuote($row['brand_slug']);
    $emit(
        "UPDATE categories c\n" .
        "INNER JOIN brands b ON b.slug = {$bs}\n" .
        "SET c.brand_id = b.id\n" .
        "WHERE c.slug = {$cs};"
    );
}

echo "\n-- Bitdi\n";
