<?php
declare(strict_types=1);

/**
 * @return array<string, mixed>|null
 */
function maxhomeFetchProductForCompare(PDO $pdo, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            c.name AS category_name,
            b.name AS brand_name,
            (SELECT pi.image_url
             FROM product_images pi
             WHERE pi.product_id = p.id
             ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
             LIMIT 1) AS image_url
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN brands b ON b.id = p.brand_id
         WHERE p.slug = :slug AND p.status = 'active'{$catalogOnlineSql}
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);

    $product = $stmt->fetch();
    return $product ?: null;
}

/**
 * @param array<string, mixed> $product
 * @return list<array{display_label:string,display_value:string,spec_key:string}>
 */
function maxhomeProductVisibleSpecs(PDO $pdo, array $product): array
{
    $specStmt = $pdo->prepare(
        "SELECT
            ps.spec_key,
            ps.spec_value,
            sd.label AS spec_label,
            sd.input_type,
            sd.unit
         FROM product_specs ps
         LEFT JOIN spec_definitions sd ON sd.spec_key COLLATE utf8mb4_general_ci = ps.spec_key
         WHERE ps.product_id = :pid
         ORDER BY ps.sort_order ASC, ps.id ASC"
    );
    $specStmt->execute(['pid' => (int) $product['id']]);
    $specs = $specStmt->fetchAll() ?: [];

    $specRowsByKey = [];
    foreach ($specs as $specRow) {
        $specKey = trim((string) ($specRow['spec_key'] ?? ''));
        if ($specKey === '' || maxhomeIsInternalProductSpecKey($specKey)) {
            continue;
        }
        $specRowsByKey[$specKey] = $specRow;
    }

    $definitionStmt = $pdo->prepare(
        "SELECT
            sd.spec_key,
            sd.label AS spec_label,
            sd.input_type,
            sd.unit,
            csm.sort_order
         FROM category_spec_map csm
         INNER JOIN spec_definitions sd ON sd.id = csm.spec_definition_id
         WHERE csm.category_id = :category_id
           AND sd.is_active = 1
         ORDER BY csm.sort_order ASC, sd.sort_order ASC, sd.label ASC"
    );
    $definitionStmt->execute(['category_id' => (int) $product['category_id']]);
    $categoryDefinitions = $definitionStmt->fetchAll() ?: [];

    $renderSpecRow = static function (array $specRow): ?array {
        $rawValue = trim((string) ($specRow['spec_value'] ?? ''));
        if ($rawValue === '') {
            return null;
        }

        $inputType = (string) ($specRow['input_type'] ?? '');
        if ($inputType === 'boolean') {
            $displayValue = in_array(strtolower($rawValue), ['1', 'true', 'yes', 'on'], true) ? 'Beli' : 'Xeyr';
        } else {
            $displayValue = $rawValue;
            if (($specRow['unit'] ?? null) !== null && trim((string) $specRow['unit']) !== '') {
                $displayValue .= ' ' . trim((string) $specRow['unit']);
            }
        }

        return [
            'display_label' => (string) (($specRow['spec_label'] ?? '') !== '' ? $specRow['spec_label'] : ($specRow['spec_key'] ?? '')),
            'display_value' => $displayValue,
            'spec_key' => (string) ($specRow['spec_key'] ?? ''),
        ];
    };

    $visibleSpecs = [];
    $usedSpecKeys = [];

    foreach ($categoryDefinitions as $definition) {
        $definitionKey = (string) ($definition['spec_key'] ?? '');
        if ($definitionKey === '') {
            continue;
        }
        $usedSpecKeys[$definitionKey] = true;
        $row = $specRowsByKey[$definitionKey] ?? [];
        $rendered = $renderSpecRow([
            'spec_key' => $definitionKey,
            'spec_label' => (string) ($definition['spec_label'] ?? $definitionKey),
            'input_type' => (string) ($definition['input_type'] ?? ''),
            'unit' => $definition['unit'] ?? null,
            'spec_value' => $row['spec_value'] ?? '',
        ]);
        if ($rendered !== null) {
            $visibleSpecs[] = $rendered;
        }
    }

    foreach ($specs as $specRow) {
        $specKey = (string) ($specRow['spec_key'] ?? '');
        if ($specKey === '' || isset($usedSpecKeys[$specKey]) || maxhomeIsInternalProductSpecKey($specKey)) {
            continue;
        }
        $rendered = $renderSpecRow($specRow);
        if ($rendered !== null) {
            $visibleSpecs[] = $rendered;
        }
    }

    return $visibleSpecs;
}

/**
 * @param list<array{display_label:string,display_value:string,spec_key:string}> $leftSpecs
 * @param list<array{display_label:string,display_value:string,spec_key:string}> $rightSpecs
 * @return list<array{label:string,left:string,right:string}>
 */
function maxhomeBuildCompareRows(array $leftSpecs, array $rightSpecs): array
{
    $labels = [];
    $leftValues = [];
    $rightValues = [];

    foreach ($leftSpecs as $spec) {
        $key = (string) ($spec['spec_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $labels[$key] = (string) ($spec['display_label'] ?? $key);
        $leftValues[$key] = (string) ($spec['display_value'] ?? '');
    }

    foreach ($rightSpecs as $spec) {
        $key = (string) ($spec['spec_key'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($labels[$key])) {
            $labels[$key] = (string) ($spec['display_label'] ?? $key);
        }
        $rightValues[$key] = (string) ($spec['display_value'] ?? '');
    }

    $rows = [];
    foreach ($labels as $key => $label) {
        $rows[] = [
            'label' => $label,
            'left' => $leftValues[$key] ?? '—',
            'right' => $rightValues[$key] ?? '—',
        ];
    }

    return $rows;
}

/**
 * @param array<string, mixed> $product
 * @return array<string, string>
 */
function maxhomeProductBaseCompareValues(array $product): array
{
    $priceDisplay = maxhomeProductPriceDisplay($product);

    return [
        'Brend' => (string) ($product['brand_name'] ?: 'MAXHOME'),
        'Kateqoriya' => (string) ($product['category_name'] ?: 'Ümumi'),
        'Qiymət' => number_format($priceDisplay['base_price'], 2) . ' ₼',
        'Reytinq' => number_format((float) ($product['rating_avg'] ?? 0), 1) . ' (' . (int) ($product['rating_count'] ?? 0) . ')',
        'Stok' => (int) ($product['stock_qty'] ?? 0) > 0 ? 'Mövcuddur' : 'Stokda yoxdur',
    ];
}

/**
 * @param array<string, mixed> $leftProduct
 * @param array<string, mixed> $rightProduct
 * @return list<array{label:string,left:string,right:string,diff:bool}>
 */
function maxhomeBuildFullCompareRows(array $leftProduct, array $rightProduct, PDO $pdo): array
{
    $leftBase = maxhomeProductBaseCompareValues($leftProduct);
    $rightBase = maxhomeProductBaseCompareValues($rightProduct);
    $leftSpecs = maxhomeProductVisibleSpecs($pdo, $leftProduct);
    $rightSpecs = maxhomeProductVisibleSpecs($pdo, $rightProduct);
    $specRows = maxhomeBuildCompareRows($leftSpecs, $rightSpecs);

    $rows = [];
    foreach ($leftBase as $label => $leftValue) {
        $rightValue = $rightBase[$label] ?? '—';
        $rows[] = [
            'label' => $label,
            'left' => $leftValue,
            'right' => $rightValue,
            'diff' => $leftValue !== $rightValue,
        ];
    }

    foreach ($specRows as $row) {
        $rows[] = [
            'label' => $row['label'],
            'left' => $row['left'],
            'right' => $row['right'],
            'diff' => $row['left'] !== $row['right'],
        ];
    }

    return $rows;
}
