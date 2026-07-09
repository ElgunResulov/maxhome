<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';
$allCategories = $pdo->query(
    "SELECT id, parent_id, name, slug, sort_order
     FROM categories
     ORDER BY sort_order ASC, name ASC"
)->fetchAll() ?: [];
$allBrands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll() ?: [];

/**
 * Parent kateqoriya dropdown-u üçün iyerarxik (depth) siyahı.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{id:int,name:string,slug:string,depth:int}>
 */
function buildCategoryTreeOptions(array $rows): array
{
    $byId = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $byId[$id] = $row;
    }

    $childrenByParent = [];
    foreach ($byId as $row) {
        $pid = $row['parent_id'];
        $key = $pid === null ? 0 : (int) $pid;
        $childrenByParent[$key][] = $row;
    }

    $walk = static function (int $parentId, int $depth, array &$out) use (&$walk, $childrenByParent): void {
        $children = $childrenByParent[$parentId] ?? [];
        usort(
            $children,
            static function (array $a, array $b): int {
                $so = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
                return $so !== 0 ? $so : strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
        );
        foreach ($children as $c) {
            $id = (int) ($c['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($c['name'] ?? ''),
                'slug' => (string) ($c['slug'] ?? ''),
                'depth' => $depth,
            ];
            $walk($id, $depth + 1, $out);
        }
    };

    $out = [];
    $walk(0, 0, $out);

    return $out;
}

function saveCategoryImage(array $file): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Yukleme verisi yanlisdir.');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yuklenmesi ugursuz oldu.');
    }

    $maxBytes = 10 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Fayl olcusu 10MB-den boyuk ola bilmez.');
    }

    $tmpPath = (string) $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpPath);
    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowedMimeToExt[$mimeType])) {
        throw new RuntimeException('Yalniz JPG, PNG ve WEBP sekilleri qebul edilir.');
    }

    $extension = $allowedMimeToExt[$mimeType];
    $targetDir = __DIR__ . '/../uploads/categories';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Uploads qovlugu yaradilamadi.');
    }

    $filename = 'category_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Sekil uploads qovluguna kopyalana bilmedi.');
    }

    return 'uploads/categories/' . $filename;
}

/**
 * @return list<array<int, string>>
 */
function parseCsvRows(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('CSV fayli acila bilmedi.');
    }
    try {
        while (($cols = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = array_map(
                static fn($v): string => trim((string) $v),
                $cols
            );
        }
    } finally {
        fclose($handle);
    }
    return $rows;
}

/**
 * @return list<array<int, string>>
 */
function parseXlsxRows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'XLSX importu ucun serverde ZipArchive modulu aktiv deyil. '
            . 'Zehmet olmasa fayli CSV (UTF-8) formatinda saxlayib import edin.'
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('XLSX fayli acila bilmedi.');
    }

    try {
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx !== false) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                        continue;
                    }
                    $parts = [];
                    foreach ($si->r as $run) {
                        $parts[] = (string) ($run->t ?? '');
                    }
                    $sharedStrings[] = implode('', $parts);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            throw new RuntimeException('XLSX sheet1 tapilmadi.');
        }
        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false || !isset($sheet->sheetData)) {
            throw new RuntimeException('XLSX melumatlari oxuna bilmedi.');
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $c) {
                $value = '';
                $type = (string) ($c['t'] ?? '');
                if ($type === 's') {
                    $idx = (int) ($c->v ?? -1);
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($c->is->t ?? '');
                } else {
                    $value = (string) ($c->v ?? '');
                }
                $row[] = trim($value);
            }
            $rows[] = $row;
        }
        return $rows;
    } finally {
        $zip->close();
    }
}

/**
 * @return list<array<string, mixed>>
 */
function parseCategoryImportRows(array $rows): array
{
    if (empty($rows)) {
        throw new RuntimeException('Fayl bosdur.');
    }

    $headerRaw = array_shift($rows);
    $header = array_map(
        static fn(string $v): string => strtolower(trim($v)),
        is_array($headerRaw) ? $headerRaw : []
    );

    $required = ['name', 'slug'];
    foreach ($required as $key) {
        if (!in_array($key, $header, true)) {
            throw new RuntimeException('Header-da "' . $key . '" sutunu mutleq olmalidir.');
        }
    }

    $result = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || count(array_filter($row, static fn(string $v): bool => $v !== '')) === 0) {
            continue;
        }
        $mapped = [];
        foreach ($header as $colIndex => $colName) {
            if ($colName === '') {
                continue;
            }
            $mapped[$colName] = trim((string) ($row[$colIndex] ?? ''));
        }
        if (($mapped['name'] ?? '') === '' || ($mapped['slug'] ?? '') === '') {
            throw new RuntimeException('Setir ' . ($index + 2) . ': name ve slug bos ola bilmez.');
        }
        $result[] = [
            'name' => (string) $mapped['name'],
            'slug' => (string) $mapped['slug'],
            'description' => ($mapped['description'] ?? '') !== '' ? (string) $mapped['description'] : null,
            'image_url' => ($mapped['image_url'] ?? '') !== '' ? (string) $mapped['image_url'] : null,
            'sort_order' => (int) ($mapped['sort_order'] ?? 0),
            'is_active' => isset($mapped['is_active']) ? ((int) $mapped['is_active'] === 1 ? 1 : 0) : 1,
            'parent_slug' => ($mapped['parent_slug'] ?? '') !== '' ? (string) $mapped['parent_slug'] : null,
            'brand_name' => ($mapped['brand_name'] ?? '') !== '' ? (string) $mapped['brand_name'] : null,
        ];
    }

    if (empty($result)) {
        throw new RuntimeException('Import ucun uygun setir tapilmadi.');
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $parentId = (int) ($_POST['parent_id'] ?? 0);
            $brandId = (int) ($_POST['brand_id'] ?? 0);

            $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = saveCategoryImage($_FILES['image']);
            }
            $imageUrl = $imageUrl !== '' ? $imageUrl : null;

            if ($name === '' || $slug === '') {
                throw new RuntimeException('Ad ve slug mecburidir.');
            }

            if ($parentId > 0) {
                $parentExists = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
                $parentExists->execute(['id' => $parentId]);
                if (!$parentExists->fetchColumn()) {
                    throw new RuntimeException('Secilen ust kateqoriya tapilmadi.');
                }
            }
            if ($brandId > 0) {
                $brandExists = $pdo->prepare("SELECT id FROM brands WHERE id = :id LIMIT 1");
                $brandExists->execute(['id' => $brandId]);
                if (!$brandExists->fetchColumn()) {
                    throw new RuntimeException('Secilen brend tapilmadi.');
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO categories (parent_id, brand_id, name, slug, description, image_url, is_active, sort_order)
                 VALUES (:parent_id, :brand_id, :name, :slug, :description, :image_url, :is_active, :sort_order)"
            );
            $stmt->execute([
                'parent_id' => $parentId > 0 ? $parentId : null,
                'brand_id' => $brandId > 0 ? $brandId : null,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'image_url' => $imageUrl,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);
            $message = 'Kateqoriya ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $parentId = (int) ($_POST['parent_id'] ?? 0);
            $brandId = (int) ($_POST['brand_id'] ?? 0);

            if ($id <= 0 || $name === '' || $slug === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }
            if ($parentId === $id) {
                throw new RuntimeException('Kateqoriya ozunu ust kateqoriya secə bilmez.');
            }

            if ($parentId > 0) {
                $parentExists = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
                $parentExists->execute(['id' => $parentId]);
                if (!$parentExists->fetchColumn()) {
                    throw new RuntimeException('Secilen ust kateqoriya tapilmadi.');
                }

                $cycleCheck = $pdo->prepare(
                    "WITH RECURSIVE descendants AS (
                        SELECT id FROM categories WHERE parent_id = :self_id
                        UNION ALL
                        SELECT c.id FROM categories c
                        INNER JOIN descendants d ON c.parent_id = d.id
                    )
                    SELECT 1 FROM descendants WHERE id = :parent_id LIMIT 1"
                );
                $cycleCheck->execute([
                    'self_id' => $id,
                    'parent_id' => $parentId,
                ]);
                if ((bool) $cycleCheck->fetchColumn()) {
                    throw new RuntimeException('Dovriye yarandir: secilen ust kateqoriya alt budaqdadir.');
                }
            }

            if ($brandId > 0) {
                $brandExists = $pdo->prepare("SELECT id FROM brands WHERE id = :id LIMIT 1");
                $brandExists->execute(['id' => $brandId]);
                if (!$brandExists->fetchColumn()) {
                    throw new RuntimeException('Secilen brend tapilmadi.');
                }
            }

            $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = saveCategoryImage($_FILES['image']);
            }
            $imageUrl = $imageUrl !== '' ? $imageUrl : null;

            $stmt = $pdo->prepare(
                "UPDATE categories
                 SET parent_id = :parent_id,
                     brand_id = :brand_id,
                     name = :name,
                     slug = :slug,
                     description = :description,
                     image_url = :image_url,
                     is_active = :is_active,
                     sort_order = :sort_order,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'parent_id' => $parentId > 0 ? $parentId : null,
                'brand_id' => $brandId > 0 ? $brandId : null,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'image_url' => $imageUrl,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);
            $message = 'Kateqoriya ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun kateqoriya secin.');
            }
            $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute(['id' => $id]);
            $remaining = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
            if ($remaining === 0) {
                $pdo->exec('ALTER TABLE categories AUTO_INCREMENT = 1');
            }
            $message = 'Kateqoriya silindi.';
        } elseif ($action === 'delete_all') {
            $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
            if ($productCount > 0) {
                throw new RuntimeException(
                    'Butun kateqoriyalari silmek olmaz: ' . $productCount
                    . ' mehsul kateqoriyaya baglidir. Evvelce admin mehsullar sehifesinden mehsullari silin.'
                );
            }
            $pdo->exec('DELETE FROM categories');
            $pdo->exec('ALTER TABLE categories AUTO_INCREMENT = 1');
            $message = 'Butun kateqoriyalar silindi.';
        } elseif ($action === 'import_excel') {
            if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
                throw new RuntimeException('Import fayli gonderilmedi.');
            }
            $file = $_FILES['import_file'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Import fayli yuklenmedi.');
            }

            $originalName = strtolower((string) ($file['name'] ?? ''));
            $tmpPath = (string) ($file['tmp_name'] ?? '');
            if ($tmpPath === '') {
                throw new RuntimeException('Import fayli kecici qovluqda tapilmadi.');
            }

            if (str_ends_with($originalName, '.xlsx')) {
                $rawRows = parseXlsxRows($tmpPath);
            } elseif (str_ends_with($originalName, '.csv')) {
                $rawRows = parseCsvRows($tmpPath);
            } else {
                throw new RuntimeException('Yalniz .xlsx ve .csv fayllari desteklenir.');
            }

            $rows = parseCategoryImportRows($rawRows);

            $brandByName = [];
            foreach ($allBrands as $b) {
                $brandByName[mb_strtolower(trim((string) ($b['name'] ?? '')), 'UTF-8')] = (int) ($b['id'] ?? 0);
            }

            $slugToParentIdStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
            $upsertStmt = $pdo->prepare(
                "INSERT INTO categories (parent_id, brand_id, name, slug, description, image_url, is_active, sort_order)
                 VALUES (:parent_id, :brand_id, :name, :slug, :description, :image_url, :is_active, :sort_order)
                 ON DUPLICATE KEY UPDATE
                    parent_id = VALUES(parent_id),
                    brand_id = VALUES(brand_id),
                    name = VALUES(name),
                    description = VALUES(description),
                    image_url = VALUES(image_url),
                    is_active = VALUES(is_active),
                    sort_order = VALUES(sort_order),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $parentUpdateStmt = $pdo->prepare(
                "UPDATE categories SET parent_id = :parent_id, updated_at = CURRENT_TIMESTAMP WHERE slug = :slug"
            );

            $insertedOrUpdated = 0;
            foreach ($rows as $idx => $r) {
                $brandId = null;
                if (!empty($r['brand_name'])) {
                    $brandKey = mb_strtolower(trim((string) $r['brand_name']), 'UTF-8');
                    if (!isset($brandByName[$brandKey]) || $brandByName[$brandKey] <= 0) {
                        throw new RuntimeException(
                            'Setir ' . ($idx + 2) . ': brand_name tapilmadi -> ' . (string) $r['brand_name']
                        );
                    }
                    $brandId = $brandByName[$brandKey];
                }

                $upsertStmt->execute([
                    'parent_id' => null,
                    'brand_id' => $brandId,
                    'name' => (string) $r['name'],
                    'slug' => (string) $r['slug'],
                    'description' => $r['description'],
                    'image_url' => $r['image_url'],
                    'is_active' => (int) $r['is_active'],
                    'sort_order' => (int) $r['sort_order'],
                ]);
                $insertedOrUpdated++;
            }

            foreach ($rows as $idx => $r) {
                $parentId = null;
                if (!empty($r['parent_slug'])) {
                    $slugToParentIdStmt->execute(['slug' => (string) $r['parent_slug']]);
                    $parentIdRaw = $slugToParentIdStmt->fetchColumn();
                    if ($parentIdRaw === false) {
                        throw new RuntimeException(
                            'Setir ' . ($idx + 2) . ': parent_slug tapilmadi -> ' . (string) $r['parent_slug']
                        );
                    }
                    $parentId = (int) $parentIdRaw;
                }
                $parentUpdateStmt->execute([
                    'parent_id' => $parentId,
                    'slug' => (string) $r['slug'],
                ]);
            }

            $message = $insertedOrUpdated . ' setir Excel/CSV-den ugurla import edildi.';
        } else {
            throw new RuntimeException('Bilinmeyen emeliyyat.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}
$categoryTreeOptions = buildCategoryTreeOptions($allCategories);

/** @var array<int, array<string, mixed>> $allCategoriesById */
$allCategoriesById = [];
foreach ($allCategories as $row) {
    $cid = (int) ($row['id'] ?? 0);
    if ($cid > 0) {
        $allCategoriesById[$cid] = $row;
    }
}

/** @var list<array{id:int,name:string,slug:string}> $rootCategoryOptions */
$rootCategoryOptions = [];
foreach ($allCategories as $row) {
    if (($row['parent_id'] ?? null) !== null) {
        continue;
    }
    $rootCategoryOptions[] = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'slug' => (string) $row['slug'],
    ];
}
usort(
    $rootCategoryOptions,
    static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    }
);

/** @var array<int, list<array{id:int,name:string,slug:string}>> $childCategoryOptionsByRoot */
$childCategoryOptionsByRoot = [];
foreach ($allCategories as $row) {
    $pid = $row['parent_id'];
    if ($pid === null) {
        continue;
    }
    $parentId = (int) $pid;
    if (!isset($allCategoriesById[$parentId])) {
        continue;
    }
    if (($allCategoriesById[$parentId]['parent_id'] ?? null) !== null) {
        continue; // Yalniz root altini "alt kateqoriya" kimi gostermek ucun
    }
    $childCategoryOptionsByRoot[$parentId][] = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'slug' => (string) $row['slug'],
    ];
}
foreach ($childCategoryOptionsByRoot as $rootId => $items) {
    usort(
        $items,
        static fn(array $a, array $b): int => strcmp($a['name'], $b['name'])
    );
    $childCategoryOptionsByRoot[$rootId] = $items;
}

$selectedRootParentId = 0;
$selectedChildParentId = 0;
$selectedParentId = $editing ? (int) ($editing['parent_id'] ?? 0) : 0;
if ($selectedParentId > 0 && isset($allCategoriesById[$selectedParentId])) {
    $selectedParentRow = $allCategoriesById[$selectedParentId];
    $selectedParentParentId = $selectedParentRow['parent_id'] ?? null;
    if ($selectedParentParentId === null) {
        $selectedRootParentId = $selectedParentId;
    } else {
        $selectedRootParentId = (int) $selectedParentParentId;
        $selectedChildParentId = $selectedParentId;
    }
}

$categoriesRaw = $pdo->query(
    "SELECT c.id, c.parent_id, c.brand_id, c.name, c.slug, c.description, c.image_url, c.is_active, c.sort_order,
            p.name AS parent_name, b.name AS brand_name
     FROM categories c
     LEFT JOIN categories p ON p.id = c.parent_id
     LEFT JOIN brands b ON b.id = c.brand_id"
)->fetchAll() ?: [];

/** @var array<int, array<string, mixed>> $categoriesById */
$categoriesById = [];
foreach ($categoriesRaw as $row) {
    $cid = (int) ($row['id'] ?? 0);
    if ($cid > 0) {
        $categoriesById[$cid] = $row;
    }
}

/** @var list<array<string, mixed>> $categories */
$categories = [];
foreach ($categoryTreeOptions as $node) {
    $cid = (int) ($node['id'] ?? 0);
    if (!isset($categoriesById[$cid])) {
        continue;
    }
    $row = $categoriesById[$cid];
    $row['depth'] = (int) ($node['depth'] ?? 0);
    $categories[] = $row;
    unset($categoriesById[$cid]);
}
// Agacda yer almayan (yetim) kayıtlar varsa sonda yine göster.
foreach ($categoriesById as $row) {
    $row['depth'] = 0;
    $categories[] = $row;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kateqoriyalar</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('categories'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Kateqoriya idareetmesi</h1>
                <p class="admin-subtitle">Kateqoriya agacı, parent-uşaq əlaqəsi və brend yarpaq bağlantıları buradan idarə olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Kateqoriyani redakte et' : 'Yeni kateqoriya'; ?></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div>
                        <label>Ad</label>
                        <input id="category-name" name="name" required value="<?php echo $editing ? e((string) $editing['name']) : ''; ?>" placeholder="Meselen: Smartfonlar">
                    </div>
                    <div>
                        <label>Slug</label>
                        <input id="category-slug" name="slug" required value="<?php echo $editing ? e((string) $editing['slug']) : ''; ?>" placeholder="meselen-smartfonlar">
                    </div>
                    <div>
                        <label>Ust kateqoriya</label>
                        <select id="parent-root-select">
                            <option value="">Yoxdur (ana kateqoriya)</option>
                            <?php foreach ($rootCategoryOptions as $rootOpt): ?>
                                <?php if ($editing && (int) $rootOpt['id'] === (int) $editing['id']) {
                                    continue;
                                } ?>
                                <option value="<?php echo (int) $rootOpt['id']; ?>" <?php echo $selectedRootParentId === (int) $rootOpt['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($rootOpt['name'] . ' (' . $rootOpt['slug'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block; margin-top:6px; opacity:.75;">Ilk mərhələ parent seçimi.</small>
                    </div>
                    <div>
                        <label>Alt kateqoriya (opsional)</label>
                        <select id="parent-child-select">
                            <option value="">Yoxdur</option>
                            <?php foreach (($childCategoryOptionsByRoot[$selectedRootParentId] ?? []) as $childOpt): ?>
                                <?php if ($editing && (int) $childOpt['id'] === (int) $editing['id']) {
                                    continue;
                                } ?>
                                <option value="<?php echo (int) $childOpt['id']; ?>" <?php echo $selectedChildParentId === (int) $childOpt['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($childOpt['name'] . ' (' . $childOpt['slug'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block; margin-top:6px; opacity:.75;">Secilsə parent kimi bu istifadə olunur.</small>
                    </div>
                    <input type="hidden" name="parent_id" id="parent-id-hidden" value="<?php echo (int) ($selectedChildParentId > 0 ? $selectedChildParentId : $selectedRootParentId); ?>">
                    <div>
                        <label>Brend (yarpaq ucun)</label>
                        <select name="brand_id">
                            <option value="">Brend baglama</option>
                            <?php foreach ($allBrands as $brand): ?>
                                <?php
                                $bid = (int) $brand['id'];
                                $selectedBrandId = $editing ? (int) ($editing['brand_id'] ?? 0) : 0;
                                ?>
                                <option value="<?php echo $bid; ?>" <?php echo $selectedBrandId === $bid ? 'selected' : ''; ?>>
                                    <?php echo e((string) $brand['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block; margin-top:6px; opacity:.75;">Yalniz son (yarpaq) kateqoriyada secin.</small>
                    </div>
                    <div><label>Sort</label><input name="sort_order" type="number" value="<?php echo $editing ? (int) $editing['sort_order'] : 0; ?>"></div>
                    <div>
                        <label style="margin-bottom: 8px;">Aktiv</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo !$editing || (int) $editing['is_active'] === 1 ? 'checked' : ''; ?>>
                            Aktivdir
                        </label>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Tesvir</label>
                        <textarea name="description"><?php echo $editing ? e((string) ($editing['description'] ?? '')) : ''; ?></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Image URL (opsional)</label>
                        <input name="image_url" value="<?php echo $editing ? e((string) ($editing['image_url'] ?? '')) : ''; ?>" placeholder="uploads/categories/... veya https://...">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Yoxsa fayl yukle (JPG/PNG/WEBP)</label>
                        <input name="image" type="file" accept=".jpg,.jpeg,.png,.webp">
                        <?php if ($editing && !empty($editing['image_url'])): ?>
                            <div style="margin-top:10px;">
                                <img class="thumb" src="../<?php echo e((string) $editing['image_url']); ?>" alt="category image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_categories.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title">Excel/CSV ile toplu kateqoriya elavesi</h3>
            <p style="margin-top: 0; opacity: .8;">
                Desteklenen sutunlar: <code>name</code>, <code>slug</code> (mecburi),
                <code>parent_slug</code>, <code>brand_name</code>, <code>description</code>,
                <code>image_url</code>, <code>sort_order</code>, <code>is_active</code>.
                <br>XLSX importu ucun serverde <code>ZipArchive</code> aktiv olmalidir; eks halda CSV istifade edin.
            </p>
            <form method="post" enctype="multipart/form-data" class="stack-sm">
                <input type="hidden" name="action" value="import_excel">
                <input name="import_file" type="file" accept=".xlsx,.csv" required>
                <small style="display:block; opacity:.75;">
                    Header setiri mutleq olmalidir. <code>parent_slug</code> varsa evvelden movcud olmalidir.
                </small>
                <div>
                    <button class="btn btn-primary" type="submit">Excel/CSV import et</button>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                <h3 class="section-title" style="margin:0;">Kateqoriyalar</h3>
                <?php if (!empty($categories)): ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('Butun kateqoriyalar silinsin? Bu geri qaytarila bilmez. Mehsul varsa emeliyyat bloklanacaq.');">
                        <input type="hidden" name="action" value="delete_all">
                        <button class="btn btn-danger" type="submit">Hamisini sil</button>
                    </form>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Ad</th>
                    <th>Slug</th>
                    <th>Ust</th>
                    <th>Brend</th>
                    <th>Aktiv</th>
                    <th>Sort</th>
                    <th>Sekil</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="9">Kateqoriya yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $idx => $c): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <?php $depth = (int) ($c['depth'] ?? 0); ?>
                            <td>
                                <div style="padding-left: <?php echo $depth * 18; ?>px;">
                                    <?php if ($depth > 0): ?>
                                        <span style="opacity:.55; margin-right:6px;">↳</span>
                                    <?php endif; ?>
                                    <?php echo e((string) $c['name']); ?>
                                </div>
                                <small class="spec-help">
                                    DB ID: <?php echo (int) $c['id']; ?> · <?php echo $depth === 0 ? 'Ust kateqoriya' : 'Alt kateqoriya'; ?>
                                </small>
                            </td>
                            <td><code><?php echo e((string) $c['slug']); ?></code></td>
                            <td><?php echo e((string) ($c['parent_name'] ?? '-')); ?></td>
                            <td><?php echo e((string) ($c['brand_name'] ?? '-')); ?></td>
                            <td><?php echo (int) $c['is_active'] === 1 ? 'Aktiv' : 'Passiv'; ?></td>
                            <td><?php echo (int) $c['sort_order']; ?></td>
                            <td>
                                <?php if (!empty($c['image_url'])): ?>
                                    <img class="thumb" src="../<?php echo e((string) $c['image_url']); ?>" alt="category">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_categories.php?edit=<?php echo (int) $c['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu kateqoriya silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                        <button class="btn btn-danger" type="submit">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<script>
(() => {
    const nameInput = document.getElementById('category-name');
    const slugInput = document.getElementById('category-slug');
    if (!nameInput || !slugInput) return;

    // Redaktede manual slug qorunsun; yenide rahatliq ucun addan auto-generate et.
    let slugManuallyChanged = false;
    const initialSlug = slugInput.value.trim();
    if (initialSlug !== '') {
        slugManuallyChanged = true;
    }

    const toSlug = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');

    slugInput.addEventListener('input', () => {
        slugManuallyChanged = slugInput.value.trim() !== '';
    });

    nameInput.addEventListener('input', () => {
        if (slugManuallyChanged) return;
        slugInput.value = toSlug(nameInput.value);
    });
})();

(() => {
    const rootSelect = document.getElementById('parent-root-select');
    const childSelect = document.getElementById('parent-child-select');
    const parentHidden = document.getElementById('parent-id-hidden');
    if (!rootSelect || !childSelect || !parentHidden) return;

    const childMap = <?php echo json_encode($childCategoryOptionsByRoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const editingId = <?php echo json_encode($editing ? (int) $editing['id'] : 0); ?>;

    function syncParentHidden() {
        const childVal = childSelect.value.trim();
        const rootVal = rootSelect.value.trim();
        parentHidden.value = childVal !== '' ? childVal : rootVal;
    }

    function refillChildren() {
        const rootId = rootSelect.value;
        const previous = childSelect.value;
        childSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Yoxdur';
        childSelect.appendChild(empty);

        const items = childMap[rootId] || [];
        items.forEach((item) => {
            const id = String(item.id || '');
            if (!id || (editingId > 0 && Number(id) === Number(editingId))) {
                return;
            }
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = `${item.name || ''} (${item.slug || ''})`;
            if (previous !== '' && previous === id) {
                opt.selected = true;
            }
            childSelect.appendChild(opt);
        });
        syncParentHidden();
    }

    rootSelect.addEventListener('change', () => {
        refillChildren();
    });
    childSelect.addEventListener('change', syncParentHidden);
    syncParentHidden();
})();
</script>
</body>
</html>

