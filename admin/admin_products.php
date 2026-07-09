<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product_json_import.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();
$message = '';
$messageType = 'success';

    /**
     * @return array<string, array<string, mixed>>
     */
    function fetchCategorySpecDefinitions(PDO $pdo, int $categoryId): array
    {
        $excludedSpecKeys = [
            'screen_size_inch',
            'storage_gb',
            'sp_touch_sampling_hz',
            'sp_gorilla_glass',
        ];
        $excludedPlaceholders = implode(',', array_fill(0, count($excludedSpecKeys), '?'));
        $categoryIdsToTry = [];
        $seenCategoryIds = [];
        $currentCategoryId = $categoryId;
        $parentStmt = $pdo->prepare(
            "SELECT parent_id
            FROM categories
            WHERE id = ?
            LIMIT 1"
        );
        while ($currentCategoryId > 0 && !isset($seenCategoryIds[$currentCategoryId])) {
            $categoryIdsToTry[] = $currentCategoryId;
            $seenCategoryIds[$currentCategoryId] = true;

            $parentStmt->execute([$currentCategoryId]);
            $parentId = (int) ($parentStmt->fetchColumn() ?: 0);
            $currentCategoryId = $parentId;
        }

        $stmt = $pdo->prepare(
            "SELECT
                sd.spec_key,
                sd.label,
                sd.input_type,
                sd.unit,
                sd.options_json,
                csm.is_required,
                csm.sort_order
            FROM category_spec_map csm
            INNER JOIN spec_definitions sd ON sd.id = csm.spec_definition_id
            WHERE csm.category_id = ?
            AND sd.is_active = 1
            AND sd.spec_key NOT IN (" . $excludedPlaceholders . ")
            ORDER BY csm.sort_order ASC, sd.sort_order ASC, sd.label ASC"
        );
        $rows = [];
        foreach ($categoryIdsToTry as $categoryIdCandidate) {
            $stmt->execute(array_merge([$categoryIdCandidate], $excludedSpecKeys));
            $rows = $stmt->fetchAll() ?: [];
            if (!empty($rows)) {
                break;
            }
        }

        $map = [];
        foreach ($rows as $row) {
            $options = [];
            if (!empty($row['options_json'])) {
                $decoded = json_decode((string) $row['options_json'], true);
                if (is_array($decoded)) {
                    $options = array_values(array_filter($decoded, static fn ($item) => is_scalar($item)));
                }
            }
            $key = (string) $row['spec_key'];
            $map[$key] = [
                'spec_key' => $key,
                'label' => (string) $row['label'],
                'input_type' => (string) $row['input_type'],
                'unit' => $row['unit'] !== null ? (string) $row['unit'] : null,
                'is_required' => (int) $row['is_required'] === 1,
                'sort_order' => (int) $row['sort_order'],
                'options' => $options,
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $definition
     */
    function normalizeSpecValue(mixed $rawValue, array $definition): string
    {
        $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
        $inputType = (string) ($definition['input_type'] ?? 'text');

        if ($inputType === 'boolean') {
            $boolValue = in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
            if ($value === '') {
                $boolValue = '0';
            }
            return $boolValue;
        }

        if ($value === '') {
            return '';
        }

        if ($inputType === 'number') {
            if (!is_numeric($value)) {
                throw new RuntimeException('Sayisal xususiyyet deyeri yanlisdir: ' . (string) $definition['label']);
            }
            return (string) (0 + $value);
        }

        if ($inputType === 'select') {
            $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
            if (!in_array($value, array_map('strval', $options), true)) {
                throw new RuntimeException('Secim xususiyyet deyeri yanlisdir: ' . (string) $definition['label']);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $spec
     * @param string $namePrefix
     */
    function renderSpecField(array $spec, string $namePrefix, string $currentValue = ''): string
    {
        $name = $namePrefix . '[' . (string) $spec['spec_key'] . ']';
        $label = e((string) $spec['label']);
        $html = '<div><label>' . $label . '</label>';
        $inputType = (string) ($spec['input_type'] ?? 'text');

        if ($inputType === 'select') {
            $html .= '<select name="' . e($name) . '">';
            $html .= '<option value="">Secin</option>';
            foreach ((array) ($spec['options'] ?? []) as $option) {
                $optionValue = (string) $option;
                $selected = $currentValue === $optionValue ? ' selected' : '';
                $html .= '<option value="' . e($optionValue) . '"' . $selected . '>' . e($optionValue) . '</option>';
            }
            $html .= '</select>';
        } elseif ($inputType === 'boolean') {
            $current = $currentValue !== '' ? $currentValue : '0';
            $html .= '<select name="' . e($name) . '">';
            $html .= '<option value="0"' . ($current === '0' ? ' selected' : '') . '>Xeyr</option>';
            $html .= '<option value="1"' . ($current === '1' ? ' selected' : '') . '>Beli</option>';
            $html .= '</select>';
        } else {
            $type = $inputType === 'number' ? 'number' : 'text';
            $step = $inputType === 'number' ? ' step="any"' : '';
            $html .= '<input type="' . $type . '" name="' . e($name) . '" value="' . e($currentValue) . '"' . $step . '>';
        }

        if (!empty($spec['unit'])) {
            $html .= '<div class="spec-help">Olcu vahidi: ' . e((string) $spec['unit']) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    function generateUniqueProductSlug(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT 1 FROM products WHERE slug = :slug LIMIT 1');
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $slug = 'p-' . bin2hex(random_bytes(8));
            $stmt->execute(['slug' => $slug]);
            if (!$stmt->fetchColumn()) {
                return $slug;
            }
        }

        throw new RuntimeException('Unikal slug yaradila bilmedi, yeniden cehd edin.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    function normalizeUploadFileList(array $fileField): array
    {
        if (!isset($fileField['name'])) {
            return [];
        }

        if (is_array($fileField['name'])) {
            $names = $fileField['name'];
            $tmpNames = is_array($fileField['tmp_name'] ?? null) ? $fileField['tmp_name'] : [];
            $errors = is_array($fileField['error'] ?? null) ? $fileField['error'] : [];
            $sizes = is_array($fileField['size'] ?? null) ? $fileField['size'] : [];
            $types = is_array($fileField['type'] ?? null) ? $fileField['type'] : [];
            $out = [];
            foreach ($names as $idx => $name) {
                $out[] = [
                    'name' => $name,
                    'type' => $types[$idx] ?? '',
                    'tmp_name' => $tmpNames[$idx] ?? '',
                    'error' => $errors[$idx] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $sizes[$idx] ?? 0,
                ];
            }
            return $out;
        }

        return [$fileField];
    }

    /**
     * @param mixed $labelsRaw
     * @param mixed $valuesRaw
     * @return list<array{spec_key:string,spec_value:string,sort_order:int}>
     */
    function normalizeCustomSpecsInput(mixed $labelsRaw, mixed $valuesRaw): array
    {
        $labels = is_array($labelsRaw) ? $labelsRaw : [];
        $values = is_array($valuesRaw) ? $valuesRaw : [];
        $items = [];
        $nextSort = 9000;
        $seen = [];

        $count = max(count($labels), count($values));
        for ($i = 0; $i < $count; $i++) {
            $label = trim((string) ($labels[$i] ?? ''));
            $value = trim((string) ($values[$i] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            if ($label === '' || $value === '') {
                throw new RuntimeException('Elave ozellik ucun ad ve deyer birlikde doldurulmalidir.');
            }
            if (mb_strlen($label) > 120) {
                throw new RuntimeException('Elave ozellik adi en cox 120 simvol ola biler.');
            }
            if (mb_strlen($value) > 255) {
                throw new RuntimeException('Elave ozellik deyeri en cox 255 simvol ola biler.');
            }
            $dedupeKey = mb_strtolower($label);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $items[] = [
                'spec_key' => $label,
                'spec_value' => $value,
                'sort_order' => $nextSort++,
            ];
        }

        return $items;
    }

    function resolveBrandIdFromInput(PDO $pdo, string $rawBrandName): ?int
    {
        $brandName = trim($rawBrandName);
        if ($brandName === '') {
            return null;
        }
        if (mb_strlen($brandName) > 120) {
            throw new RuntimeException('Brend adi en cox 120 simvol ola biler.');
        }

        $existingStmt = $pdo->prepare(
            "SELECT id
            FROM brands
            WHERE LOWER(name) = LOWER(:name)
            LIMIT 1"
        );
        $existingStmt->execute(['name' => $brandName]);
        $existingId = (int) $existingStmt->fetchColumn();
        if ($existingId > 0) {
            return $existingId;
        }

        $insertStmt = $pdo->prepare(
            "INSERT INTO brands (name)
            VALUES (:name)"
        );
        $insertStmt->execute(['name' => $brandName]);

        return (int) $pdo->lastInsertId();
    }

    $categories = $pdo->query(
        "SELECT id, parent_id, name
        FROM categories
        WHERE is_active = 1
        ORDER BY sort_order ASC, name ASC"
    )->fetchAll() ?: [];
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll() ?: [];
    $productsApiSettings = productJsonImportGetApiSettings($pdo);
    $productsApiUrl = $productsApiSettings['url'];
    $productsApiToken = $productsApiSettings['token'] !== ''
        ? $productsApiSettings['token']
        : 'mh_677b93d6e87070f1fece1b67d8ee0ed6fc390f72b8a25b8c';
    $primaryCategories = [];
    $subcategoriesByParent = [];
    foreach ($categories as $categoryRow) {
        $parentId = $categoryRow['parent_id'] !== null ? (int) $categoryRow['parent_id'] : null;
        $id = (int) ($categoryRow['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if ($parentId === null) {
            $primaryCategories[] = [
                'id' => $id,
                'name' => (string) ($categoryRow['name'] ?? ''),
            ];
            continue;
        }
        $subcategoriesByParent[$parentId][] = [
            'id' => $id,
            'name' => (string) ($categoryRow['name'] ?? ''),
        ];
    }
    $editId = (int) ($_GET['edit'] ?? 0);
    $editingProduct = null;
    $editingProductSpecs = [];
    $editingSpecDefinitions = [];
    $editingProductPrimaryImage = null;
    $editingProductImages = [];
    $editingBundleProducts = [];
    $editingCustomSpecs = [];
    $editingParentCategoryId = 0;
    $editingSubcategoryId = 0;
    $editingThirdCategoryId = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? ''));

        try {
            if ($action === 'create') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $slug = productJsonImportResolveProductSlug($pdo, ['name' => $name, 'slug' => '']);
                $sku = trim((string) ($_POST['sku'] ?? ''));
                $shortDescription = trim((string) ($_POST['short_description'] ?? ''));
                $basePrice = (float) ($_POST['base_price'] ?? 0);
                $compareAtRaw = trim((string) ($_POST['compare_at_price'] ?? ''));
                $compareAtPrice = null;
                if ($compareAtRaw !== '' && is_numeric($compareAtRaw)) {
                    $compareAtPrice = round((float) $compareAtRaw, 2);
                    if ($compareAtPrice <= 0) {
                        $compareAtPrice = null;
                    } elseif ($basePrice > 0 && $compareAtPrice <= $basePrice) {
                        throw new RuntimeException('Siyahı qiymet endirimli qiymetden boyuk olmalidir.');
                    }
                }
                $stockQty = (int) ($_POST['stock_qty'] ?? 0);
                $parentCategoryId = (int) ($_POST['category_id'] ?? 0);
                $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
                $thirdCategoryId = (int) ($_POST['third_category_id'] ?? 0);
                $categoryId = $parentCategoryId;
                $brandId = resolveBrandIdFromInput($pdo, (string) ($_POST['brand_name'] ?? ''));
                $status = trim((string) ($_POST['status'] ?? 'active'));
                $specInput = $_POST['spec'] ?? [];
                if (!is_array($specInput)) {
                    $specInput = [];
                }
                $customSpecs = normalizeCustomSpecsInput(
                    $_POST['extra_spec_label'] ?? [],
                    $_POST['extra_spec_value'] ?? []
                );

                if ($name === '' || $sku === '' || $parentCategoryId <= 0) {
                    throw new RuntimeException('Mecburi saheler bos buraxila bilmez.');
                }

                $parentStmt = $pdo->prepare(
                    "SELECT id
                    FROM categories
                    WHERE id = :id
                    AND is_active = 1
                    LIMIT 1"
                );
                $parentStmt->execute(['id' => $parentCategoryId]);
                if (!$parentStmt->fetch()) {
                    throw new RuntimeException('Secilen kateqoriya movcud deyil.');
                }

                $subCountStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                    FROM categories
                    WHERE parent_id = :parent_id
                    AND is_active = 1"
                );
                $subCountStmt->execute(['parent_id' => $parentCategoryId]);
                $activeSubcategoryCount = (int) $subCountStmt->fetchColumn();

                if ($subcategoryId > 0) {
                    $subStmt = $pdo->prepare(
                        "SELECT id
                        FROM categories
                        WHERE id = :id
                        AND parent_id = :parent_id
                        AND is_active = 1
                        LIMIT 1"
                    );
                    $subStmt->execute([
                        'id' => $subcategoryId,
                        'parent_id' => $parentCategoryId,
                    ]);
                    if (!$subStmt->fetch()) {
                        throw new RuntimeException('Secilen alt kateqoriya bu kateqoriyaya aid deyil.');
                    }
                    $categoryId = $subcategoryId;
                    $thirdCountStmt = $pdo->prepare(
                        "SELECT COUNT(*)
                        FROM categories
                        WHERE parent_id = :parent_id
                        AND is_active = 1"
                    );
                    $thirdCountStmt->execute(['parent_id' => $subcategoryId]);
                    $activeThirdCategoryCount = (int) $thirdCountStmt->fetchColumn();

                    if ($thirdCategoryId > 0) {
                        $thirdStmt = $pdo->prepare(
                            "SELECT id
                            FROM categories
                            WHERE id = :id
                            AND parent_id = :parent_id
                            AND is_active = 1
                            LIMIT 1"
                        );
                        $thirdStmt->execute([
                            'id' => $thirdCategoryId,
                            'parent_id' => $subcategoryId,
                        ]);
                        if (!$thirdStmt->fetch()) {
                            throw new RuntimeException('Secilen alt-alt kateqoriya bu alt kateqoriyaya aid deyil.');
                        }
                        $categoryId = $thirdCategoryId;
                    } elseif ($activeThirdCategoryCount > 0) {
                        throw new RuntimeException('Bu alt kateqoriya ucun alt-alt kateqoriya secmek mecburidir.');
                    }
                } elseif ($activeSubcategoryCount > 0) {
                    throw new RuntimeException('Bu kateqoriya ucun alt kateqoriya secmek mecburidir.');
                }

                $definitions = fetchCategorySpecDefinitions($pdo, $categoryId);
                $normalizedSpecs = [];

                foreach ($definitions as $specKey => $definition) {
                    $normalizedValue = normalizeSpecValue($specInput[$specKey] ?? '', $definition);
                    if ($normalizedValue !== '') {
                        $normalizedSpecs[] = [
                            'spec_key' => $specKey,
                            'spec_value' => $normalizedValue,
                            'sort_order' => (int) ($definition['sort_order'] ?? 0),
                        ];
                    }
                }

                $pdo->beginTransaction();
                try {
                    if (maxhomeProductsOnlineVisibleColumnExists($pdo)) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO products (
                                category_id, brand_id, name, slug, short_description, sku, base_price, compare_at_price, stock_qty, status, online_visible
                            ) VALUES (
                                :category_id, :brand_id, :name, :slug, :short_description, :sku, :base_price, :compare_at_price, :stock_qty, :status, 1
                            )"
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            "INSERT INTO products (
                                category_id, brand_id, name, slug, short_description, sku, base_price, compare_at_price, stock_qty, status
                            ) VALUES (
                                :category_id, :brand_id, :name, :slug, :short_description, :sku, :base_price, :compare_at_price, :stock_qty, :status
                            )"
                        );
                    }
                    $stmt->execute([
                        'category_id' => $categoryId,
                        'brand_id' => $brandId,
                        'name' => $name,
                        'slug' => $slug,
                        'short_description' => $shortDescription !== '' ? $shortDescription : null,
                        'sku' => $sku,
                        'base_price' => $basePrice,
                        'compare_at_price' => $compareAtPrice,
                        'stock_qty' => $stockQty,
                        'status' => in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'active',
                    ]);

                    $productId = (int) $pdo->lastInsertId();
                    if (!empty($normalizedSpecs)) {
                        $specStmt = $pdo->prepare(
                            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
                            VALUES (:product_id, :spec_key, :spec_value, :sort_order)"
                        );

                        foreach ($normalizedSpecs as $spec) {
                            $specStmt->execute([
                                'product_id' => $productId,
                                'spec_key' => $spec['spec_key'],
                                'spec_value' => $spec['spec_value'],
                                'sort_order' => $spec['sort_order'],
                            ]);
                        }
                    }
                    if (!empty($customSpecs)) {
                        $customSpecStmt = $pdo->prepare(
                            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
                            VALUES (:product_id, :spec_key, :spec_value, :sort_order)"
                        );
                        foreach ($customSpecs as $spec) {
                            $customSpecStmt->execute([
                                'product_id' => $productId,
                                'spec_key' => $spec['spec_key'],
                                'spec_value' => $spec['spec_value'],
                                'sort_order' => $spec['sort_order'],
                            ]);
                        }
                        maxhomeSyncCategoryExtraSpecTemplates($pdo, $categoryId, $customSpecs);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $message = 'Mehsul ugurla elave edildi.';
                if (isset($_FILES['product_image']) && is_array($_FILES['product_image'])) {
                    $uploadedFiles = normalizeUploadFileList($_FILES['product_image']);
                    $uploadedCount = 0;
                    $failedUploads = [];
                    foreach ($uploadedFiles as $idx => $imgFile) {
                        $imgErr = (int) ($imgFile['error'] ?? UPLOAD_ERR_NO_FILE);
                        if ($imgErr === UPLOAD_ERR_NO_FILE || empty($imgFile['name'])) {
                            continue;
                        }
                        if ($imgErr !== UPLOAD_ERR_OK) {
                            $failedUploads[] = 'fayl #' . ($idx + 1) . ' xetasi';
                            continue;
                        }
                        try {
                            saveProductImageFromUpload($pdo, $productId, $imgFile, $uploadedCount === 0, $uploadedCount);
                            $uploadedCount++;
                        } catch (Throwable $imgEx) {
                            $failedUploads[] = 'fayl #' . ($idx + 1) . ': ' . $imgEx->getMessage();
                        }
                    }
                    if ($uploadedCount > 0) {
                        $message .= ' ' . $uploadedCount . ' sekil elave edildi.';
                    }
                    if (!empty($failedUploads)) {
                        $message .= ' Bezi sekiller yuklenmedi: ' . implode('; ', $failedUploads);
                    }
                }
            }

            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $basePrice = (float) ($_POST['base_price'] ?? 0);
                $compareAtRaw = trim((string) ($_POST['compare_at_price'] ?? ''));
                $compareAtPrice = null;
                if ($compareAtRaw !== '' && is_numeric($compareAtRaw)) {
                    $compareAtPrice = round((float) $compareAtRaw, 2);
                    if ($compareAtPrice <= 0) {
                        $compareAtPrice = null;
                    } elseif ($basePrice > 0 && $compareAtPrice <= $basePrice) {
                        throw new RuntimeException('Siyahı qiymet endirimli qiymetden boyuk olmalidir.');
                    }
                }
                $stockQty = (int) ($_POST['stock_qty'] ?? 0);
                $status = trim((string) ($_POST['status'] ?? 'active'));
                $parentCategoryId = (int) ($_POST['category_id'] ?? 0);
                $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
                $thirdCategoryId = (int) ($_POST['third_category_id'] ?? 0);
                $categoryId = $parentCategoryId;
                $specInput = $_POST['spec'] ?? [];
                if (!is_array($specInput)) {
                    $specInput = [];
                }
                $customSpecs = normalizeCustomSpecsInput(
                    $_POST['extra_spec_label'] ?? [],
                    $_POST['extra_spec_value'] ?? []
                );

                if ($id <= 0 || $name === '' || $parentCategoryId <= 0) {
                    throw new RuntimeException('Yenileme melumatlari sehvdir.');
                }

                maxhomeAssertCatalogProduct($pdo, $id);

                $productStmt = $pdo->prepare("SELECT category_id FROM products WHERE id = :id LIMIT 1");
                $productStmt->execute(['id' => $id]);
                $productRow = $productStmt->fetch();
                if (!$productRow) {
                    throw new RuntimeException('Mehsul tapilmadi.');
                }

                $parentStmt = $pdo->prepare(
                    "SELECT id
                    FROM categories
                    WHERE id = :id
                    AND is_active = 1
                    LIMIT 1"
                );
                $parentStmt->execute(['id' => $parentCategoryId]);
                if (!$parentStmt->fetch()) {
                    throw new RuntimeException('Secilen kateqoriya movcud deyil.');
                }

                $subCountStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                    FROM categories
                    WHERE parent_id = :parent_id
                    AND is_active = 1"
                );
                $subCountStmt->execute(['parent_id' => $parentCategoryId]);
                $activeSubcategoryCount = (int) $subCountStmt->fetchColumn();

                if ($subcategoryId > 0) {
                    $subStmt = $pdo->prepare(
                        "SELECT id
                        FROM categories
                        WHERE id = :id
                        AND parent_id = :parent_id
                        AND is_active = 1
                        LIMIT 1"
                    );
                    $subStmt->execute([
                        'id' => $subcategoryId,
                        'parent_id' => $parentCategoryId,
                    ]);
                    if (!$subStmt->fetch()) {
                        throw new RuntimeException('Secilen alt kateqoriya bu kateqoriyaya aid deyil.');
                    }
                    $categoryId = $subcategoryId;
                    $thirdCountStmt = $pdo->prepare(
                        "SELECT COUNT(*)
                        FROM categories
                        WHERE parent_id = :parent_id
                        AND is_active = 1"
                    );
                    $thirdCountStmt->execute(['parent_id' => $subcategoryId]);
                    $activeThirdCategoryCount = (int) $thirdCountStmt->fetchColumn();

                    if ($thirdCategoryId > 0) {
                        $thirdStmt = $pdo->prepare(
                            "SELECT id
                            FROM categories
                            WHERE id = :id
                            AND parent_id = :parent_id
                            AND is_active = 1
                            LIMIT 1"
                        );
                        $thirdStmt->execute([
                            'id' => $thirdCategoryId,
                            'parent_id' => $subcategoryId,
                        ]);
                        if (!$thirdStmt->fetch()) {
                            throw new RuntimeException('Secilen alt-alt kateqoriya bu alt kateqoriyaya aid deyil.');
                        }
                        $categoryId = $thirdCategoryId;
                    } elseif ($activeThirdCategoryCount > 0) {
                        throw new RuntimeException('Bu alt kateqoriya ucun alt-alt kateqoriya secmek mecburidir.');
                    }
                } elseif ($activeSubcategoryCount > 0) {
                    throw new RuntimeException('Bu kateqoriya ucun alt kateqoriya secmek mecburidir.');
                }

                $definitions = fetchCategorySpecDefinitions($pdo, $categoryId);
                $normalizedSpecs = [];

                foreach ($definitions as $specKey => $definition) {
                    $normalizedValue = normalizeSpecValue($specInput[$specKey] ?? '', $definition);
                    if ($normalizedValue !== '') {
                        $normalizedSpecs[] = [
                            'spec_key' => $specKey,
                            'spec_value' => $normalizedValue,
                            'sort_order' => (int) ($definition['sort_order'] ?? 0),
                        ];
                    }
                }

                $pdo->beginTransaction();
                try {
                    if (maxhomeProductsOnlineVisibleColumnExists($pdo)) {
                        $stmt = $pdo->prepare(
                            "UPDATE products
                            SET category_id = :category_id, name = :name, base_price = :base_price, compare_at_price = :compare_at_price, stock_qty = :stock_qty, status = :status, online_visible = 1, updated_at = CURRENT_TIMESTAMP
                            WHERE id = :id"
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE products
                            SET category_id = :category_id, name = :name, base_price = :base_price, compare_at_price = :compare_at_price, stock_qty = :stock_qty, status = :status, updated_at = CURRENT_TIMESTAMP
                            WHERE id = :id"
                        );
                    }
                    $stmt->execute([
                        'id' => $id,
                        'category_id' => $categoryId,
                        'name' => $name,
                        'base_price' => $basePrice,
                        'compare_at_price' => $compareAtPrice,
                        'stock_qty' => $stockQty,
                        'status' => in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'active',
                    ]);

                    if (!empty($definitions)) {
                        $specKeys = array_keys($definitions);
                        $specPlaceholders = [];
                        $deleteParams = ['product_id' => $id];
                        foreach ($specKeys as $index => $specKey) {
                            $placeholder = 'spec_key_' . $index;
                            $specPlaceholders[] = ':' . $placeholder;
                            $deleteParams[$placeholder] = $specKey;
                        }
                        $deleteSql = "DELETE FROM product_specs WHERE product_id = :product_id AND spec_key IN (" .
                            implode(',', $specPlaceholders) . ")";
                        $deleteStmt = $pdo->prepare($deleteSql);
                        $deleteStmt->execute($deleteParams);
                    }

                    if (!empty($normalizedSpecs)) {
                        $insertSpecStmt = $pdo->prepare(
                            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
                            VALUES (:product_id, :spec_key, :spec_value, :sort_order)"
                        );
                        foreach ($normalizedSpecs as $spec) {
                            $insertSpecStmt->execute([
                                'product_id' => $id,
                                'spec_key' => $spec['spec_key'],
                                'spec_value' => $spec['spec_value'],
                                'sort_order' => $spec['sort_order'],
                            ]);
                        }
                    }

                    $pdo->prepare("DELETE FROM product_specs WHERE product_id = :product_id AND sort_order >= 9000")
                        ->execute(['product_id' => $id]);
                    if (!empty($customSpecs)) {
                        $insertCustomStmt = $pdo->prepare(
                            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
                            VALUES (:product_id, :spec_key, :spec_value, :sort_order)"
                        );
                        foreach ($customSpecs as $spec) {
                            $insertCustomStmt->execute([
                                'product_id' => $id,
                                'spec_key' => $spec['spec_key'],
                                'spec_value' => $spec['spec_value'],
                                'sort_order' => $spec['sort_order'],
                            ]);
                        }
                        maxhomeSyncCategoryExtraSpecTemplates($pdo, $categoryId, $customSpecs);
                    }

                    if (productBundleItemsTableExists($pdo)) {
                        $bundleIds = [];
                        $rawBundleField = $_POST['bundle_product_ids'] ?? null;
                        if (is_array($rawBundleField)) {
                            foreach ($rawBundleField as $part) {
                                $bid = (int) $part;
                                if ($bid > 0 && $bid !== $id) {
                                    $bundleIds[] = $bid;
                                }
                            }
                            $bundleIds = array_values(array_unique($bundleIds));
                        } else {
                            $rawBundle = trim((string) (is_scalar($rawBundleField) ? (string) $rawBundleField : ''));
                            $parts = $rawBundle === '' ? [] : preg_split('/[\s,;]+/', $rawBundle, -1, PREG_SPLIT_NO_EMPTY);
                            foreach ($parts as $part) {
                                if (ctype_digit((string) $part)) {
                                    $bid = (int) $part;
                                    if ($bid > 0 && $bid !== $id) {
                                        $bundleIds[] = $bid;
                                    }
                                }
                            }
                            $bundleIds = array_values(array_unique($bundleIds));
                        }
                        if (count($bundleIds) > 20) {
                            throw new RuntimeException('Ekosistem siyahisinda 20-den cox mehsul ID ola bilmez.');
                        }
                        foreach ($bundleIds as $bid) {
                            $existsStmt = $pdo->prepare(
                                "SELECT pr.id FROM products pr WHERE pr.id = :id AND pr.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'pr') . " LIMIT 1"
                            );
                            $existsStmt->execute(['id' => $bid]);
                            if (!$existsStmt->fetch()) {
                                throw new RuntimeException('Ekosistem siyahisinda movcud olmayan ve ya aktiv olmayan ID: ' . $bid);
                            }
                        }

                        $pdo->prepare('DELETE FROM product_bundle_items WHERE parent_product_id = :pid')
                            ->execute(['pid' => $id]);
                        if (!empty($bundleIds)) {
                            $insBundle = $pdo->prepare(
                                'INSERT INTO product_bundle_items (parent_product_id, bundle_product_id, sort_order)
                                VALUES (:parent_id, :bundle_id, :sort_order)'
                            );
                            foreach ($bundleIds as $sort => $bid) {
                                $insBundle->execute([
                                    'parent_id' => $id,
                                    'bundle_id' => $bid,
                                    'sort_order' => $sort,
                                ]);
                            }
                        }
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $message = 'Mehsul ugurla yenilendi.';
                if (isset($_FILES['product_image']) && is_array($_FILES['product_image'])) {
                    $uploadedFiles = normalizeUploadFileList($_FILES['product_image']);
                    $hasPrimaryStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM product_images WHERE product_id = :product_id AND is_primary = 1"
                    );
                    $hasPrimaryStmt->execute(['product_id' => $id]);
                    $hasPrimary = (int) $hasPrimaryStmt->fetchColumn() > 0;

                    $uploadedCount = 0;
                    $failedUploads = [];
                    foreach ($uploadedFiles as $idx => $imgFile) {
                        $imgErr = (int) ($imgFile['error'] ?? UPLOAD_ERR_NO_FILE);
                        if ($imgErr === UPLOAD_ERR_NO_FILE || empty($imgFile['name'])) {
                            continue;
                        }
                        if ($imgErr !== UPLOAD_ERR_OK) {
                            $failedUploads[] = 'fayl #' . ($idx + 1) . ' xetasi';
                            continue;
                        }
                        try {
                            $makePrimary = !$hasPrimary && $uploadedCount === 0;
                            saveProductImageFromUpload($pdo, $id, $imgFile, $makePrimary, $uploadedCount);
                            $uploadedCount++;
                        } catch (Throwable $imgEx) {
                            $failedUploads[] = 'fayl #' . ($idx + 1) . ': ' . $imgEx->getMessage();
                        }
                    }
                    if ($uploadedCount > 0) {
                        $message .= ' ' . $uploadedCount . ' sekil elave edildi.';
                    }
                    if (!empty($failedUploads)) {
                        $message .= ' Bezi sekiller yuklenmedi: ' . implode('; ', $failedUploads);
                    }
                }
                $editId = $id;
            }

            if ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('Silmek ucun mehsul secin.');
                }
                maxhomeAssertCatalogProduct($pdo, $id);
                $pdo->beginTransaction();
                try {
                    // Mehsul "ne olursa olsun" silinmelidir talebine gore sifaris setirlerini de temizleyirik.
                    $pdo->prepare('DELETE FROM order_items WHERE product_id = :id')->execute(['id' => $id]);
                    $pdo->prepare('DELETE FROM cart_items WHERE product_id = :id')->execute(['id' => $id]);
                    $pdo->prepare('DELETE FROM wishlist_items WHERE product_id = :id')->execute(['id' => $id]);
                    $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $message = 'Mehsul silindi.';
            }

            if ($action === 'delete_product_image') {
                $productId = (int) ($_POST['product_id'] ?? 0);
                $imageId = (int) ($_POST['image_id'] ?? 0);
                if ($productId <= 0 || $imageId <= 0) {
                    throw new RuntimeException('Sekil silme melumatlari sehvdir.');
                }

                maxhomeAssertCatalogProduct($pdo, $productId);

                $imgStmt = $pdo->prepare(
                    "SELECT id, product_id, image_url
                    FROM product_images
                    WHERE id = :id AND product_id = :product_id
                    LIMIT 1"
                );
                $imgStmt->execute([
                    'id' => $imageId,
                    'product_id' => $productId,
                ]);
                $imgRow = $imgStmt->fetch();
                if (!$imgRow) {
                    throw new RuntimeException('Silinecek sekil tapilmadi.');
                }

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM product_images WHERE id = :id")
                        ->execute(['id' => $imageId]);

                    $primaryCntStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM product_images WHERE product_id = :product_id AND is_primary = 1"
                    );
                    $primaryCntStmt->execute(['product_id' => $productId]);
                    if ((int) $primaryCntStmt->fetchColumn() === 0) {
                        $fallbackStmt = $pdo->prepare(
                            "SELECT id FROM product_images
                            WHERE product_id = :product_id
                            ORDER BY sort_order ASC, id ASC
                            LIMIT 1"
                        );
                        $fallbackStmt->execute(['product_id' => $productId]);
                        $fallbackId = (int) $fallbackStmt->fetchColumn();
                        if ($fallbackId > 0) {
                            $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id")
                                ->execute(['id' => $fallbackId]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $imageUrl = (string) ($imgRow['image_url'] ?? '');
                if (str_starts_with($imageUrl, 'uploads/')) {
                    $fullPath = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $imageUrl);
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $message = 'Sekil silindi.';
                $editId = $productId;
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $adminPerPage = 50;
    $adminPage = max(1, (int) ($_GET['page'] ?? 1));
    $newProductSlugPreview = 'p-' . bin2hex(random_bytes(8));
    $catalogOnlySql = maxhomeProductsOnlineVisibleSql($pdo, 'p');
    $fromSql = "
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id";
    $params = [];
    $whereParts = [];
    if ($q !== '') {
        $whereParts[] = '(p.name LIKE :q_name OR p.sku LIKE :q_sku)';
        $params['q_name'] = '%' . $q . '%';
        $params['q_sku'] = '%' . $q . '%';
    }
    if ($catalogOnlySql !== '') {
        $whereParts[] = ltrim($catalogOnlySql, ' AND ');
    }
    $whereSql = $whereParts !== [] ? ' WHERE ' . implode(' AND ', $whereParts) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $countStmt->execute($params);
    $adminTotalProducts = (int) $countStmt->fetchColumn();
    $adminTotalPages = max(1, (int) ceil($adminTotalProducts / $adminPerPage));
    if ($adminPage > $adminTotalPages) {
        $adminPage = $adminTotalPages;
    }
    $adminOffset = ($adminPage - 1) * $adminPerPage;
    $adminRangeStart = $adminTotalProducts > 0 ? $adminOffset + 1 : 0;
    $adminRangeEnd = min($adminOffset + $adminPerPage, $adminTotalProducts);

    $adminPaginationUrl = static function (string $searchQuery, int $targetPage): string {
        $query = [];
        if ($searchQuery !== '') {
            $query['q'] = $searchQuery;
        }
        if ($targetPage > 1) {
            $query['page'] = (string) $targetPage;
        }
        $qs = http_build_query($query);

        return 'admin_products.php' . ($qs !== '' ? '?' . $qs : '');
    };

    $sql = "SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.stock_qty, p.status, c.name AS category_name, b.name AS brand_name"
        . $fromSql
        . $whereSql
        . " ORDER BY p.id DESC LIMIT {$adminPerPage} OFFSET {$adminOffset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll() ?: [];

    if ($editId > 0) {
        $editVisibleSql = maxhomeProductsOnlineVisibleColumnExists($pdo) ? ' AND online_visible = 1' : '';
        $editStmt = $pdo->prepare(
            "SELECT id, name, base_price, compare_at_price, stock_qty, status, category_id
            FROM products
            WHERE id = :id{$editVisibleSql}
            LIMIT 1"
        );
        $editStmt->execute(['id' => $editId]);
        $editingProduct = $editStmt->fetch() ?: null;
        if (!$editingProduct) {
            $message = 'Bu mehsul anbar panelinde idare olunur ve ya tapilmadi.';
            $messageType = 'error';
            $editId = 0;
        }
        if ($editingProduct) {
            $categoryLookupStmt = $pdo->prepare(
                "SELECT id, parent_id
                FROM categories
                WHERE id = :id
                LIMIT 1"
            );
            $categoryLookupStmt->execute(['id' => (int) $editingProduct['category_id']]);
            $editingCategoryRow = $categoryLookupStmt->fetch();
            if ($editingCategoryRow) {
                $editingCategoryParentId = $editingCategoryRow['parent_id'] !== null ? (int) $editingCategoryRow['parent_id'] : null;
                if ($editingCategoryParentId !== null && $editingCategoryParentId > 0) {
                    $parentLookupStmt = $pdo->prepare(
                        "SELECT id, parent_id
                        FROM categories
                        WHERE id = :id
                        LIMIT 1"
                    );
                    $parentLookupStmt->execute(['id' => $editingCategoryParentId]);
                    $editingParentRow = $parentLookupStmt->fetch() ?: null;
                    $editingGrandParentId = $editingParentRow && $editingParentRow['parent_id'] !== null
                        ? (int) $editingParentRow['parent_id']
                        : null;

                    if ($editingGrandParentId !== null && $editingGrandParentId > 0) {
                        $editingParentCategoryId = $editingGrandParentId;
                        $editingSubcategoryId = $editingCategoryParentId;
                        $editingThirdCategoryId = (int) $editingCategoryRow['id'];
                    } else {
                        $editingParentCategoryId = $editingCategoryParentId;
                        $editingSubcategoryId = (int) $editingCategoryRow['id'];
                        $editingThirdCategoryId = 0;
                    }
                } else {
                    $editingParentCategoryId = (int) $editingCategoryRow['id'];
                    $editingSubcategoryId = 0;
                    $editingThirdCategoryId = 0;
                }
            }
            $editingSpecDefinitions = fetchCategorySpecDefinitions($pdo, (int) $editingProduct['category_id']);
            $specValueStmt = $pdo->prepare(
                "SELECT spec_key, spec_value
                FROM product_specs
                WHERE product_id = :product_id"
            );
            $specValueStmt->execute(['product_id' => (int) $editingProduct['id']]);
            foreach ($specValueStmt->fetchAll() ?: [] as $specRow) {
                $editingProductSpecs[(string) $specRow['spec_key']] = (string) $specRow['spec_value'];
            }
            $customSpecStmt = $pdo->prepare(
                "SELECT spec_key, spec_value
                FROM product_specs
                WHERE product_id = :product_id
                AND sort_order >= 9000
                ORDER BY sort_order ASC, id ASC"
            );
            $customSpecStmt->execute(['product_id' => (int) $editingProduct['id']]);
            foreach ($customSpecStmt->fetchAll() ?: [] as $customSpecRow) {
                $editingCustomSpecs[] = [
                    'label' => (string) $customSpecRow['spec_key'],
                    'value' => (string) $customSpecRow['spec_value'],
                ];
            }
            $primaryImgStmt = $pdo->prepare(
                "SELECT image_url FROM product_images
                WHERE product_id = :product_id
                ORDER BY is_primary DESC, sort_order ASC, id ASC
                LIMIT 1"
            );
            $primaryImgStmt->execute(['product_id' => (int) $editingProduct['id']]);
            $primaryImgRow = $primaryImgStmt->fetch();
            if ($primaryImgRow && !empty($primaryImgRow['image_url'])) {
                $editingProductPrimaryImage = (string) $primaryImgRow['image_url'];
            }
            $allImagesStmt = $pdo->prepare(
                "SELECT id, image_url, is_primary, sort_order
                FROM product_images
                WHERE product_id = :product_id
                ORDER BY is_primary DESC, sort_order ASC, id ASC"
            );
            $allImagesStmt->execute(['product_id' => (int) $editingProduct['id']]);
            $editingProductImages = $allImagesStmt->fetchAll() ?: [];

            if (productBundleItemsTableExists($pdo)) {
                $bundleListStmt = $pdo->prepare(
                    'SELECT p.id, p.name, p.sku, p.base_price, p.status
                    FROM product_bundle_items pbi
                    INNER JOIN products p ON p.id = pbi.bundle_product_id
                    WHERE pbi.parent_product_id = :pid
                    ORDER BY pbi.sort_order ASC, pbi.id ASC'
                );
                $bundleListStmt->execute(['pid' => (int) $editingProduct['id']]);
                foreach ($bundleListStmt->fetchAll() ?: [] as $br) {
                    $editingBundleProducts[] = [
                        'id' => (int) $br['id'],
                        'name' => (string) $br['name'],
                        'sku' => (string) $br['sku'],
                        'base_price' => (float) $br['base_price'],
                        'status' => (string) $br['status'],
                    ];
                }
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="az">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Mehsullar</title>
        <link rel="stylesheet" href="../assets/css/foundation.css">
        <link rel="stylesheet" href="assets/css/admin.css">
        <style>
            .create-product-stack { display:grid; gap:14px; }
            .spec-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
            .spec-grid input:not([type="hidden"]),
            .spec-grid select { width: 270px; max-width: 100%; }
            .spec-help { font-size:12px; opacity:.8; margin-top:4px; }
            .product-edit-thumb { max-width: 160px; max-height: 160px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(0,0,0,.08); }
            .extra-spec-list { display:grid; gap:10px; margin-top:10px; }
            .extra-spec-row { display:grid; grid-template-columns: 1fr 1fr auto; gap:8px; align-items:end; }
            .import-progress { display:none; margin-top:12px; }
            .import-progress.is-active { display:block; }
            .import-progress__bar { height:10px; background:rgba(0,0,0,.08); border-radius:999px; overflow:hidden; }
            .import-progress__fill { height:100%; width:0; background:#1f6feb; transition:width .25s ease; }
            .import-progress__meta { display:flex; justify-content:space-between; gap:12px; margin-top:8px; font-size:13px; }
            .import-failed-report { display:none; margin-top:14px; border:1px solid rgba(220,38,38,.25); border-radius:10px; background:rgba(220,38,38,.04); padding:12px; }
            .import-failed-report.is-visible { display:block; }
            .import-failed-report h4 { margin:0 0 8px; font-size:15px; }
            .import-failed-list { max-height:320px; overflow:auto; display:grid; gap:8px; }
            .import-failed-item { padding:8px 10px; border-radius:8px; background:#fff; border:1px solid rgba(0,0,0,.08); font-size:13px; }
            .import-failed-item strong { display:block; margin-bottom:4px; }
            .import-failed-item span { color:#6b7280; }
        </style>
    </head>
    <body>
    <div class="admin-layout">
        <?php adminSidebar('products'); ?>
        <main class="admin-main">
            <header class="admin-top">
                <div>
                    <h1 class="admin-title">Mehsul idareetmesi</h1>
                    <p class="admin-subtitle">Axtar, elave et, redakte et ve sil.</p>
                </div>
            </header>

            <?php if ($message !== ''): ?>
                <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                    <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <section class="card" style="margin-bottom: 14px;">
                <h3 class="section-title">Məhsul idxalı (API / JSON)</h3>
                <p class="spec-help" style="margin: 0 0 12px;">
                    Məhsullar <strong>API-dan</strong> və ya <strong>JSON faylından</strong> idxal oluna bilər.
                    Böyük siyahılar avtomatik <strong>addım-addım</strong> emal olunur (hər addımda <?php echo PRODUCT_JSON_IMPORT_BATCH_SIZE; ?> məhsul).
                    <strong>Idxal mövcud məhsulları silmir</strong> — yeni məhsul əlavə edir; eyni SKU olanlarda qiymət yenilənir və stok üstünə əlavə olunur.
                </p>
                <form id="json-import-form" enctype="multipart/form-data" style="display:grid; gap:12px; max-width:760px;">
                    <div>
                        <label>API URL</label>
                        <input name="api_url" type="url" value="<?php echo e($productsApiUrl); ?>" placeholder="https://maxhome-api.vercel.app/api/products">
                    </div>
                    <div>
                        <label>API Token (Bearer)</label>
                        <input name="api_token" type="password" value="<?php echo e($productsApiToken); ?>" autocomplete="off" placeholder="mh_...">
                        <div class="spec-help">Token yalnız server tərəfdə saxlanılır və API sorğusunda istifadə olunur.</div>
                    </div>
                    <div>
                        <label>JSON faylı (istəyə bağlı)</label>
                        <input id="products_json" name="products_json" type="file" accept=".json,application/json">
                        <div class="spec-help">API əvəzinə lokal JSON faylı da yükləyə bilərsiniz.</div>
                    </div>
                    <div>
                        <label>İlk neçə məhsul idxal olunsun? (0 = hamısı)</label>
                        <input name="import_limit" type="number" min="0" value="0" placeholder="Mes: 5">
                        <div class="spec-help">Test üçün 5 yaza bilərsiniz; bütün fayl üçün 0 buraxın.</div>
                    </div>
                    <div>
                        <label>Varsayılan kateqoriya (uyğun kateqoriya tapılmazsa)</label>
                        <select name="import_category_id">
                            <option value="">Secin</option>
                            <?php foreach ($primaryCategories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>"><?php echo e((string) $category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="create_missing_categories" value="1" checked>
                            Tapılmayan kateqoriyaları avtomatik yarat
                        </label>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="create_missing_subcategories" value="1" checked>
                            Tapılmayan alt kateqoriyaları avtomatik yarat
                        </label>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="create_missing_sub_sub_categories" value="1" checked>
                            Tapılmayan alt-alt kateqoriyaları avtomatik yarat
                        </label>
                    </div>
                    <div>
                        <label>Yeni kateqoriyaların parent-i</label>
                        <select name="import_parent_category_id">
                            <option value="0">Kök kateqoriya</option>
                            <?php foreach ($primaryCategories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>"><?php echo e((string) $category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <details class="spec-help">
                        <summary>JSON formatı (sizin fayl)</summary>
                        <pre style="white-space:pre-wrap; margin-top:8px; font-size:12px;">{
  "merchant": "Instax Baku",
  "source": "birmarket.az",
  "count": 726,
  "products": [
    {
      "name": "Məhsul adı",
      "sku": "7702018488704",
      "brand": "Gillette",
      "category": "Təraş üçün ülgüclər",
      "main_category": "Gözəllik və baxım",
      "sub_category": "Təraş",
      "sub_sub_category": "Təraş üçün ülgüclər",
      "price": 27.99
    }
  ]
}</pre>
                        <div class="spec-help" style="margin-top:8px;">
                            Yalniz "category" sahəsi (birmarket.az) tapilmazsa avtomatik yaradilir — checkbox secimi teleb olunmur.
                            main/sub/sub_sub ucun yuxaridaki 3 secim tetbiq olunur.
                        </div>
                    </details>
                    <div id="json-import-flash" class="flash" style="display:none;"></div>
                    <div id="json-import-report" class="import-failed-report" aria-live="polite">
                        <h4 id="json-import-report-title">Yuklenmeyen mehsullar</h4>
                        <div id="json-import-failed-list" class="import-failed-list"></div>
                    </div>
                    <div id="json-import-progress" class="import-progress" aria-live="polite">
                        <div class="import-progress__bar">
                            <div id="json-import-progress-fill" class="import-progress__fill"></div>
                        </div>
                        <div class="import-progress__meta">
                            <span id="json-import-progress-text">Hazirlanir...</span>
                            <span id="json-import-progress-percent">0%</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button id="api-import-submit" class="btn btn-primary" type="button">API-dan cek ve idxal et</button>
                        <button id="json-import-submit" class="btn btn-muted" type="submit">JSON fayl idxal et</button>
                        <button id="json-import-cancel" class="btn btn-muted" type="button" style="display:none;">Dayandir</button>
                    </div>
                </form>
            </section>

            <section class="card" style="margin-bottom: 14px;">
                <h3 class="section-title">Yeni mehsul</h3>
                <form method="post" id="create-product-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="create-product-stack">
                        <div class="form-grid">
                            <div><label>Ad</label><input name="name" required></div>
                            <div>
                                <label>Slug</label>
                                <input value="<?php echo e($newProductSlugPreview); ?>" readonly tabindex="-1" aria-readonly="true">
                                <div class="spec-help">Yadda saxlananda avtomatik unikal slug yaradilir.</div>
                            </div>
                            <div><label>SKU</label><input name="sku" required></div>
                            <div>
                                <label>Endirimli qiymet (isteye baglı)</label>
                                <input name="base_price" type="number" step="0.01" min="0" placeholder="Mes: 999.00">
                                <div class="spec-help">Magazada satış üçün cari qiymət (₼); boş buraxıla bilər.</div>
                            </div>
                            <div>
                                <label>Siyahı qiymet (isteye baglı)</label>
                                <input name="compare_at_price" type="number" step="0.01" min="0" placeholder="Mes: 1299.00">
                                <div class="spec-help">Endirimdən əvvəlki daha yüksək qiymət; daxil etdikdə vitrində xəttlə göstərilir. Endirimli qiymətdən böyük olmalıdır.</div>
                            </div>
                            <div><label>Stok</label><input name="stock_qty" type="number" min="0" value="0"></div>
                            <div>
                                <label>Kateqoriya</label>
                                <select name="category_id" id="category_id" required>
                                    <option value="">Secin</option>
                                    <?php foreach ($primaryCategories as $category): ?>
                                        <option value="<?php echo (int) $category['id']; ?>"><?php echo e((string) $category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Alt kateqoriya</label>
                                <select name="subcategory_id" id="subcategory_id">
                                    <option value="">Secin</option>
                                </select>
                                <div id="subcategory-help" class="spec-help">Eger varsa, alt kateqoriya secin.</div>
                            </div>
                            <div>
                                <label>Alt-alt kateqoriya</label>
                                <select name="third_category_id" id="third_category_id">
                                    <option value="">Secin</option>
                                </select>
                                <div id="third-category-help" class="spec-help">Eger varsa, alt-alt kateqoriya secin.</div>
                            </div>
                            <div>
                                <label>Brend</label>
                                <input type="text" name="brand_name" list="brand-name-list" placeholder="Brend yazin">
                                <datalist id="brand-name-list">
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo e((string) $brand['name']); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div>
                                <label>Status</label>
                                <select name="status">
                                    <option value="active">active</option>
                                    <option value="draft">draft</option>
                                    <option value="archived">archived</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Qisa tesvir</label>
                                <textarea name="short_description"></textarea>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Şekil (isteğe bağlı)</label>
                                <div class="file-input" data-file-input>
                                    <input class="file-input__native" id="create-product-image" name="product_image[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
                                    <button class="btn btn-muted file-input__btn" type="button" data-file-btn>Dosyaları Seç</button>
                                    <div class="file-input__label" data-file-label>Dosya seçilmedi</div>
                                </div>
                                <div class="image-picker" data-image-picker="create-product-image" aria-label="Seçilen görsel önizlemeleri">
                                    <div class="image-slot image-picker__primary" data-slot="0"><div class="image-slot__plus">+</div></div>
                                    <div class="image-picker__grid">
                                        <div class="image-slot" data-slot="1"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="2"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="3"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="4"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="5"><div class="image-slot__plus">+</div></div>
                                    </div>
                                </div>
                                <div class="spec-help">JPG, PNG, WEBP; max. 20MB (her fayl). Birden çok şekil seçmek olur; birincisi birincil (primary) olacaq.</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="section-title" style="margin-bottom: 8px;">Xususiyyetler</h4>
                            <div id="spec-loading" class="spec-help">Kateqoriya secildikden sonra xususiyyetler gelecek.</div>
                        </div>
                        <div id="spec-fields" class="spec-grid"></div>
                        <div>
                            <h4 class="section-title" style="margin: 8px 0;">Elave ozellikler</h4>
                            <p class="spec-help">Bir defe elave etdiyiniz ozellikler secilen kateqoriya ucun Xususiyyetler bolmesinde avtomatik gorunur.</p>
                            <button class="btn btn-muted" type="button" id="add-extra-spec-btn">+ Elave ozellik elave et</button>
                            <div id="extra-spec-list" class="extra-spec-list"></div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit" style="margin-top: 12px;">Mehsulu elave et</button>
                </form>
            </section>

            <?php if ($editingProduct): ?>
                <section class="card" style="margin-bottom: 14px;">
                    <h3 class="section-title">Mehsulu redakte et #<?php echo (int) $editingProduct['id']; ?></h3>
                    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Deyisiklikler yadda saxlanilsin?');">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int) $editingProduct['id']; ?>">
                        <input type="hidden" name="product_id" value="<?php echo (int) $editingProduct['id']; ?>">
                        <input type="hidden" name="image_id" id="edit-delete-image-id" value="">
                        <div class="form-grid">
                            <div><label>Ad</label><input name="name" value="<?php echo e((string) $editingProduct['name']); ?>" required></div>
                            <div>
                                <label>Kateqoriya</label>
                                <select name="category_id" id="edit_category_id" required>
                                    <option value="">Secin</option>
                                    <?php foreach ($primaryCategories as $category): ?>
                                        <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $category['id'] === $editingParentCategoryId ? 'selected' : ''; ?>>
                                            <?php echo e((string) $category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Alt kateqoriya</label>
                                <select name="subcategory_id" id="edit_subcategory_id">
                                    <option value="">Secin</option>
                                    <?php foreach (($subcategoriesByParent[$editingParentCategoryId] ?? []) as $subCategory): ?>
                                        <option value="<?php echo (int) $subCategory['id']; ?>" <?php echo (int) $subCategory['id'] === $editingSubcategoryId ? 'selected' : ''; ?>>
                                            <?php echo e((string) $subCategory['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="edit-subcategory-help" class="spec-help">Eger varsa, alt kateqoriya secin.</div>
                            </div>
                            <div>
                                <label>Alt-alt kateqoriya</label>
                                <select name="third_category_id" id="edit_third_category_id">
                                    <option value="">Secin</option>
                                    <?php foreach (($subcategoriesByParent[$editingSubcategoryId] ?? []) as $thirdCategory): ?>
                                        <option value="<?php echo (int) $thirdCategory['id']; ?>" <?php echo (int) $thirdCategory['id'] === $editingThirdCategoryId ? 'selected' : ''; ?>>
                                            <?php echo e((string) $thirdCategory['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="edit-third-category-help" class="spec-help">Eger varsa, alt-alt kateqoriya secin.</div>
                            </div>
                            <div>
                                <label>Endirimli qiymet (isteye baglı)</label>
                                <input name="base_price" type="number" step="0.01" min="0" placeholder="Mes: 999.00" value="<?php echo (float) $editingProduct['base_price'] > 0 ? e(number_format((float) $editingProduct['base_price'], 2, '.', '')) : ''; ?>">
                                <div class="spec-help">Magazada satış üçün cari qiymət (₼); boş buraxıla bilər.</div>
                            </div>
                            <div>
                                <label>Siyahı qiymet (isteye baglı)</label>
                                <input name="compare_at_price" type="number" step="0.01" min="0" placeholder="Mes: 1299.00" value="<?php echo isset($editingProduct['compare_at_price']) && $editingProduct['compare_at_price'] !== null ? e(number_format((float) $editingProduct['compare_at_price'], 2, '.', '')) : ''; ?>">
                                <div class="spec-help">Endirimdən əvvəlki qiymət; boş buraxın və ya endirimli qiymətdən böyük yazın.</div>
                            </div>
                            <div><label>Stok</label><input name="stock_qty" type="number" min="0" value="<?php echo (int) $editingProduct['stock_qty']; ?>"></div>
                            <div>
                                <label>Status</label>
                                <select name="status">
                                    <option value="active" <?php echo $editingProduct['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                                    <option value="draft" <?php echo $editingProduct['status'] === 'draft' ? 'selected' : ''; ?>>draft</option>
                                    <option value="archived" <?php echo $editingProduct['status'] === 'archived' ? 'selected' : ''; ?>>archived</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Şekil (isteğe bağlı)</label>
                                <?php if ($editingProductPrimaryImage): ?>
                                    <div style="margin-bottom:8px;">
                                        <img class="product-edit-thumb" src="../<?php echo e($editingProductPrimaryImage); ?>" alt="">
                                        <div class="spec-help">Cari birincil sekil. Yeni fayl secseniz, magazada o gosterilecek.</div>
                                    </div>
                                <?php else: ?>
                                    <p class="spec-help" style="margin:0 0 8px;">Hec bir sekil yoxdur; yukledikden sonra birincil kimi qeyd olunacaq.</p>
                                <?php endif; ?>
                                <div class="file-input" data-file-input>
                                    <input class="file-input__native" id="edit-product-image" name="product_image[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
                                    <button class="btn btn-muted file-input__btn" type="button" data-file-btn>Dosyaları Seç</button>
                                    <div class="file-input__label" data-file-label>Dosya seçilmedi</div>
                                </div>
                                <div class="image-picker" data-image-picker="edit-product-image" aria-label="Seçilen görsel önizlemeleri">
                                    <div class="image-slot image-picker__primary" data-slot="0"><div class="image-slot__plus">+</div></div>
                                    <div class="image-picker__grid">
                                        <div class="image-slot" data-slot="1"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="2"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="3"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="4"><div class="image-slot__plus">+</div></div>
                                        <div class="image-slot" data-slot="5"><div class="image-slot__plus">+</div></div>
                                    </div>
                                </div>
                                <div class="spec-help">JPG, PNG, WEBP; max. 20MB (her fayl). Birden çok şekil seçmek olur. <a href="upload_product_image.php">Bütün şekilleri idare et</a></div>
                                <?php if (!empty($editingProductImages)): ?>
                                    <div style="margin-top:10px; display:grid; gap:8px;">
                                        <?php foreach ($editingProductImages as $img): ?>
                                            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px; border:1px solid rgba(0,0,0,.08); border-radius:8px;">
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <img class="product-edit-thumb" style="max-width:72px; max-height:72px;" src="../<?php echo e((string) $img['image_url']); ?>" alt="">
                                                    <div class="spec-help" style="margin:0;">
                                                        <?php echo (int) $img['is_primary'] === 1 ? 'Birincil sekil' : 'Elave sekil'; ?>
                                                    </div>
                                                </div>
                                                <button
                                                    class="btn btn-danger"
                                                    type="button"
                                                    onclick="submitEditImageDelete(<?php echo (int) $img['id']; ?>)"
                                                >Sil</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (productBundleItemsTableExists($pdo)): ?>
                                <div class="bundle-picker" id="bundle-picker" style="grid-column: 1 / -1;" data-exclude-id="<?php echo (int) $editingProduct['id']; ?>">
                                    <label class="bundle-picker__label">Ekosistem / kampaniya — birge teklif olunan mehsullar</label>
                                    <p class="spec-help bundle-picker__hint">Axtarışdan seçin; sıra mağaza blokunda eyni qaydada göstərilir. Yalnız <strong>active</strong> məhsullar saxlanılır.
                                        <a href="admin_ecosystem.php?parent=<?php echo (int) $editingProduct['id']; ?>">Ekosistem üçün ayrıca admin səhifəsi</a>
                                    </p>
                                    <div class="bundle-picker__search-wrap">
                                        <input type="search" id="bundle-search-q" class="bundle-picker__search" placeholder="Ad ve ya SKU ile axtar (min. 2 simvol)..." autocomplete="off" />
                                        <div id="bundle-search-results" class="bundle-picker__results" hidden></div>
                                    </div>
                                    <ul id="bundle-selected-list" class="bundle-selected-list" aria-label="Secilmis ekosistem mehsullari"></ul>
                                    <div id="bundle-hidden-inputs"></div>
                                    <details class="bundle-picker__advanced">
                                        <summary>ID siyahisi ile surətli əlavə</summary>
                                        <p class="spec-help">Nümunə: <code>12, 15, 18</code> ve ya her setrde bir ID. Yalnız aktiv məhsullar əlavə olunur.</p>
                                        <textarea id="bundle-bulk-ids" class="bundle-picker__bulk" rows="3" placeholder="12, 15, 18"></textarea>
                                        <button type="button" class="btn btn-muted bundle-picker__bulk-btn" id="bundle-bulk-apply">Siyahiya elave et</button>
                                        <span id="bundle-bulk-msg" class="bundle-picker__bulk-msg" role="status"></span>
                                    </details>
                                </div>
                                <script type="application/json" id="bundle-picker-initial"><?php
                                    echo json_encode(
                                        $editingBundleProducts,
                                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                                    );
                                ?></script>
                            <?php endif; ?>
                        </div>
                        <h4 class="section-title" style="margin-top:14px;">Xususiyyetler</h4>
                        <div id="edit-spec-loading" class="spec-help">
                            <?php echo !empty($editingSpecDefinitions) ? '' : 'Bu kateqoriya ucun elave xususiyyet yoxdur.'; ?>
                        </div>
                        <div id="edit-spec-fields" class="spec-grid">
                            <?php foreach ($editingSpecDefinitions as $spec): ?>
                                <?php
                                $currentSpecValue = (string) ($editingProductSpecs[(string) $spec['spec_key']] ?? '');
                                echo renderSpecField($spec, 'spec', $currentSpecValue);
                                ?>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 12px;">
                            <h4 class="section-title" style="margin: 8px 0;">Elave ozellikler</h4>
                            <p class="spec-help">Bir defe elave etdiyiniz ozellikler secilen kateqoriya ucun Xususiyyetler bolmesinde avtomatik gorunur.</p>
                            <button class="btn btn-muted" type="button" id="edit-add-extra-spec-btn">+ Elave ozellik elave et</button>
                            <div id="edit-extra-spec-list" class="extra-spec-list"></div>
                        </div>
                        <div class="stack-sm" style="margin-top:12px;">
                            <button class="btn btn-primary" type="submit">Yenile</button>
                            <a class="btn btn-muted" href="admin_products.php<?php echo $q !== '' ? '?q=' . urlencode($q) : ''; ?>">Legv et</a>
                        </div>
                    </form>
                </section>
                <script type="application/json" id="edit-spec-values"><?php
                    echo json_encode(
                        $editingProductSpecs,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                ?></script>
                <script type="application/json" id="edit-extra-spec-values"><?php
                    $editingExtraSpecValueMap = [];
                    foreach ($editingCustomSpecs as $customSpecRow) {
                        $editingExtraSpecValueMap[(string) $customSpecRow['label']] = (string) $customSpecRow['value'];
                    }
                    echo json_encode(
                        $editingExtraSpecValueMap,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                ?></script>
            <?php endif; ?>

            <section class="card">
                <form method="get" style="display:flex; gap:10px; margin-bottom: 12px;">
                    <input name="q" value="<?php echo e($q); ?>" placeholder="Mehsul adina ve ya SKU-a gore axtar...">
                    <button class="btn btn-muted" type="submit">Axtar</button>
                    <a class="btn btn-muted" href="admin_products.php">Temizle</a>
                </form>
                <?php if ($adminTotalProducts > 0): ?>
                    <p class="spec-help" style="margin: 0 0 12px;">
                        <?php echo $adminRangeStart; ?>–<?php echo $adminRangeEnd; ?> / <?php echo $adminTotalProducts; ?> mehsul
                        <?php if ($adminTotalPages > 1): ?>
                            · Sehife <?php echo $adminPage; ?> / <?php echo $adminTotalPages; ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Ad</th>
                            <th>Kateqoriya</th>
                            <th>Brend</th>
                            <th>Qiymet</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Emeliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="8">Mehsul tapilmadi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $idx => $product): ?>
                                <tr>
                                    <td><?php echo $adminOffset + $idx + 1; ?></td>
                                    <td><?php echo e((string) $product['name']); ?><br><small><?php echo e((string) $product['sku']); ?></small><br><small class="spec-help">DB ID: <?php echo (int) $product['id']; ?></small></td>
                                    <td><?php echo e((string) ($product['category_name'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($product['brand_name'] ?? '-')); ?></td>
                                    <td><?php echo number_format((float) $product['base_price'], 2); ?> ₼</td>
                                    <td><?php echo (int) $product['stock_qty']; ?></td>
                                    <td><span class="status-badge status-<?php echo e((string) $product['status']); ?>"><?php echo e((string) $product['status']); ?></span></td>
                                    <td>
                                        <div class="stack-sm">
                                            <a class="btn btn-muted" href="admin_products.php?edit=<?php echo (int) $product['id']; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">Duzenle</a>
                                            <form class="inline-form" method="post" onsubmit="return confirm('Bu mehsul silinsin?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
                                                <button class="btn btn-danger" type="submit">Sil</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($adminTotalPages > 1): ?>
                    <nav style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:16px; padding-top:12px; border-top:1px solid rgba(0,0,0,0.08);">
                        <?php if ($adminPage > 1): ?>
                            <a class="btn btn-muted" href="<?php echo e($adminPaginationUrl($q, $adminPage - 1)); ?>">Evvelki</a>
                        <?php else: ?>
                            <span class="btn btn-muted" style="opacity:0.5; pointer-events:none;">Evvelki</span>
                        <?php endif; ?>
                        <span class="spec-help">Sehife <?php echo $adminPage; ?> / <?php echo $adminTotalPages; ?></span>
                        <?php if ($adminPage < $adminTotalPages): ?>
                            <a class="btn btn-muted" href="<?php echo e($adminPaginationUrl($q, $adminPage + 1)); ?>">Novbeti</a>
                        <?php else: ?>
                            <span class="btn btn-muted" style="opacity:0.5; pointer-events:none;">Novbeti</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script>
    (() => {
        const form = document.getElementById('create-product-form');
        if (!form) return;

        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const thirdCategorySelect = document.getElementById('third_category_id');
        const subcategoryHelp = document.getElementById('subcategory-help');
        const thirdCategoryHelp = document.getElementById('third-category-help');
        const specFields = document.getElementById('spec-fields');
        const specLoading = document.getElementById('spec-loading');
        const extraSpecList = document.getElementById('extra-spec-list');
        const addExtraSpecBtn = document.getElementById('add-extra-spec-btn');
        const subcategoriesByParent = <?php
            echo json_encode(
                $subcategoriesByParent,
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
        ?> || {};

        function getEffectiveCategoryId() {
            const parentId = categorySelect ? categorySelect.value : '';
            const subId = subcategorySelect ? subcategorySelect.value : '';
            const thirdId = thirdCategorySelect ? thirdCategorySelect.value : '';
            return thirdId || subId || parentId;
        }

        function refreshSubcategories() {
            if (!categorySelect || !subcategorySelect || !thirdCategorySelect) return;
            const parentId = categorySelect.value;
            const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
            subcategorySelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Secin';
            subcategorySelect.appendChild(placeholder);

            options.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = String(item.name);
                subcategorySelect.appendChild(option);
            });

            const hasChildren = options.length > 0;
            subcategorySelect.disabled = !hasChildren;
            subcategorySelect.required = hasChildren;
            if (subcategoryHelp) {
                subcategoryHelp.textContent = hasChildren
                    ? 'Bu kateqoriya ucun alt kateqoriya secmek mecburidir.'
                    : 'Bu kateqoriya ucun aktiv alt kateqoriya yoxdur.';
            }

            refreshThirdCategories();
        }

        function refreshThirdCategories() {
            if (!subcategorySelect || !thirdCategorySelect) return;
            const subId = subcategorySelect.value;
            const options = subId && Array.isArray(subcategoriesByParent[subId]) ? subcategoriesByParent[subId] : [];
            thirdCategorySelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Secin';
            thirdCategorySelect.appendChild(placeholder);

            options.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = String(item.name);
                thirdCategorySelect.appendChild(option);
            });

            const hasChildren = options.length > 0;
            thirdCategorySelect.disabled = !hasChildren;
            thirdCategorySelect.required = hasChildren;
            if (thirdCategoryHelp) {
                thirdCategoryHelp.textContent = hasChildren
                    ? 'Bu alt kateqoriya ucun alt-alt kateqoriya secmek mecburidir.'
                    : 'Bu alt kateqoriya ucun aktiv alt-alt kateqoriya yoxdur.';
            }
        }

        function buildSpecField(spec) {
            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            label.textContent = spec.label;
            wrapper.appendChild(label);

            const name = `spec[${spec.spec_key}]`;
            let input;

            if (spec.input_type === 'select') {
                input = document.createElement('select');
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Secin';
                input.appendChild(empty);
                (spec.options || []).forEach((opt) => {
                    const option = document.createElement('option');
                    option.value = String(opt);
                    option.textContent = String(opt);
                    input.appendChild(option);
                });
            } else if (spec.input_type === 'boolean') {
                input = document.createElement('select');
                [['0', 'Xeyr'], ['1', 'Beli']].forEach(([value, text]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = text;
                    input.appendChild(option);
                });
            } else {
                input = document.createElement('input');
                input.type = spec.input_type === 'number' ? 'number' : 'text';
                if (spec.input_type === 'number') {
                    input.step = 'any';
                }
            }

            input.name = name;
            wrapper.appendChild(input);

            if (spec.unit) {
                const help = document.createElement('div');
                help.className = 'spec-help';
                help.textContent = `Olcu vahidi: ${spec.unit}`;
                wrapper.appendChild(help);
            }
            return wrapper;
        }

        function addExtraSpecRow(container, label = '', value = '') {
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'extra-spec-row';
            row.innerHTML = `
                <div>
                    <label>Ozellik adi</label>
                    <input name="extra_spec_label[]" placeholder="Mes: Paketde var">
                </div>
                <div>
                    <label>Ozellik deyeri</label>
                    <input name="extra_spec_value[]" class="add-extra-spec-value" placeholder="Mes: Mouse pad">
                </div>
                <button class="btn btn-danger" type="button" data-action="remove-extra-spec">Sil</button>
            `;
            const labelInput = row.querySelector('input[name="extra_spec_label[]"]');
            const valueInput = row.querySelector('input[name="extra_spec_value[]"]');
            if (labelInput instanceof HTMLInputElement && label) {
                labelInput.value = label;
            }
            if (valueInput instanceof HTMLInputElement && value) {
                valueInput.value = value;
            }
            container.appendChild(row);
        }

        function buildExtraTemplateField(label, value = '') {
            const wrapper = document.createElement('div');
            wrapper.dataset.extraTemplate = '1';
            const labelEl = document.createElement('label');
            labelEl.textContent = label;
            wrapper.appendChild(labelEl);

            const hiddenLabel = document.createElement('input');
            hiddenLabel.type = 'hidden';
            hiddenLabel.name = 'extra_spec_label[]';
            hiddenLabel.value = label;
            wrapper.appendChild(hiddenLabel);

            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.name = 'extra_spec_value[]';
            valueInput.value = value;
            valueInput.placeholder = 'Deyer daxil edin';
            wrapper.appendChild(valueInput);

            const help = document.createElement('div');
            help.className = 'spec-help';
            help.textContent = 'Saxlanmis elave ozellik';
            wrapper.appendChild(help);

            return wrapper;
        }

        async function loadSpecs() {
            const categoryId = getEffectiveCategoryId();
            specFields.innerHTML = '';
            if (!categoryId) {
                specLoading.textContent = 'Evvelce kateqoriya secin.';
                return;
            }
            if (subcategorySelect && subcategorySelect.required && !subcategorySelect.value) {
                specLoading.textContent = 'Evvelce alt kateqoriya secin.';
                return;
            }
            if (thirdCategorySelect && thirdCategorySelect.required && !thirdCategorySelect.value) {
                specLoading.textContent = 'Evvelce alt-alt kateqoriya secin.';
                return;
            }
            specLoading.textContent = 'Xususiyyetler yuklenir...';
            try {
                const response = await fetch(`ajax/category_specs.php?category_id=${encodeURIComponent(categoryId)}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Yukleme xetasi');
                }
                const specs = Array.isArray(data.specs) ? data.specs : [];
                const extraTemplates = Array.isArray(data.extra_templates) ? data.extra_templates : [];
                if (specs.length === 0 && extraTemplates.length === 0) {
                    specLoading.textContent = 'Bu kateqoriya ucun xususiyyet yoxdur.';
                    return;
                }
                specLoading.textContent = '';
                specs.forEach((spec) => {
                    specFields.appendChild(buildSpecField(spec));
                });
                extraTemplates.forEach((template) => {
                    const label = String(template.label || '').trim();
                    if (!label) return;
                    specFields.appendChild(buildExtraTemplateField(label));
                });
            } catch (error) {
                specLoading.textContent = 'Xususiyyetler yuklenmedi.';
            }
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', () => {
                refreshSubcategories();
                loadSpecs();
            });
            if (categorySelect.value) {
                refreshSubcategories();
                loadSpecs();
            }
        }
        if (subcategorySelect) {
            subcategorySelect.addEventListener('change', () => {
                refreshThirdCategories();
                loadSpecs();
            });
        }
        if (thirdCategorySelect) {
            thirdCategorySelect.addEventListener('change', loadSpecs);
        }
        if (addExtraSpecBtn && extraSpecList) {
            addExtraSpecBtn.addEventListener('click', () => addExtraSpecRow(extraSpecList));
            extraSpecList.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (target.dataset.action !== 'remove-extra-spec') return;
                const row = target.closest('.extra-spec-row');
                if (row) row.remove();
            });
        }

        // Custom file inputs
        document.querySelectorAll('[data-file-input]').forEach((wrap) => {
            if (!(wrap instanceof HTMLElement)) return;
            const input = wrap.querySelector('input[type="file"]');
            const btn = wrap.querySelector('[data-file-btn]');
            const label = wrap.querySelector('[data-file-label]');
            if (!(input instanceof HTMLInputElement) || !(btn instanceof HTMLElement) || !(label instanceof HTMLElement)) return;

            const sync = () => {
                const files = input.files ? Array.from(input.files) : [];
                if (files.length === 0) {
                    label.textContent = 'Dosya seçilmedi';
                    return;
                }
                label.textContent = files.length === 1 ? files[0].name : `${files.length} dosya seçildi`;
            };

            btn.addEventListener('click', () => input.click());
            input.addEventListener('change', sync);
            sync();
        });

        function renderPickerForInput(inputId) {
            const input = document.getElementById(inputId);
            if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
            const picker = document.querySelector(`[data-image-picker="${CSS.escape(inputId)}"]`);
            if (!(picker instanceof HTMLElement)) return;

            /** @type {string[]} */
            let prevUrls = [];
            const slots = Array.from(picker.querySelectorAll('[data-slot]'));
            let dragFrom = null;

            const setInputFiles = (files) => {
                try {
                    const dt = new DataTransfer();
                    files.forEach((f) => dt.items.add(f));
                    input.files = dt.files;
                } catch (e) {
                    // Eski tarayıcılarda file list set edilemeyebilir; yine de UI güncellenir.
                }
            };

            const swapFiles = (fromIdx, toIdx) => {
                const files = input.files ? Array.from(input.files) : [];
                if (!files.length) return;
                if (fromIdx < 0 || toIdx < 0 || fromIdx >= files.length || toIdx >= files.length) return;
                if (fromIdx === toIdx) return;
                const tmp = files[fromIdx];
                files[fromIdx] = files[toIdx];
                files[toIdx] = tmp;
                setInputFiles(files);
                sync();
            };

            const chooseForSlot = (slotIdx) => {
                const idx = Number(slotIdx);
                if (!Number.isFinite(idx) || idx < 0 || idx > 5) return;
                const temp = document.createElement('input');
                temp.type = 'file';
                temp.accept = input.accept || '.jpg,.jpeg,.png,.webp';
                temp.multiple = false;
                temp.style.position = 'fixed';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);

                temp.addEventListener('change', () => {
                    try {
                        const picked = temp.files && temp.files[0] ? temp.files[0] : null;
                        if (!picked) return;
                        const files = input.files ? Array.from(input.files) : [];
                        const targetIdx = Math.min(Math.max(idx, 0), 5);
                        if (targetIdx < files.length) {
                            files[targetIdx] = picked;
                        } else {
                            files.push(picked);
                            if (targetIdx !== files.length - 1) {
                                const moved = files.pop();
                                files.splice(Math.min(targetIdx, files.length), 0, moved);
                            }
                        }
                        setInputFiles(files);
                        sync();
                    } finally {
                        temp.remove();
                    }
                }, { once: true });

                temp.click();
            };

            const removeFileAt = (slotIdx) => {
                const files = input.files ? Array.from(input.files) : [];
                if (!files.length) return;
                if (slotIdx < 0 || slotIdx >= files.length) return;
                files.splice(slotIdx, 1);
                setInputFiles(files);
                input.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
            };

            const clearSlot = (slotEl) => {
                slotEl.classList.remove('image-slot--filled');
                slotEl.innerHTML = '<div class="image-slot__plus">+</div>';
                slotEl.removeAttribute('draggable');
            };

            const setSlotImage = (slotEl, url) => {
                slotEl.classList.add('image-slot--filled');
                slotEl.innerHTML = '';
                slotEl.setAttribute('draggable', 'true');

                const rawIdx = slotEl.getAttribute('data-slot');
                const slotIndex = rawIdx ? parseInt(rawIdx, 10) : NaN;

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'image-slot__delete';
                delBtn.textContent = 'Sil';
                delBtn.setAttribute('aria-label', 'Sekli sil');
                delBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!Number.isFinite(slotIndex)) return;
                    removeFileAt(slotIndex);
                });
                slotEl.appendChild(delBtn);

                if (Number.isFinite(slotIndex) && slotIndex > 0) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'image-slot__action';
                    btn.textContent = 'Üz qabığı';
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        swapFiles(slotIndex, 0);
                    });
                    slotEl.appendChild(btn);
                }

                const img = document.createElement('img');
                img.src = url;
                img.alt = '';
                slotEl.appendChild(img);
            };

            const sync = () => {
                prevUrls.forEach((u) => URL.revokeObjectURL(u));
                prevUrls = [];

                const files = input.files ? Array.from(input.files) : [];
                slots.forEach(clearSlot);

                files.slice(0, 6).forEach((file, idx) => {
                    const slotEl = slots.find((el) => String(el.getAttribute('data-slot')) === String(idx));
                    if (!(slotEl instanceof HTMLElement)) return;
                    const url = URL.createObjectURL(file);
                    prevUrls.push(url);
                    setSlotImage(slotEl, url);
                });
            };

            // Drag & drop reordering (ilk 6 içinde)
            slots.forEach((slotEl) => {
                if (!(slotEl instanceof HTMLElement)) return;

                slotEl.addEventListener('click', (e) => {
                    const target = e.target;
                    if (target instanceof HTMLElement && (target.closest('.image-slot__action') || target.closest('.image-slot__delete'))) {
                        return;
                    }
                    const rawIdx = slotEl.getAttribute('data-slot');
                    const idx = rawIdx ? parseInt(rawIdx, 10) : NaN;
                    if (!Number.isFinite(idx)) return;
                    chooseForSlot(idx);
                });

                slotEl.addEventListener('dragstart', (e) => {
                    const rawIdx = slotEl.getAttribute('data-slot');
                    const idx = rawIdx ? parseInt(rawIdx, 10) : NaN;
                    const files = input.files ? Array.from(input.files) : [];
                    if (!Number.isFinite(idx) || idx < 0 || idx >= Math.min(files.length, 6)) {
                        e.preventDefault();
                        return;
                    }
                    dragFrom = idx;
                    slotEl.classList.add('is-dragging');
                    try {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', String(idx));
                    } catch (_) {}
                });

                slotEl.addEventListener('dragend', () => {
                    dragFrom = null;
                    slots.forEach((el) => el instanceof HTMLElement && el.classList.remove('is-drag-over', 'is-dragging'));
                });

                slotEl.addEventListener('dragover', (e) => {
                    if (dragFrom === null) return;
                    e.preventDefault();
                    slotEl.classList.add('is-drag-over');
                    try {
                        e.dataTransfer.dropEffect = 'move';
                    } catch (_) {}
                });

                slotEl.addEventListener('dragleave', () => {
                    slotEl.classList.remove('is-drag-over');
                });

                slotEl.addEventListener('drop', (e) => {
                    if (dragFrom === null) return;
                    e.preventDefault();
                    slotEl.classList.remove('is-drag-over');
                    const rawTo = slotEl.getAttribute('data-slot');
                    const toIdx = rawTo ? parseInt(rawTo, 10) : NaN;
                    if (!Number.isFinite(toIdx)) return;
                    const files = input.files ? Array.from(input.files) : [];
                    const max = Math.min(files.length, 6);
                    if (toIdx < 0 || toIdx >= max) return;
                    if (dragFrom === toIdx) return;
                    swapFiles(dragFrom, toIdx);
                });
            });

            input.addEventListener('change', sync);
            sync();
        }

        renderPickerForInput('create-product-image');
        renderPickerForInput('edit-product-image');
    })();
    </script>
    <?php if ($editingProduct && productBundleItemsTableExists($pdo)): ?>
    <script>
    (() => {
        const root = document.getElementById('bundle-picker');
        if (!root) return;

        const excludeId = parseInt(root.dataset.excludeId || '0', 10) || 0;
        const initialEl = document.getElementById('bundle-picker-initial');
        let selected = [];
        if (initialEl && initialEl.textContent.trim()) {
            try {
                selected = JSON.parse(initialEl.textContent);
            } catch (e) {
                selected = [];
            }
        }
        if (!Array.isArray(selected)) {
            selected = [];
        }

        const searchInput = document.getElementById('bundle-search-q');
        const resultsEl = document.getElementById('bundle-search-results');
        const listEl = document.getElementById('bundle-selected-list');
        const hiddenWrap = document.getElementById('bundle-hidden-inputs');
        const bulkTa = document.getElementById('bundle-bulk-ids');
        const bulkBtn = document.getElementById('bundle-bulk-apply');
        const bulkMsg = document.getElementById('bundle-bulk-msg');
        let searchTimer = null;
        const MAX = 20;

        function syncHidden() {
            hiddenWrap.innerHTML = '';
            selected.forEach((p) => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'bundle_product_ids[]';
                inp.value = String(p.id);
                hiddenWrap.appendChild(inp);
            });
        }

        function fmtMoney(n) {
            return new Intl.NumberFormat('az-AZ', { style: 'currency', currency: 'AZN', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
        }

        function renderList() {
            listEl.innerHTML = '';
            if (selected.length === 0) {
                const li = document.createElement('li');
                li.className = 'bundle-picker-empty';
                li.textContent = 'Hələ əlavə məhsul seçilməyib. Yuxarıdan ad və ya SKU ilə axtarın.';
                listEl.appendChild(li);
                syncHidden();
                return;
            }

            selected.forEach((p, idx) => {
                const li = document.createElement('li');
                li.className = 'bundle-selected-row';

                const info = document.createElement('div');
                info.className = 'bundle-selected-row__info';
                const title = document.createElement('strong');
                title.textContent = p.name;
                const meta = document.createElement('div');
                meta.className = 'bundle-selected-row__meta';
                meta.textContent = 'ID ' + p.id + ' · ' + p.sku + ' · ' + fmtMoney(p.base_price);
                info.appendChild(title);
                info.appendChild(meta);
                li.appendChild(info);

                const actions = document.createElement('div');
                actions.className = 'bundle-selected-row__actions';

                const up = document.createElement('button');
                up.type = 'button';
                up.className = 'btn btn-muted bundle-selected-row__btn';
                up.textContent = 'Yuxarı';
                up.disabled = idx === 0;
                up.addEventListener('click', () => {
                    if (idx <= 0) return;
                    const t = selected[idx - 1];
                    selected[idx - 1] = selected[idx];
                    selected[idx] = t;
                    renderList();
                });

                const down = document.createElement('button');
                down.type = 'button';
                down.className = 'btn btn-muted bundle-selected-row__btn';
                down.textContent = 'Aşağı';
                down.disabled = idx === selected.length - 1;
                down.addEventListener('click', () => {
                    if (idx >= selected.length - 1) return;
                    const t = selected[idx + 1];
                    selected[idx + 1] = selected[idx];
                    selected[idx] = t;
                    renderList();
                });

                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'btn btn-danger bundle-selected-row__btn';
                rm.textContent = 'Çıxar';
                rm.addEventListener('click', () => {
                    selected.splice(idx, 1);
                    renderList();
                });

                actions.appendChild(up);
                actions.appendChild(down);
                actions.appendChild(rm);
                li.appendChild(actions);
                listEl.appendChild(li);
            });
            syncHidden();
        }

        function addProduct(p) {
            if (!p || p.id === excludeId) return false;
            if (selected.some((x) => x.id === p.id)) return false;
            if (selected.length >= MAX) {
                window.alert('Ekosistem siyahısında ən çox ' + MAX + ' məhsul ola bilər.');
                return false;
            }
            selected.push({
                id: p.id,
                name: p.name,
                sku: p.sku,
                base_price: p.base_price,
                status: p.status,
            });
            renderList();
            return true;
        }

        async function runSearch(q) {
            const url = 'ajax/product_search.php?q=' + encodeURIComponent(q) + '&exclude_id=' + excludeId;
            const r = await fetch(url, { credentials: 'same-origin' });
            const data = await r.json().catch(() => null);
            if (!r.ok) {
                throw new Error((data && data.message) ? data.message : ('HTTP ' + r.status));
            }
            if (!data || typeof data !== 'object') {
                throw new Error('Server cavabi JSON deyil');
            }
            if (!data.ok) throw new Error(data.message || 'Xəta');
            return data.products || [];
        }

        async function runIds(idsStr) {
            const r = await fetch('ajax/product_search.php?ids=' + encodeURIComponent(idsStr) + '&exclude_id=' + excludeId, { credentials: 'same-origin' });
            const data = await r.json().catch(() => null);
            if (!r.ok) {
                throw new Error((data && data.message) ? data.message : ('HTTP ' + r.status));
            }
            if (!data || typeof data !== 'object') {
                throw new Error('Server cavabi JSON deyil');
            }
            if (!data.ok) throw new Error(data.message || 'Xəta');
            return data;
        }

        function showResults(products) {
            resultsEl.innerHTML = '';
            if (!products.length) {
                const empty = document.createElement('div');
                empty.className = 'bundle-picker__result-empty';
                empty.textContent = 'Nəticə tapılmadı';
                resultsEl.appendChild(empty);
                resultsEl.hidden = false;
                return;
            }
            products.forEach((p) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'bundle-picker__result-row';
                const already = selected.some((x) => x.id === p.id);
                if (already) {
                    row.classList.add('is-disabled');
                }
                const nameSpan = document.createElement('span');
                nameSpan.className = 'bundle-picker__result-name';
                nameSpan.textContent = p.name;
                const metaSpan = document.createElement('span');
                metaSpan.className = 'bundle-picker__result-meta';
                metaSpan.textContent = p.sku + ' · ' + fmtMoney(p.base_price);
                row.appendChild(nameSpan);
                row.appendChild(metaSpan);
                row.addEventListener('click', () => {
                    if (already) return;
                    if (addProduct(p)) {
                        resultsEl.hidden = true;
                        searchInput.value = '';
                    }
                });
                resultsEl.appendChild(row);
            });
            resultsEl.hidden = false;
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                const q = searchInput.value.trim();
                if (q.length < 2) {
                    resultsEl.hidden = true;
                    resultsEl.innerHTML = '';
                    return;
                }
                searchTimer = setTimeout(async () => {
                    try {
                        const products = await runSearch(q);
                        showResults(products);
                    } catch (err) {
                        resultsEl.innerHTML = '';
                        const errDiv = document.createElement('div');
                        errDiv.className = 'bundle-picker__result-empty';
                        errDiv.textContent = 'Axtarış yüklənmədi: ' + ((err && err.message) ? err.message : 'bilinmeyen xeta');
                        resultsEl.appendChild(errDiv);
                        resultsEl.hidden = false;
                    }
                }, 300);
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    resultsEl.hidden = true;
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (!root.contains(e.target)) {
                resultsEl.hidden = true;
            }
        });

        if (bulkBtn && bulkTa && bulkMsg) {
            bulkBtn.addEventListener('click', async () => {
                bulkMsg.textContent = '';
                const raw = bulkTa.value.trim();
                if (!raw) return;
                try {
                    const data = await runIds(raw);
                    let added = 0;
                    for (const p of data.products || []) {
                        if (p.id === excludeId) continue;
                        if (selected.some((x) => x.id === p.id)) continue;
                        if (selected.length >= MAX) {
                            bulkMsg.textContent = 'Limit: ən çox ' + MAX + ' məhsul. ';
                            break;
                        }
                        selected.push({
                            id: p.id,
                            name: p.name,
                            sku: p.sku,
                            base_price: p.base_price,
                            status: p.status,
                        });
                        added += 1;
                    }
                    renderList();
                    const parts = [];
                    if (data.missing && data.missing.length) {
                        parts.push('Tapılmadı və ya aktiv deyil: ' + data.missing.join(', '));
                    }
                    if (added) {
                        parts.push(added + ' məhsul əlavə edildi');
                    }
                    if (!added && !(data.missing && data.missing.length)) {
                        parts.push('Yeni əlavə ediləcək məhsul yoxdur (təkrar və ya boş).');
                    }
                    bulkMsg.textContent = parts.join(' · ');
                    bulkTa.value = '';
                } catch (err) {
                    bulkMsg.textContent = 'Yükləmə xətası';
                }
            });
        }

        renderList();
    })();
    </script>
    <?php endif; ?>
    <?php if ($editingProduct): ?>
    <script>
    function submitEditImageDelete(imageId) {
        const form = document.querySelector('form input[name="id"][value="<?php echo (int) $editingProduct['id']; ?>"]')?.form;
        const actionInput = form ? form.querySelector('input[name="action"]') : null;
        const imageIdInput = document.getElementById('edit-delete-image-id');
        if (!form || !actionInput || !imageIdInput) {
            return;
        }
        if (!window.confirm('Bu sekil silinsin?')) {
            return;
        }
        actionInput.value = 'delete_product_image';
        imageIdInput.value = String(imageId);
        form.submit();
    }

    (() => {
        const categorySelect = document.getElementById('edit_category_id');
        const subcategorySelect = document.getElementById('edit_subcategory_id');
        const thirdCategorySelect = document.getElementById('edit_third_category_id');
        const subcategoryHelp = document.getElementById('edit-subcategory-help');
        const thirdCategoryHelp = document.getElementById('edit-third-category-help');
        const editSpecFields = document.getElementById('edit-spec-fields');
        const editSpecLoading = document.getElementById('edit-spec-loading');
        const editExtraSpecList = document.getElementById('edit-extra-spec-list');
        const editAddExtraSpecBtn = document.getElementById('edit-add-extra-spec-btn');
        if (!categorySelect || !subcategorySelect || !thirdCategorySelect) return;

        const subcategoriesByParent = <?php
            echo json_encode(
                $subcategoriesByParent,
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
        ?> || {};
        const specValueEl = document.getElementById('edit-spec-values');
        const initialSpecValues = (() => {
            if (!specValueEl || !specValueEl.textContent.trim()) return {};
            try {
                const parsed = JSON.parse(specValueEl.textContent);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        })();
        const initialEffectiveCategoryId = '<?php echo (string) ((int) ($editingProduct['category_id'] ?? 0)); ?>';
        const extraSpecValueEl = document.getElementById('edit-extra-spec-values');
        const initialExtraSpecValues = (() => {
            if (!extraSpecValueEl || !extraSpecValueEl.textContent.trim()) return {};
            try {
                const parsed = JSON.parse(extraSpecValueEl.textContent);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        })();

        function refreshSubcategories() {
            const parentId = categorySelect.value;
            const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
            const currentValue = subcategorySelect.value;
            subcategorySelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Secin';
            subcategorySelect.appendChild(placeholder);

            options.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = String(item.name);
                if (String(item.id) === String(currentValue)) {
                    option.selected = true;
                }
                subcategorySelect.appendChild(option);
            });

            const hasChildren = options.length > 0;
            subcategorySelect.disabled = !hasChildren;
            subcategorySelect.required = hasChildren;
            if (hasChildren && !subcategorySelect.value) {
                subcategorySelect.value = '';
            }
            if (subcategoryHelp) {
                subcategoryHelp.textContent = hasChildren
                    ? 'Bu kateqoriya ucun alt kateqoriya secmek mecburidir.'
                    : 'Bu kateqoriya ucun aktiv alt kateqoriya yoxdur.';
            }
            refreshThirdCategories();
        }

        function refreshThirdCategories() {
            const subId = subcategorySelect.value;
            const options = subId && Array.isArray(subcategoriesByParent[subId]) ? subcategoriesByParent[subId] : [];
            const currentValue = thirdCategorySelect.value;
            thirdCategorySelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Secin';
            thirdCategorySelect.appendChild(placeholder);

            options.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = String(item.name);
                if (String(item.id) === String(currentValue)) {
                    option.selected = true;
                }
                thirdCategorySelect.appendChild(option);
            });

            const hasChildren = options.length > 0;
            thirdCategorySelect.disabled = !hasChildren;
            thirdCategorySelect.required = hasChildren;
            if (hasChildren && !thirdCategorySelect.value) {
                thirdCategorySelect.value = '';
            }
            if (thirdCategoryHelp) {
                thirdCategoryHelp.textContent = hasChildren
                    ? 'Bu alt kateqoriya ucun alt-alt kateqoriya secmek mecburidir.'
                    : 'Bu alt kateqoriya ucun aktiv alt-alt kateqoriya yoxdur.';
            }
        }

        function getEffectiveCategoryId() {
            const thirdId = thirdCategorySelect.value;
            const subId = subcategorySelect.value;
            return thirdId || subId || categorySelect.value || '';
        }

        function buildSpecField(spec, currentValue = '') {
            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            label.textContent = spec.label;
            wrapper.appendChild(label);

            const name = `spec[${spec.spec_key}]`;
            let input;

            if (spec.input_type === 'select') {
                input = document.createElement('select');
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Secin';
                input.appendChild(empty);
                (spec.options || []).forEach((opt) => {
                    const option = document.createElement('option');
                    option.value = String(opt);
                    option.textContent = String(opt);
                    if (String(currentValue) === String(opt)) {
                        option.selected = true;
                    }
                    input.appendChild(option);
                });
            } else if (spec.input_type === 'boolean') {
                input = document.createElement('select');
                const normalized = String(currentValue) === '1' ? '1' : '0';
                [['0', 'Xeyr'], ['1', 'Beli']].forEach(([value, text]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = text;
                    if (normalized === value) {
                        option.selected = true;
                    }
                    input.appendChild(option);
                });
            } else {
                input = document.createElement('input');
                input.type = spec.input_type === 'number' ? 'number' : 'text';
                if (spec.input_type === 'number') {
                    input.step = 'any';
                }
                input.value = currentValue !== null && currentValue !== undefined ? String(currentValue) : '';
            }

            input.name = name;
            wrapper.appendChild(input);

            if (spec.unit) {
                const help = document.createElement('div');
                help.className = 'spec-help';
                help.textContent = `Olcu vahidi: ${spec.unit}`;
                wrapper.appendChild(help);
            }
            return wrapper;
        }

        function addExtraSpecRow(container, label = '', value = '') {
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'extra-spec-row';
            row.innerHTML = `
                <div>
                    <label>Ozellik adi</label>
                    <input name="extra_spec_label[]" placeholder="Mes: Paketde var">
                </div>
                <div>
                    <label>Ozellik deyeri</label>
                    <input name="extra_spec_value[]" placeholder="Mes: Mouse pad">
                </div>
                <button class="btn btn-danger" type="button" data-action="remove-extra-spec">Sil</button>
            `;
            const labelInput = row.querySelector('input[name="extra_spec_label[]"]');
            const valueInput = row.querySelector('input[name="extra_spec_value[]"]');
            if (labelInput instanceof HTMLInputElement && label) {
                labelInput.value = label;
            }
            if (valueInput instanceof HTMLInputElement && value) {
                valueInput.value = value;
            }
            container.appendChild(row);
        }

        function buildExtraTemplateField(label, value = '') {
            const wrapper = document.createElement('div');
            wrapper.dataset.extraTemplate = '1';
            const labelEl = document.createElement('label');
            labelEl.textContent = label;
            wrapper.appendChild(labelEl);

            const hiddenLabel = document.createElement('input');
            hiddenLabel.type = 'hidden';
            hiddenLabel.name = 'extra_spec_label[]';
            hiddenLabel.value = label;
            wrapper.appendChild(hiddenLabel);

            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.name = 'extra_spec_value[]';
            valueInput.value = value;
            valueInput.placeholder = 'Deyer daxil edin';
            wrapper.appendChild(valueInput);

            const help = document.createElement('div');
            help.className = 'spec-help';
            help.textContent = 'Saxlanmis elave ozellik';
            wrapper.appendChild(help);

            return wrapper;
        }

        function renderManualExtraSpecs(usedLabels, isInitialCategory) {
            if (!editExtraSpecList) return;
            editExtraSpecList.innerHTML = '';
            if (!isInitialCategory) return;

            Object.entries(initialExtraSpecValues).forEach(([label, value]) => {
                const normalized = String(label).trim().toLowerCase();
                if (!normalized || usedLabels.has(normalized)) return;
                addExtraSpecRow(editExtraSpecList, String(label), String(value ?? ''));
            });
        }

        async function loadEditSpecs() {
            if (!editSpecFields || !editSpecLoading) return;
            const categoryId = getEffectiveCategoryId();
            editSpecFields.innerHTML = '';
            if (editExtraSpecList) {
                editExtraSpecList.innerHTML = '';
            }
            if (!categoryId) {
                editSpecLoading.textContent = 'Evvelce kateqoriya secin.';
                return;
            }
            if (subcategorySelect.required && !subcategorySelect.value) {
                editSpecLoading.textContent = 'Evvelce alt kateqoriya secin.';
                return;
            }
            if (thirdCategorySelect.required && !thirdCategorySelect.value) {
                editSpecLoading.textContent = 'Evvelce alt-alt kateqoriya secin.';
                return;
            }

            editSpecLoading.textContent = 'Xususiyyetler yuklenir...';
            try {
                const response = await fetch(`ajax/category_specs.php?category_id=${encodeURIComponent(categoryId)}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Yukleme xetasi');
                }
                const specs = Array.isArray(data.specs) ? data.specs : [];
                const extraTemplates = Array.isArray(data.extra_templates) ? data.extra_templates : [];
                if (specs.length === 0 && extraTemplates.length === 0) {
                    editSpecLoading.textContent = 'Bu kateqoriya ucun xususiyyet yoxdur.';
                    renderManualExtraSpecs(new Set(), String(categoryId) === String(initialEffectiveCategoryId));
                    return;
                }
                editSpecLoading.textContent = '';
                const isInitialCategory = String(categoryId) === String(initialEffectiveCategoryId);
                const usedTemplateLabels = new Set();

                specs.forEach((spec) => {
                    const current = isInitialCategory ? (initialSpecValues[spec.spec_key] ?? '') : '';
                    editSpecFields.appendChild(buildSpecField(spec, String(current)));
                });
                extraTemplates.forEach((template) => {
                    const label = String(template.label || '').trim();
                    if (!label) return;
                    usedTemplateLabels.add(label.toLowerCase());
                    const current = isInitialCategory ? String(initialExtraSpecValues[label] ?? '') : '';
                    editSpecFields.appendChild(buildExtraTemplateField(label, current));
                });
                renderManualExtraSpecs(usedTemplateLabels, isInitialCategory);
            } catch (e) {
                editSpecLoading.textContent = 'Xususiyyetler yuklenmedi.';
            }
        }

        categorySelect.addEventListener('change', () => {
            refreshSubcategories();
            loadEditSpecs();
        });
        subcategorySelect.addEventListener('change', () => {
            refreshThirdCategories();
            loadEditSpecs();
        });
        thirdCategorySelect.addEventListener('change', loadEditSpecs);
        if (editAddExtraSpecBtn && editExtraSpecList) {
            editAddExtraSpecBtn.addEventListener('click', () => addExtraSpecRow(editExtraSpecList));
            editExtraSpecList.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (target.dataset.action !== 'remove-extra-spec') return;
                const row = target.closest('.extra-spec-row');
                if (row) row.remove();
            });
        }

        refreshSubcategories();
        loadEditSpecs();
    })();
    </script>
    <?php endif; ?>
    <script>
    (function () {
        const form = document.getElementById('json-import-form');
        if (!form) return;

        const submitBtn = document.getElementById('json-import-submit');
        const apiSubmitBtn = document.getElementById('api-import-submit');
        const cancelBtn = document.getElementById('json-import-cancel');
        const progressWrap = document.getElementById('json-import-progress');
        const progressFill = document.getElementById('json-import-progress-fill');
        const progressText = document.getElementById('json-import-progress-text');
        const progressPercent = document.getElementById('json-import-progress-percent');
        const flashBox = document.getElementById('json-import-flash');
        const reportBox = document.getElementById('json-import-report');
        const reportTitle = document.getElementById('json-import-report-title');
        const failedList = document.getElementById('json-import-failed-list');
        const importUrl = 'ajax/import_products_json.php';

        let activeJobId = '';
        let cancelled = false;

        function showFlash(message, type) {
            if (!flashBox) return;
            flashBox.textContent = message;
            flashBox.className = 'flash ' + (type === 'error' ? 'flash-error' : 'flash-success');
            flashBox.style.display = 'block';
        }

        function hideFailedReport() {
            if (reportBox) reportBox.classList.remove('is-visible');
            if (failedList) failedList.innerHTML = '';
        }

        function showFailedReport(data) {
            if (!reportBox || !failedList) return;
            const failed = (Array.isArray(data.failed_products) ? data.failed_products : [])
                .filter(function (item) {
                    return item && item.type !== 'skipped';
                });
            if (!failed.length) {
                hideFailedReport();
                return;
            }

            failedList.innerHTML = '';
            failed.forEach(function (item) {
                const row = document.createElement('div');
                row.className = 'import-failed-item';
                const name = (item && item.name) ? String(item.name) : 'Mehsul';
                const sku = (item && item.sku) ? String(item.sku) : '';
                const reason = (item && item.reason) ? String(item.reason) : 'Yuklenmedi';
                const typeLabel = (item && item.type === 'skipped') ? 'Kechildi' : 'Xeta';
                row.innerHTML =
                    '<strong>' + name + (sku ? ' <span>[' + sku + ']</span>' : '') + '</strong>' +
                    '<div><span>' + typeLabel + ':</span> ' + reason + '</div>';
                failedList.appendChild(row);
            });

            if (reportTitle) {
                reportTitle.textContent = 'Yuklenmeyen mehsullar (' + failed.length + ')';
            }
            reportBox.classList.add('is-visible');
        }

        function buildFinalMessage(data) {
            const imported = Number(data.imported || 0);
            const updated = Number(data.updated || 0);
            const errorCount = Number(data.error_count || 0);
            const parts = [];
            if (imported > 0) {
                parts.push(imported + ' yeni mehsul elave edildi');
            }
            if (updated > 0) {
                parts.push(updated + ' movcud mehsul yenilendi (qiymet/stok)');
            }
            let message = parts.length
                ? parts.join(', ') + '. Movcud mehsullar silinmedi.'
                : 'Emal tamamlandi.';
            if (errorCount > 0) {
                message += ' ' + errorCount + ' mehsul xeta ile yuklenmedi (asagidaki siyahiya baxin).';
            }
            if (imported === 0 && updated === 0 && errorCount > 0) {
                return { message: message, type: 'error' };
            }
            if (errorCount > 0) {
                return { message: message, type: 'success' };
            }
            return { message: message, type: 'success' };
        }

        function setUiRunning(running) {
            if (submitBtn) submitBtn.disabled = running;
            if (apiSubmitBtn) apiSubmitBtn.disabled = running;
            if (cancelBtn) cancelBtn.style.display = running ? 'inline-flex' : 'none';
            if (progressWrap) progressWrap.classList.toggle('is-active', running);
            form.querySelectorAll('input, select, button').forEach(function (el) {
                if (el === cancelBtn) return;
                if (el === submitBtn) return;
                if (el === apiSubmitBtn) return;
                if (el.type === 'file') return;
                el.disabled = running;
            });
        }

        function buildImportFormData(step, extra) {
            const body = new FormData(form);
            const fileInput = document.getElementById('products_json');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                body.set('products_json', fileInput.files[0]);
            }
            body.set('step', step);
            if (extra) {
                Object.keys(extra).forEach(function (key) {
                    body.set(key, extra[key]);
                });
            }
            return body;
        }

        function updateProgress(data) {
            const percent = Math.max(0, Math.min(100, Number(data.percent || 0)));
            if (progressFill) progressFill.style.width = percent + '%';
            if (progressPercent) progressPercent.textContent = percent + '%';
            if (progressText) {
                progressText.textContent = data.message || (data.processed + ' / ' + data.total);
            }
        }

        async function postForm(step, extra) {
            const body = buildImportFormData(step, extra);
            const response = await fetch(importUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            });
            const data = await response.json().catch(function () { return null; });
            if (!response.ok || !data || !data.ok) {
                throw new Error((data && data.message) ? data.message : 'Server xetasi');
            }
            return data;
        }

        async function postJob(step, jobId, retries) {
            const maxRetries = typeof retries === 'number' ? retries : 2;
            let lastError = null;
            for (let attempt = 0; attempt <= maxRetries; attempt++) {
                try {
                    const body = new FormData();
                    body.append('step', step);
                    body.append('job_id', jobId);
                    const response = await fetch(importUrl, {
                        method: 'POST',
                        body: body,
                        credentials: 'same-origin'
                    });
                    const data = await response.json().catch(function () { return null; });
                    if (!response.ok || !data || !data.ok) {
                        throw new Error((data && data.message) ? data.message : 'Server xetasi');
                    }
                    return data;
                } catch (error) {
                    lastError = error;
                    if (attempt < maxRetries) {
                        await new Promise(function (resolve) { setTimeout(resolve, 800); });
                    }
                }
            }
            throw lastError || new Error('Server xetasi');
        }

        async function runBatches(jobId) {
            activeJobId = jobId;
            cancelled = false;
            let lastResult = null;

            while (!cancelled) {
                try {
                    lastResult = await postJob('batch', jobId);
                } catch (error) {
                    if (!lastResult) {
                        throw error;
                    }
                    lastResult = Object.assign({}, lastResult, {
                        errors: (lastResult.errors || []).concat(['Batch xetasi: ' + (error.message || 'Server xetasi')]),
                        failed_count: Number(lastResult.failed_count || 0) + 1,
                        failed_products: (lastResult.failed_products || []).concat([{
                            name: 'Batch emali',
                            sku: '',
                            reason: error.message || 'Server xetasi',
                            type: 'error'
                        }])
                    });
                    break;
                }
                updateProgress(lastResult);
                if (lastResult.done) {
                    break;
                }
            }

            if (cancelled && activeJobId) {
                await postJob('cancel', activeJobId).catch(function () {});
                throw new Error('Import dayandirildi.');
            }

            return lastResult;
        }

        async function runImport(startStep) {
            setUiRunning(true);
            if (flashBox) flashBox.style.display = 'none';
            hideFailedReport();
            updateProgress({ percent: 0, message: startStep === 'start_api' ? 'API-dan mehsullar cekilir...' : 'Fayl parse edilir...' });

            try {
                const startData = await postForm(startStep);
                updateProgress({
                    percent: 0,
                    message: startData.message || 'Idxal baslayir...',
                    processed: 0,
                    total: startData.total || 0
                });

                const finalData = await runBatches(startData.job_id);
                const summary = buildFinalMessage(finalData);
                showFlash(summary.message, summary.type);
                showFailedReport(finalData);
            } catch (error) {
                showFlash(error.message || 'Import ugursuz oldu.', 'error');
            } finally {
                activeJobId = '';
                setUiRunning(false);
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const fileInput = document.getElementById('products_json');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                showFlash('JSON fayli secin ve ya API-dan idxal edin.', 'error');
                return;
            }
            await runImport('start');
        });

        if (apiSubmitBtn) {
            apiSubmitBtn.addEventListener('click', async function () {
                const apiUrl = form.querySelector('[name="api_url"]');
                const apiToken = form.querySelector('[name="api_token"]');
                if (!apiUrl || !String(apiUrl.value || '').trim()) {
                    showFlash('API URL daxil edin.', 'error');
                    return;
                }
                if (!apiToken || !String(apiToken.value || '').trim()) {
                    showFlash('API token daxil edin.', 'error');
                    return;
                }
                await runImport('start_api');
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                cancelled = true;
            });
        }
    })();
    </script>
    </body>
    </html>
