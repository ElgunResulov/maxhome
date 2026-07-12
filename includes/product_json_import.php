<?php
declare(strict_types=1);

const PRODUCT_JSON_IMPORT_BATCH_SIZE = 20;
const PRODUCT_JSON_IMPORT_MAX_BYTES = 20 * 1024 * 1024;
const PRODUCT_API_DEFAULT_URL = 'https://maxhome-api.vercel.app/api/products';

function productJsonImportJobsRoot(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'jobs';
}

function productJsonImportEnsureJobsRoot(): void
{
    $root = productJsonImportJobsRoot();
    if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
        throw new RuntimeException('Import qovlugu yaradila bilmedi.');
    }
}

function productJsonImportCleanupOldJobs(int $maxAgeSeconds = 86400): void
{
    productJsonImportEnsureJobsRoot();
    $root = productJsonImportJobsRoot();
    $entries = scandir($root);
    if ($entries === false) {
        return;
    }
    $cutoff = time() - $maxAgeSeconds;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) {
            continue;
        }
        $mtime = filemtime($path);
        if ($mtime !== false && $mtime < $cutoff) {
            productJsonImportDeleteJobDir($path);
        }
    }
}

function productJsonImportDeleteJobDir(string $jobDir): void
{
    if (!is_dir($jobDir)) {
        return;
    }
    $items = scandir($jobDir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $jobDir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($jobDir);
}

function productJsonImportValidateJobId(string $jobId): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', $jobId);
}

function productJsonImportJobDir(string $jobId): string
{
    if (!productJsonImportValidateJobId($jobId)) {
        throw new RuntimeException('Import job ID yanlisdir.');
    }

    return productJsonImportJobsRoot() . DIRECTORY_SEPARATOR . $jobId;
}

/**
 * @return array<string, mixed>
 */
function productJsonImportReadMeta(string $jobId): array
{
    $metaPath = productJsonImportJobDir($jobId) . DIRECTORY_SEPARATOR . 'meta.json';
    if (!is_file($metaPath)) {
        throw new RuntimeException('Import job tapilmadi.');
    }
    $raw = file_get_contents($metaPath);
    $meta = json_decode($raw !== false ? $raw : '', true);
    if (!is_array($meta)) {
        throw new RuntimeException('Import job meta oxunmadi.');
    }

    return $meta;
}

/**
 * @param array<string, mixed> $meta
 */
function productJsonImportWriteMeta(string $jobId, array $meta): void
{
    $metaPath = productJsonImportJobDir($jobId) . DIRECTORY_SEPARATOR . 'meta.json';
    $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Import job meta yazila bilmedi.');
    }
    if (file_put_contents($metaPath, $encoded) === false) {
        throw new RuntimeException('Import job meta saxlanila bilmedi.');
    }
}

/**
 * @return list<array<string, mixed>>
 */
function productJsonImportNormalizeProductRows(array $decoded): array
{
    if (isset($decoded['products']) && is_array($decoded['products'])) {
        $decoded = $decoded['products'];
    }

    if (!array_is_list($decoded)) {
        throw new RuntimeException('Mehsul siyahisi tapilmadi.');
    }

    $items = [];
    foreach ($decoded as $index => $row) {
        if (!is_array($row)) {
            throw new RuntimeException('Setir #' . ((int) $index + 1) . ' mehsul obyekti deyil.');
        }
        $items[] = $row;
    }

    if ($items === []) {
        throw new RuntimeException('Heç bir mehsul tapilmadi.');
    }

    return $items;
}

/**
 * @return array{url:string,token:string}
 */
function productJsonImportGetApiSettings(PDO $pdo): array
{
    $out = [
        'url' => PRODUCT_API_DEFAULT_URL,
        'token' => '',
    ];

    try {
        $stmt = $pdo->query(
            "SELECT setting_key, setting_value
             FROM site_settings
             WHERE setting_key IN ('products_api_url', 'products_api_token')"
        );
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            $value = trim((string) ($row['setting_value'] ?? ''));
            if ($key === 'products_api_url' && $value !== '') {
                $out['url'] = $value;
            }
            if ($key === 'products_api_token' && $value !== '') {
                $out['token'] = $value;
            }
        }
    } catch (Throwable $e) {
        // site_settings yoxdursa default URL ile davam et.
    }

    return $out;
}

function productJsonImportSaveApiSettings(PDO $pdo, string $url, string $token): void
{
    $url = trim($url);
    $token = trim($token);
    if ($url === '' || $token === '') {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute(['k' => 'products_api_url', 'v' => $url]);
    $stmt->execute(['k' => 'products_api_token', 'v' => $token]);
}

/**
 * @return list<array<string, mixed>>
 */
function productJsonImportFetchProductsFromApi(string $url, string $token): array
{
    $url = trim($url);
    $token = trim($token);
    if ($url === '') {
        throw new RuntimeException('API URL bosdur.');
    }
    if ($token === '') {
        throw new RuntimeException('API token bosdur.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL aktiv deyil.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('API sorğusu ugursuz oldu: ' . $curlError);
    }
    if ($httpCode === 401 || $httpCode === 403) {
        throw new RuntimeException('API token yanlisdir ve ya icaze yoxdur (HTTP ' . $httpCode . ').');
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('API xetasi (HTTP ' . $httpCode . ').');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('API cavabi duzgun JSON deyil.');
    }

    return productJsonImportNormalizeProductRows($decoded);
}

/**
 * @return list<array<string, mixed>>
 */
function productJsonImportParseUpload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('JSON yukleme verisi yanlisdir.');
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('JSON fayli secin.');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('JSON fayli yuklenmedi.');
    }
    if ((int) $file['size'] > PRODUCT_JSON_IMPORT_MAX_BYTES) {
        throw new RuntimeException('JSON fayli 20MB-den boyuk ola bilmez.');
    }

    $originalName = strtolower((string) ($file['name'] ?? ''));
    if (pathinfo($originalName, PATHINFO_EXTENSION) !== 'json') {
        throw new RuntimeException('Yalniz .json fayli qebul edilir.');
    }

    $raw = file_get_contents((string) $file['tmp_name']);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException('JSON fayli bosdur.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON formati sehvdir.');
    }

    return productJsonImportNormalizeProductRows($decoded);
}

function productJsonImportNormalizeText(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = [
        'ə' => 'e', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c', 'ı' => 'i',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    return $text;
}

/**
 * @return list<string>
 */
function productJsonImportTextTokens(string $text): array
{
    $normalized = productJsonImportNormalizeText($text);
    if ($normalized === '') {
        return [];
    }

    $tokens = [];
    foreach (explode(' ', $normalized) as $token) {
        if (mb_strlen($token) >= 3) {
            $tokens[] = $token;
        }
    }

    return array_values(array_unique($tokens));
}

function productJsonImportUniqueCategorySlug(PDO $pdo, string $name): string
{
    $base = productJsonImportNormalizeText($name);
    $base = str_replace(' ', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'kateqoriya';
    }
    if (mb_strlen($base) > 180) {
        $base = mb_substr($base, 0, 180);
    }

    $slug = $base;
    $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE slug = :slug LIMIT 1');
    for ($attempt = 1; $attempt <= 50; $attempt++) {
        $stmt->execute(['slug' => $slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $attempt;
    }

    return $base . '-' . bin2hex(random_bytes(3));
}

function productJsonImportUniqueProductSlug(PDO $pdo): string
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

function productJsonImportUniqueBrandSlug(PDO $pdo, string $brandName): string
{
    $base = productJsonImportSlugify($brandName);
    if ($base === '') {
        $base = 'brend';
    }
    if (mb_strlen($base) > 150) {
        $base = mb_substr($base, 0, 150);
        $base = rtrim($base, '-');
    }
    if ($base === '') {
        return 'b-' . bin2hex(random_bytes(6));
    }

    $stmt = $pdo->prepare('SELECT 1 FROM brands WHERE slug = :slug LIMIT 1');
    $slug = $base;
    for ($attempt = 1; $attempt <= 50; $attempt++) {
        $stmt->execute(['slug' => $slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $attempt;
    }

    return 'b-' . bin2hex(random_bytes(6));
}

function productJsonImportSlugify(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $map = [
        'ə' => 'e', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c', 'ı' => 'i',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text;
}

function productJsonImportEnsureUniqueProductSlug(PDO $pdo, string $baseSlug): string
{
    $baseSlug = productJsonImportSlugify($baseSlug);
    if ($baseSlug === '') {
        return productJsonImportUniqueProductSlug($pdo);
    }
    if (mb_strlen($baseSlug) > 200) {
        $baseSlug = mb_substr($baseSlug, 0, 200);
        $baseSlug = rtrim($baseSlug, '-');
    }
    if ($baseSlug === '') {
        return productJsonImportUniqueProductSlug($pdo);
    }

    $stmt = $pdo->prepare('SELECT 1 FROM products WHERE slug = :slug LIMIT 1');
    $slug = $baseSlug;
    for ($attempt = 1; $attempt <= 50; $attempt++) {
        $stmt->execute(['slug' => $slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $attempt;
    }

    return productJsonImportUniqueProductSlug($pdo);
}

/**
 * @param array<string, mixed> $item
 */
function productJsonImportResolveProductSlug(PDO $pdo, array $item): string
{
    $rawSlug = trim((string) ($item['slug'] ?? ''));
    if ($rawSlug !== '') {
        return productJsonImportEnsureUniqueProductSlug($pdo, $rawSlug);
    }

    $sourceUrl = trim((string) ($item['source_url'] ?? ''));
    if ($sourceUrl !== '') {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $basename = trim(basename($path), '/');
            if ($basename !== '' && $basename !== '.') {
                $fromUrl = productJsonImportSlugify($basename);
                if ($fromUrl !== '') {
                    return productJsonImportEnsureUniqueProductSlug($pdo, $fromUrl);
                }
            }
        }
    }

    $name = trim((string) ($item['name'] ?? ''));
    if ($name !== '') {
        $fromName = productJsonImportSlugify($name);
        if ($fromName !== '') {
            return productJsonImportEnsureUniqueProductSlug($pdo, $fromName);
        }
    }

    return productJsonImportUniqueProductSlug($pdo);
}

function productJsonImportResolveBrandId(PDO $pdo, string $rawBrandName): ?int
{
    $brandName = trim($rawBrandName);
    if ($brandName === '') {
        return null;
    }
    if (mb_strlen($brandName) > 120) {
        throw new RuntimeException('Brend adi en cox 120 simvol ola biler.');
    }

    $existingStmt = $pdo->prepare(
        "SELECT id FROM brands WHERE LOWER(name) = LOWER(:name) LIMIT 1"
    );
    $existingStmt->execute(['name' => $brandName]);
    $existingId = (int) $existingStmt->fetchColumn();
    if ($existingId > 0) {
        return $existingId;
    }

    $brandSlug = productJsonImportUniqueBrandSlug($pdo, $brandName);
    $insertStmt = $pdo->prepare('INSERT INTO brands (name, slug) VALUES (:name, :slug)');
    $insertStmt->execute([
        'name' => $brandName,
        'slug' => $brandSlug,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return list<array{id:int,name:string,parent_id:int|null}>
 */
function productJsonImportLoadActiveCategories(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT id, name, parent_id FROM categories WHERE is_active = 1"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'parent_id' => isset($row['parent_id']) && $row['parent_id'] !== null && $row['parent_id'] !== ''
                ? (int) $row['parent_id']
                : null,
        ];
    }

    return $out;
}

function productJsonImportCategoryCacheKey(?int $parentId, string $name): string
{
    return ($parentId ?? 0) . '|' . productJsonImportNormalizeText($name);
}

/**
 * @return array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int}
 */
function productJsonImportNormalizeCategoryCreateSettings(mixed $settings): array
{
    if (is_int($settings)) {
        if ($settings < 0) {
            return [
                'create_main' => false,
                'create_sub' => false,
                'create_sub_sub' => false,
                'parent_id' => 0,
            ];
        }

        return [
            'create_main' => true,
            'create_sub' => true,
            'create_sub_sub' => true,
            'parent_id' => max(0, $settings),
        ];
    }

    if (!is_array($settings)) {
        return [
            'create_main' => false,
            'create_sub' => false,
            'create_sub_sub' => false,
            'parent_id' => 0,
        ];
    }

    if (isset($settings['auto_create_parent_id']) && !isset($settings['create_main']) && !isset($settings['category_create'])) {
        return productJsonImportNormalizeCategoryCreateSettings((int) $settings['auto_create_parent_id']);
    }

    $src = is_array($settings['category_create'] ?? null) ? $settings['category_create'] : $settings;

    return [
        'create_main' => (bool) ($src['create_main'] ?? true),
        'create_sub' => (bool) ($src['create_sub'] ?? true),
        'create_sub_sub' => (bool) ($src['create_sub_sub'] ?? true),
        'parent_id' => max(0, (int) ($src['parent_id'] ?? $src['auto_create_parent_id'] ?? 0)),
    ];
}

/**
 * @param array<string, mixed> $post
 * @return array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int}
 */
function productJsonImportCategoryCreateSettingsFromPost(array $post): array
{
    return [
        'create_main' => isset($post['create_missing_categories']),
        'create_sub' => isset($post['create_missing_subcategories']),
        'create_sub_sub' => isset($post['create_missing_sub_sub_categories']),
        'parent_id' => (int) ($post['import_parent_category_id'] ?? 0),
    ];
}

/**
 * @param array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int} $createSettings
 */
function productJsonImportCanCreateCategoryLevel(array $createSettings, int $levelIndex, int $pathLength): bool
{
    return true;
}

/**
 * @return list<string>
 */
function productJsonImportExpandCategorySegments(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/\s*>\s*/u', $value) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out !== [] ? $out : [$value];
}

/**
 * @param list<string> $path
 */
function productJsonImportAppendCategorySegment(array &$path, string $segment): void
{
    foreach (productJsonImportExpandCategorySegments($segment) as $part) {
        if ($path === [] || end($path) !== $part) {
            $path[] = $part;
        }
    }
}

/**
 * JSON-da yalniz category/category_name var, main/sub yoxdur (birmarket formati).
 *
 * @param array<string, mixed> $item
 */
function productJsonImportIsSingleLeafCategoryItem(array $item): bool
{
    $path = productJsonImportExtractCategoryPath($item);

    return count($path) === 1;
}

function productJsonImportScoreCategoryNameMatch(string $importName, string $dbName): int
{
    $normalizedName = productJsonImportNormalizeText($importName);
    $dbNormalized = productJsonImportNormalizeText($dbName);
    if ($dbNormalized === '') {
        return 0;
    }

    if ($dbNormalized === $normalizedName) {
        return 100;
    }
    if (
        str_contains($normalizedName, $dbNormalized)
        || str_contains($dbNormalized, $normalizedName)
    ) {
        return 85;
    }

    $importTokens = productJsonImportTextTokens($importName);
    $dbTokens = productJsonImportTextTokens($dbName);
    if ($importTokens === [] || $dbTokens === []) {
        return 0;
    }

    $common = count(array_intersect($importTokens, $dbTokens));
    if ($common <= 0) {
        return 0;
    }

    return (int) round(($common / max(count($importTokens), count($dbTokens))) * 75);
}

/**
 * @param array<string, mixed> $item
 * @return list<string>
 */
function productJsonImportExtractCategoryPath(array $item): array
{
    $pick = static function (array $keys) use ($item): string {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }
            $value = trim((string) $item[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    if (isset($item['category_path']) && is_array($item['category_path'])) {
        $path = [];
        foreach ($item['category_path'] as $segment) {
            productJsonImportAppendCategorySegment($path, (string) $segment);
        }
        if ($path !== []) {
            return $path;
        }
    }

    $apiRoot = $pick(['category']);
    $apiSub = $pick(['subcategory', 'sub_category']);
    $apiLeaf = $pick(['sub_subcategory', 'sub_sub_category', 'category_leaf']);

    if ($apiSub !== '' || $apiLeaf !== '') {
        $path = [];
        if ($apiRoot !== '') {
            productJsonImportAppendCategorySegment($path, $apiRoot);
        }
        if ($apiSub !== '') {
            productJsonImportAppendCategorySegment($path, $apiSub);
        }
        if ($apiLeaf !== '') {
            productJsonImportAppendCategorySegment($path, $apiLeaf);
        }
        if ($path !== []) {
            return $path;
        }
    }

    $main = $pick(['main_category', 'category_main', 'esas_kateqoriya']);
    $sub = $pick(['sub_category', 'alt_kateqoriya']);
    $leaf = $pick(['sub_sub_category', 'third_category', 'alt_alt_kateqoriya']);

    $path = [];
    if ($main !== '') {
        productJsonImportAppendCategorySegment($path, $main);
    }
    if ($sub !== '') {
        productJsonImportAppendCategorySegment($path, $sub);
    }
    if ($leaf !== '') {
        productJsonImportAppendCategorySegment($path, $leaf);
    }
    if ($path !== []) {
        return $path;
    }

    $single = $pick(['category_name', 'category']);
    if ($single !== '') {
        $path = [];
        productJsonImportAppendCategorySegment($path, $single);

        return $path;
    }

    return [];
}

/**
 * @param list<array{id:int,name:string,parent_id:int|null}> $activeCategories
 */
function productJsonImportFindCategoryIdUnderParent(
    string $categoryName,
    ?int $parentId,
    array $activeCategories
): int {
    $bestId = 0;
    $bestScore = 0;
    foreach ($activeCategories as $row) {
        $rowParentId = $row['parent_id'] ?? null;
        if ($parentId === null) {
            if ($rowParentId !== null) {
                continue;
            }
        } elseif ($rowParentId !== $parentId) {
            continue;
        }

        $score = productJsonImportScoreCategoryNameMatch(
            $categoryName,
            (string) ($row['name'] ?? '')
        );
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestId = (int) ($row['id'] ?? 0);
        }
    }

    return $bestScore >= 50 ? $bestId : 0;
}

/**
 * @param list<array{id:int,name:string,parent_id:int|null}> $activeCategories
 */
function productJsonImportCreateCategoryUnderParent(
    PDO $pdo,
    string $categoryName,
    ?int $parentId,
    array &$activeCategories
): int {
    if ($parentId !== null && $parentId > 0) {
        $parentStmt = $pdo->prepare(
            "SELECT id FROM categories WHERE id = :id AND is_active = 1 LIMIT 1"
        );
        $parentStmt->execute(['id' => $parentId]);
        if (!$parentStmt->fetch()) {
            throw new RuntimeException('Kateqoriya parent tapilmadi: #' . $parentId);
        }
    }

    $slug = productJsonImportUniqueCategorySlug($pdo, $categoryName);
    $insertStmt = $pdo->prepare(
        "INSERT INTO categories (parent_id, name, slug, description, image_url, is_active, sort_order)
        VALUES (:parent_id, :name, :slug, NULL, NULL, 1, 9000)"
    );
    $insertStmt->execute([
        'parent_id' => $parentId !== null && $parentId > 0 ? $parentId : null,
        'name' => $categoryName,
        'slug' => $slug,
    ]);
    $newId = (int) $pdo->lastInsertId();
    $activeCategories[] = [
        'id' => $newId,
        'name' => $categoryName,
        'parent_id' => $parentId !== null && $parentId > 0 ? $parentId : null,
    ];

    return $newId;
}

/**
 * @param list<string> $path
 * @param array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int} $categoryCreateSettings
 * @param array<string, int> $importCategoryCache
 * @param list<array{id:int,name:string,parent_id:int|null}>|null $activeCategories
 */
function productJsonImportResolveCategoryPath(
    PDO $pdo,
    array $path,
    int $fallbackCategoryId,
    array $categoryCreateSettings,
    array &$importCategoryCache,
    ?array &$activeCategories = null
): int {
    if ($path === []) {
        if ($fallbackCategoryId > 0) {
            return $fallbackCategoryId;
        }
        throw new RuntimeException('Kateqoriya secilmeyib.');
    }

    if ($activeCategories === null) {
        $activeCategories = productJsonImportLoadActiveCategories($pdo);
    }

    $createSettings = productJsonImportNormalizeCategoryCreateSettings($categoryCreateSettings);
    $pathLength = count($path);
    $resolvedId = 0;

    foreach ($path as $levelIndex => $levelName) {
        $searchParentId = null;
        if ($levelIndex === 0) {
            if ($pathLength === 1 && $createSettings['parent_id'] > 0) {
                $searchParentId = $createSettings['parent_id'];
            } else {
                $searchParentId = null;
            }
        } else {
            $searchParentId = $resolvedId > 0 ? $resolvedId : null;
        }

        $cacheKey = productJsonImportCategoryCacheKey($searchParentId, $levelName);
        if ($cacheKey !== '|' && isset($importCategoryCache[$cacheKey])) {
            $resolvedId = $importCategoryCache[$cacheKey];
            continue;
        }

        $foundId = productJsonImportFindCategoryIdUnderParent(
            $levelName,
            $searchParentId,
            $activeCategories
        );
        if ($foundId > 0) {
            $resolvedId = $foundId;
            $importCategoryCache[$cacheKey] = $foundId;
            continue;
        }

        if (!productJsonImportCanCreateCategoryLevel($createSettings, $levelIndex, $pathLength)) {
            break;
        }

        $resolvedId = productJsonImportCreateCategoryUnderParent(
            $pdo,
            $levelName,
            $searchParentId,
            $activeCategories
        );
        $importCategoryCache[$cacheKey] = $resolvedId;
    }

    if ($resolvedId > 0) {
        return $resolvedId;
    }

    if ($fallbackCategoryId > 0) {
        return $fallbackCategoryId;
    }

    throw new RuntimeException('Kateqoriya tapilmadi: ' . implode(' > ', $path));
}

/**
 * @param array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int} $categoryCreateSettings
 * @param array<string, int> $importCategoryCache
 * @param list<array{id:int,name:string,parent_id:int|null}>|null $activeCategories
 */
function productJsonImportResolveCategoryIdByName(
    PDO $pdo,
    string $categoryName,
    int $fallbackCategoryId,
    array $categoryCreateSettings,
    array &$importCategoryCache,
    ?array &$activeCategories = null
): int {
    $categoryName = trim($categoryName);
    if ($categoryName === '') {
        if ($fallbackCategoryId > 0) {
            return $fallbackCategoryId;
        }
        throw new RuntimeException('Kateqoriya adi bosdur.');
    }

    $createSettings = productJsonImportNormalizeCategoryCreateSettings($categoryCreateSettings);
    $parentId = $createSettings['parent_id'] > 0 ? $createSettings['parent_id'] : null;

    if ($activeCategories === null) {
        $activeCategories = productJsonImportLoadActiveCategories($pdo);
    }

    $scopedCacheKey = productJsonImportCategoryCacheKey($parentId, $categoryName);
    if ($scopedCacheKey !== '|' && isset($importCategoryCache[$scopedCacheKey])) {
        return $importCategoryCache[$scopedCacheKey];
    }

    if ($parentId !== null) {
        $underParentId = productJsonImportFindCategoryIdUnderParent(
            $categoryName,
            $parentId,
            $activeCategories
        );
        if ($underParentId > 0) {
            $importCategoryCache[$scopedCacheKey] = $underParentId;

            return $underParentId;
        }
    } elseif ($parentId === null) {
        $cacheKey = productJsonImportCategoryCacheKey(null, $categoryName);
        if ($cacheKey !== '|' && isset($importCategoryCache[$cacheKey])) {
            return $importCategoryCache[$cacheKey];
        }

        $bestId = 0;
        $bestScore = 0;
        foreach ($activeCategories as $row) {
            $score = productJsonImportScoreCategoryNameMatch(
                $categoryName,
                (string) ($row['name'] ?? '')
            );
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) ($row['id'] ?? 0);
            }
        }

        if ($bestScore >= 50 && $bestId > 0) {
            $importCategoryCache[$cacheKey] = $bestId;

            return $bestId;
        }
    }

    if (productJsonImportCanCreateCategoryLevel($createSettings, 0, 1)) {
        $newId = productJsonImportCreateCategoryUnderParent(
            $pdo,
            $categoryName,
            $parentId,
            $activeCategories
        );
        $importCategoryCache[$scopedCacheKey] = $newId;
        if ($parentId === null) {
            $importCategoryCache[productJsonImportCategoryCacheKey(null, $categoryName)] = $newId;
        }

        return $newId;
    }

    if ($fallbackCategoryId > 0) {
        $importCategoryCache[$scopedCacheKey] = $fallbackCategoryId;

        return $fallbackCategoryId;
    }

    throw new RuntimeException('Kateqoriya tapilmadi: ' . $categoryName);
}

/**
 * @param array<string, mixed> $item
 * @param array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int}|array<string,mixed> $categoryCreateSettings
 * @param array<string, int> $importCategoryCache
 * @param list<array{id:int,name:string,parent_id:int|null}>|null $activeCategories
 */
function productJsonImportResolveCategoryId(
    PDO $pdo,
    array $item,
    int $fallbackCategoryId = 0,
    array $categoryCreateSettings = [],
    array &$importCategoryCache = [],
    ?array &$activeCategories = null
): int {
    $path = productJsonImportExtractCategoryPath($item);
    if ($path !== []) {
        if (count($path) > 1) {
            return productJsonImportResolveCategoryPath(
                $pdo,
                $path,
                $fallbackCategoryId,
                $categoryCreateSettings,
                $importCategoryCache,
                $activeCategories
            );
        }

        return productJsonImportResolveCategoryIdByName(
            $pdo,
            $path[0],
            $fallbackCategoryId,
            $categoryCreateSettings,
            $importCategoryCache,
            $activeCategories
        );
    }

    $categorySlug = trim((string) ($item['category_slug'] ?? ''));
    if ($categorySlug !== '') {
        $slugStmt = $pdo->prepare(
            "SELECT id FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1"
        );
        $slugStmt->execute(['slug' => $categorySlug]);
        $slugCategoryId = (int) $slugStmt->fetchColumn();
        if ($slugCategoryId <= 0) {
            throw new RuntimeException('Kateqoriya slug tapilmadi: ' . $categorySlug);
        }
        return $slugCategoryId;
    }

    $leafCategoryId = (int) ($item['category_id'] ?? 0);
    if ($leafCategoryId > 0) {
        $checkStmt = $pdo->prepare(
            "SELECT id FROM categories WHERE id = :id AND is_active = 1 LIMIT 1"
        );
        $checkStmt->execute(['id' => $leafCategoryId]);
        if (!$checkStmt->fetch()) {
            throw new RuntimeException('Kateqoriya tapilmadi: #' . $leafCategoryId);
        }
        return $leafCategoryId;
    }

    if ($fallbackCategoryId > 0) {
        return $fallbackCategoryId;
    }

    throw new RuntimeException('Kateqoriya secilmeyib.');
}

/**
 * @param array<string, mixed> $item
 * @param array{create_main:bool,create_sub:bool,create_sub_sub:bool,parent_id:int}|array<string,mixed> $categoryCreateSettings
 * @param array<string, int> $importCategoryCache
 * @param list<array{id:int,name:string}>|null $activeCategories
 */
function productJsonImportSingleProduct(
    PDO $pdo,
    array $item,
    int $fallbackCategoryId = 0,
    array $categoryCreateSettings = [],
    array &$importCategoryCache = [],
    ?array &$activeCategories = null,
    ?string &$importAction = null
): int {
    $importAction = 'created';
    $name = trim((string) ($item['name'] ?? ''));
    $sku = trim((string) ($item['sku'] ?? ''));
    if ($sku === '') {
        $sku = trim((string) ($item['gtin'] ?? ''));
    }
    if ($name === '' || $sku === '') {
        throw new RuntimeException('Ad ve SKU/GTIN mecburidir.');
    }

    $basePrice = round((float) ($item['base_price'] ?? $item['price'] ?? 0), 2);
    $compareAtPrice = null;
    $oldPriceRaw = $item['old_price'] ?? $item['compare_at_price'] ?? null;
    if ($oldPriceRaw !== null && $oldPriceRaw !== '' && is_numeric($oldPriceRaw)) {
        $compareAtPrice = round((float) $oldPriceRaw, 2);
        if ($compareAtPrice <= 0) {
            $compareAtPrice = null;
        } elseif ($basePrice > 0 && $compareAtPrice <= $basePrice) {
            $compareAtPrice = null;
        }
    }
    if (array_key_exists('in_stock', $item)) {
        $inStock = filter_var($item['in_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $stockQty = $inStock ? max(1, (int) ($item['stock_qty'] ?? $item['stock'] ?? 1)) : 0;
    } else {
        $stockQty = max(0, (int) ($item['stock_qty'] ?? $item['stock'] ?? 0));
    }

    $skuCheckStmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
    $skuCheckStmt->execute(['sku' => $sku]);
    $existingRow = $skuCheckStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingRow) {
        $existingId = (int) $existingRow['id'];
        $updateSql = 'UPDATE products SET base_price = :base_price, stock_qty = stock_qty + :stock_add, updated_at = CURRENT_TIMESTAMP';
        $updateParams = [
            'base_price' => $basePrice,
            'stock_add' => $stockQty,
            'id' => $existingId,
        ];
        if ($compareAtPrice !== null) {
            $updateSql .= ', compare_at_price = :compare_at_price';
            $updateParams['compare_at_price'] = $compareAtPrice;
        }
        $updateSql .= ' WHERE id = :id';

        $pdo->prepare($updateSql)->execute($updateParams);
        $importAction = 'updated';

        return $existingId;
    }

    $categoryId = productJsonImportResolveCategoryId(
        $pdo,
        $item,
        $fallbackCategoryId,
        $categoryCreateSettings,
        $importCategoryCache,
        $activeCategories
    );
    $brandId = productJsonImportResolveBrandId($pdo, (string) ($item['brand_name'] ?? $item['brand'] ?? ''));
    $description = maxhomeSanitizeProductHtml(trim((string) ($item['description'] ?? '')));
    $shortDescription = maxhomeSanitizeProductHtml(trim((string) ($item['short_description'] ?? '')));
    if ($shortDescription === '' && $description !== '') {
        $shortDescription = maxhomeProductPlainText($description, 280);
    }
    $status = trim((string) ($item['status'] ?? 'active'));
    if (!in_array($status, ['draft', 'active', 'archived'], true)) {
        $status = 'active';
    }

    $slug = trim(productJsonImportResolveProductSlug($pdo, $item));
    if ($slug === '') {
        $slug = productJsonImportUniqueProductSlug($pdo);
    }

    $pdo->beginTransaction();
    try {
        if (maxhomeProductsOnlineVisibleColumnExists($pdo)) {
            $stmt = $pdo->prepare(
                "INSERT INTO products (
                    category_id, brand_id, name, slug, short_description, description,
                    sku, base_price, compare_at_price, stock_qty, status, online_visible
                ) VALUES (
                    :category_id, :brand_id, :name, :slug, :short_description, :description,
                    :sku, :base_price, :compare_at_price, :stock_qty, :status, 1
                )"
            );
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO products (
                    category_id, brand_id, name, slug, short_description, description,
                    sku, base_price, compare_at_price, stock_qty, status
                ) VALUES (
                    :category_id, :brand_id, :name, :slug, :short_description, :description,
                    :sku, :base_price, :compare_at_price, :stock_qty, :status
                )"
            );
        }
        $stmt->execute([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $slug,
            'short_description' => $shortDescription !== '' ? $shortDescription : null,
            'description' => $description !== '' ? $description : null,
            'sku' => $sku,
            'base_price' => $basePrice,
            'compare_at_price' => $compareAtPrice,
            'stock_qty' => $stockQty,
            'status' => $status,
        ]);

        $productId = (int) $pdo->lastInsertId();

        $specs = $item['specs'] ?? $item['spec'] ?? [];
        if (!is_array($specs)) {
            $specs = [];
        }
        if ($specs !== []) {
            $specStmt = $pdo->prepare(
                "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
                VALUES (:product_id, :spec_key, :spec_value, :sort_order)"
            );
            $sortOrder = 0;
            foreach ($specs as $specKey => $specValue) {
                if (!is_scalar($specValue)) {
                    continue;
                }
                $key = trim((string) $specKey);
                $value = trim((string) $specValue);
                if ($key === '' || $value === '' || maxhomeIsInternalProductSpecKey($key)) {
                    continue;
                }
                $specStmt->execute([
                    'product_id' => $productId,
                    'spec_key' => $key,
                    'spec_value' => $value,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        $imageUrls = [];
        $singleImage = trim((string) ($item['image_url'] ?? $item['image'] ?? ''));
        if ($singleImage !== '') {
            $imageUrls[] = $singleImage;
        }
        if (isset($item['images']) && is_array($item['images'])) {
            foreach ($item['images'] as $imageUrl) {
                if (!is_scalar($imageUrl)) {
                    continue;
                }
                $normalized = trim((string) $imageUrl);
                if ($normalized !== '' && !in_array($normalized, $imageUrls, true)) {
                    $imageUrls[] = $normalized;
                }
            }
        }

        if ($imageUrls !== []) {
            $imageStmt = $pdo->prepare(
                "INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
                VALUES (:product_id, :image_url, :alt_text, :is_primary, :sort_order)"
            );
            foreach ($imageUrls as $imageIndex => $imageUrl) {
                $imageStmt->execute([
                    'product_id' => $productId,
                    'image_url' => $imageUrl,
                    'alt_text' => $name,
                    'is_primary' => $imageIndex === 0 ? 1 : 0,
                    'sort_order' => $imageIndex,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $productId;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function productJsonImportCreateJobFromRows(array $rows, array $settings): array
{
    productJsonImportCleanupOldJobs();
    productJsonImportEnsureJobsRoot();

    $importLimit = max(0, (int) ($settings['import_limit'] ?? 0));
    if ($importLimit > 0) {
        $rows = array_slice($rows, 0, $importLimit);
    }
    if ($rows === []) {
        throw new RuntimeException('Idxal ucun mehsul yoxdur.');
    }

    $batchSize = max(1, (int) ($settings['batch_size'] ?? PRODUCT_JSON_IMPORT_BATCH_SIZE));
    $batches = array_chunk($rows, $batchSize);
    $jobId = bin2hex(random_bytes(16));
    $jobDir = productJsonImportJobDir($jobId);
    if (!mkdir($jobDir, 0775, true) && !is_dir($jobDir)) {
        throw new RuntimeException('Import job qovlugu yaradila bilmedi.');
    }

    foreach ($batches as $batchIndex => $batchRows) {
        $batchPath = $jobDir . DIRECTORY_SEPARATOR . 'batch_' . $batchIndex . '.json';
        $encoded = json_encode($batchRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($batchPath, $encoded) === false) {
            productJsonImportDeleteJobDir($jobDir);
            throw new RuntimeException('Import batch fayli yazila bilmedi.');
        }
    }

    $meta = [
        'job_id' => $jobId,
        'created_at' => date('c'),
        'source' => (string) ($settings['source'] ?? 'json'),
        'total' => count($rows),
        'batch_size' => $batchSize,
        'batch_count' => count($batches),
        'current_batch' => 0,
        'processed' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => [],
        'errors' => [],
        'failed_products' => [],
        'settings' => [
            'default_category_id' => (int) ($settings['default_category_id'] ?? 0),
            'category_create' => productJsonImportNormalizeCategoryCreateSettings(
                is_array($settings['category_create'] ?? null)
                    ? $settings['category_create']
                    : $settings
            ),
            'import_limit' => $importLimit,
        ],
        'category_cache' => [],
        'status' => 'ready',
    ];
    productJsonImportWriteMeta($jobId, $meta);

    return [
        'job_id' => $jobId,
        'total' => $meta['total'],
        'batch_count' => $meta['batch_count'],
        'batch_size' => $meta['batch_size'],
        'source' => $meta['source'],
    ];
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function productJsonImportCreateJob(array $file, array $settings): array
{
    $settings['source'] = 'json';
    return productJsonImportCreateJobFromRows(productJsonImportParseUpload($file), $settings);
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function productJsonImportCreateJobFromApi(PDO $pdo, array $settings): array
{
    $apiUrl = trim((string) ($settings['api_url'] ?? ''));
    $apiToken = trim((string) ($settings['api_token'] ?? ''));
    if ($apiUrl === '' || $apiToken === '') {
        $saved = productJsonImportGetApiSettings($pdo);
        if ($apiUrl === '') {
            $apiUrl = $saved['url'];
        }
        if ($apiToken === '') {
            $apiToken = $saved['token'];
        }
    }

    productJsonImportSaveApiSettings($pdo, $apiUrl, $apiToken);
    $rows = productJsonImportFetchProductsFromApi($apiUrl, $apiToken);
    $settings['source'] = 'api';

    return productJsonImportCreateJobFromRows($rows, $settings);
}

/**
 * @param array<string, mixed> $meta
 * @param array<string, mixed> $row
 */
function productJsonImportRecordFailure(
    array &$meta,
    array $row,
    string $label,
    string $reason,
    string $type = 'error'
): void {
    $sku = trim((string) ($row['sku'] ?? ''));
    if ($sku === '') {
        $sku = trim((string) ($row['gtin'] ?? ''));
    }

    $entry = [
        'name' => $label,
        'sku' => $sku,
        'reason' => $reason,
        'type' => $type,
    ];

    if (!isset($meta['failed_products']) || !is_array($meta['failed_products'])) {
        $meta['failed_products'] = [];
    }
    $meta['failed_products'][] = $entry;

    $line = $label;
    if ($sku !== '') {
        $line .= ' [' . $sku . ']';
    }
    $line .= ': ' . $reason;

    if ($type === 'skipped') {
        if (!isset($meta['skipped']) || !is_array($meta['skipped'])) {
            $meta['skipped'] = [];
        }
        $meta['skipped'][] = $line;
        return;
    }

    if (!isset($meta['errors']) || !is_array($meta['errors'])) {
        $meta['errors'] = [];
    }
    $meta['errors'][] = $line;
}

/**
 * @param array<string, mixed> $row
 */
function productJsonImportProductLabel(array $row, int $fallbackIndex): string
{
    $label = trim((string) ($row['name'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return 'Setir #' . $fallbackIndex;
}

/**
 * @return array<string, mixed>
 */
function productJsonImportProcessNextBatch(PDO $pdo, string $jobId): array
{
    set_time_limit(120);

    $meta = productJsonImportReadMeta($jobId);
    if (($meta['status'] ?? '') === 'done') {
        return productJsonImportBuildProgressResponse($meta, true);
    }

    $currentBatch = (int) ($meta['current_batch'] ?? 0);
    $batchCount = (int) ($meta['batch_count'] ?? 0);
    if ($currentBatch >= $batchCount) {
        $meta['status'] = 'done';
        productJsonImportWriteMeta($jobId, $meta);
        productJsonImportDeleteJobDir(productJsonImportJobDir($jobId));

        return productJsonImportBuildProgressResponse($meta, true);
    }

    $batchPath = productJsonImportJobDir($jobId) . DIRECTORY_SEPARATOR . 'batch_' . $currentBatch . '.json';
    $batchRows = null;

    try {
        if (!is_file($batchPath)) {
            throw new RuntimeException('Import batch fayli tapilmadi.');
        }

        $batchRaw = file_get_contents($batchPath);
        $batchRows = json_decode($batchRaw !== false ? $batchRaw : '', true);
        if (!is_array($batchRows)) {
            throw new RuntimeException('Import batch oxunmadi.');
        }

        $settings = is_array($meta['settings'] ?? null) ? $meta['settings'] : [];
        $defaultCategoryId = (int) ($settings['default_category_id'] ?? 0);
        $categoryCreateSettings = productJsonImportNormalizeCategoryCreateSettings($settings);
        /** @var array<string, int> $importCategoryCache */
        $importCategoryCache = is_array($meta['category_cache'] ?? null) ? $meta['category_cache'] : [];
        $activeCategories = null;

        foreach ($batchRows as $rowIndex => $row) {
            if (!is_array($row)) {
                $label = 'Setir #' . ((int) $meta['processed'] + (int) $rowIndex + 1);
                productJsonImportRecordFailure($meta, [], $label, 'Mehsul obyekti deyil.');
                $meta['processed'] = (int) ($meta['processed'] ?? 0) + 1;
                continue;
            }

            $label = productJsonImportProductLabel($row, (int) $meta['processed'] + (int) $rowIndex + 1);
            try {
                $importAction = 'created';
                productJsonImportSingleProduct(
                    $pdo,
                    $row,
                    $defaultCategoryId,
                    $categoryCreateSettings,
                    $importCategoryCache,
                    $activeCategories,
                    $importAction
                );
                if ($importAction === 'updated') {
                    $meta['updated'] = (int) ($meta['updated'] ?? 0) + 1;
                } else {
                    $meta['imported'] = (int) ($meta['imported'] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                productJsonImportRecordFailure($meta, $row, $label, $msg, 'error');
            }
            $meta['processed'] = (int) ($meta['processed'] ?? 0) + 1;
        }

        $meta['category_cache'] = $importCategoryCache;
    } catch (Throwable $batchError) {
        $batchErrorMessage = $batchError->getMessage();
        if (isset($batchRows) && is_array($batchRows) && $batchRows !== []) {
            foreach ($batchRows as $rowIndex => $row) {
                if (!is_array($row)) {
                    $label = 'Setir #' . ((int) $meta['processed'] + 1);
                    productJsonImportRecordFailure($meta, [], $label, $batchErrorMessage);
                    $meta['processed'] = (int) ($meta['processed'] ?? 0) + 1;
                    continue;
                }
                $label = productJsonImportProductLabel($row, (int) $meta['processed'] + 1);
                productJsonImportRecordFailure($meta, $row, $label, $batchErrorMessage);
                $meta['processed'] = (int) ($meta['processed'] ?? 0) + 1;
            }
        } else {
            $batchSize = max(1, (int) ($meta['batch_size'] ?? PRODUCT_JSON_IMPORT_BATCH_SIZE));
            $total = max(0, (int) ($meta['total'] ?? 0));
            $processed = max(0, (int) ($meta['processed'] ?? 0));
            $remaining = max(0, min($batchSize, $total - $processed));
            for ($i = 0; $i < $remaining; $i++) {
                productJsonImportRecordFailure(
                    $meta,
                    [],
                    'Batch #' . ($currentBatch + 1) . ' / setir #' . ($processed + $i + 1),
                    $batchErrorMessage
                );
                $meta['processed'] = (int) ($meta['processed'] ?? 0) + 1;
            }
        }
    }

    if (is_file($batchPath)) {
        @unlink($batchPath);
    }
    $meta['current_batch'] = $currentBatch + 1;
    $meta['status'] = ((int) $meta['current_batch'] >= $batchCount) ? 'done' : 'running';

    if ($meta['status'] === 'done') {
        productJsonImportWriteMeta($jobId, $meta);
        productJsonImportDeleteJobDir(productJsonImportJobDir($jobId));
    } else {
        productJsonImportWriteMeta($jobId, $meta);
    }

    return productJsonImportBuildProgressResponse($meta, $meta['status'] === 'done');
}

/**
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function productJsonImportBuildProgressResponse(array $meta, bool $done): array
{
    $total = max(0, (int) ($meta['total'] ?? 0));
    $processed = max(0, (int) ($meta['processed'] ?? 0));
    $imported = max(0, (int) ($meta['imported'] ?? 0));
    $updated = max(0, (int) ($meta['updated'] ?? 0));
    $failedProducts = is_array($meta['failed_products'] ?? null) ? $meta['failed_products'] : [];
    $skippedCount = 0;
    $errorCount = 0;
    foreach ($failedProducts as $failedProduct) {
        if (!is_array($failedProduct)) {
            $errorCount++;
            continue;
        }
        if (($failedProduct['type'] ?? '') === 'skipped') {
            $skippedCount++;
            continue;
        }
        $errorCount++;
    }
    $failedCount = count($failedProducts);
    $percent = $total > 0 ? (int) round(($processed / $total) * 100) : ($done ? 100 : 0);

    if ($done) {
        $messageParts = [];
        if ($imported > 0) {
            $messageParts[] = $imported . ' yeni mehsul elave edildi';
        }
        if ($updated > 0) {
            $messageParts[] = $updated . ' movcud mehsul yenilendi (qiymet/stok)';
        }
        $message = $messageParts !== []
            ? implode(', ', $messageParts) . '.'
            : 'Emal tamamlandi.';
    } else {
        $message = $processed . ' / ' . $total . ' mehsul emal olundu...';
    }
    if ($done && $errorCount > 0) {
        $message .= ' ' . $errorCount . ' mehsul xeta ile yuklenmedi.';
    }

    return [
        'ok' => true,
        'done' => $done,
        'job_id' => (string) ($meta['job_id'] ?? ''),
        'total' => $total,
        'processed' => $processed,
        'imported' => $imported,
        'updated' => $updated,
        'failed_count' => $failedCount,
        'skipped_count' => $skippedCount,
        'error_count' => $errorCount,
        'failed_products' => $failedProducts,
        'percent' => min(100, $percent),
        'current_batch' => (int) ($meta['current_batch'] ?? 0),
        'batch_count' => (int) ($meta['batch_count'] ?? 0),
        'skipped' => is_array($meta['skipped'] ?? null) ? $meta['skipped'] : [],
        'errors' => is_array($meta['errors'] ?? null) ? $meta['errors'] : [],
        'message' => $message,
    ];
}

function productJsonImportCancelJob(string $jobId): void
{
    $jobDir = productJsonImportJobDir($jobId);
    productJsonImportDeleteJobDir($jobDir);
}
