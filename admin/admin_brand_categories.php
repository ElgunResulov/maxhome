<?php

declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/category_brands.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';
$tableReady = maxhomeCategoryBrandsTableExists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'save_relations') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            if ($categoryId > 0) {
                $parentCheck = $pdo->prepare('SELECT parent_id FROM categories WHERE id = :id LIMIT 1');
                $parentCheck->execute(['id' => $categoryId]);
                $parentId = $parentCheck->fetchColumn();
                if ($parentId !== false && $parentId !== null) {
                    throw new RuntimeException('Yalniz esas kateqoriyalar secile bilər.');
                }
            }
            $brandIds = isset($_POST['brand_ids']) && is_array($_POST['brand_ids'])
                ? array_map('intval', $_POST['brand_ids'])
                : [];
            maxhomeSaveCategoryBrandRelations($pdo, $categoryId, $brandIds);
            $message = 'Kateqoriya–brend elaqeleri saxlanildi.';
        } elseif ($action === 'delete_relation') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $brandId = (int) ($_POST['brand_id'] ?? 0);
            if ($categoryId <= 0 || $brandId <= 0) {
                throw new RuntimeException('Silmek ucun melumatlar sehvdir.');
            }
            $pdo->prepare(
                'DELETE FROM category_brands WHERE category_id = :cid AND brand_id = :bid'
            )->execute(['cid' => $categoryId, 'bid' => $brandId]);
            $message = 'Elaqe silindi.';
        } elseif ($action === 'sync_legacy') {
            $count = maxhomeSyncCategoryBrandsFromLegacyLeaves($pdo);
            $message = 'Kohne categories.brand_id yarpaqlarindan ' . $count . ' elaqe elave edildi (artiq olanlar kecildi).';
        } else {
            throw new RuntimeException('Bilinmeyen emeliyyat.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tableReady) {
    $message = 'category_brands cedveli yoxdur. database/migration_category_brands.sql faylini isledin.';
    $messageType = 'error';
}

$allCategories = $pdo->query(
    'SELECT id, parent_id, name, slug, sort_order FROM categories ORDER BY sort_order ASC, name ASC'
)->fetchAll() ?: [];
/** @var list<array{id:int,name:string,slug:string}> $categoryOptions */
$categoryOptions = [];
foreach ($allCategories as $row) {
    if (($row['parent_id'] ?? null) !== null) {
        continue;
    }
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $categoryOptions[] = [
        'id' => $id,
        'name' => (string) ($row['name'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
    ];
}
$allBrands = $pdo->query('SELECT id, name, slug FROM brands ORDER BY name ASC')->fetchAll() ?: [];
$relations = maxhomeCategoryBrandRelationsList($pdo);

$editCategoryId = (int) ($_GET['category'] ?? 0);
$selectedBrandIds = $editCategoryId > 0 ? maxhomeCategoryBrandIdsForCategory($pdo, $editCategoryId) : [];
$selectedBrandIdSet = array_fill_keys($selectedBrandIds, true);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kateqoriya / Brend elaqeleri</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .brand-check-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 8px 12px;
            margin-top: 10px;
            max-height: 320px;
            overflow: auto;
            padding: 10px;
            border: 1px solid var(--admin-border, #e2e8f0);
            border-radius: 8px;
            background: #fafbfc;
        }
        .brand-check-grid label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('brand_categories'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Kateqoriya / Brend elaqeleri</h1>
                <p class="admin-subtitle">
                    Magaza filtrində «Marka» siyahısı seçilmiş kateqoriya (əsas, alt və alt-alt) üzrə buradan idarə olunur.
                </p>
            </div>
        </header>

        <?php if (!$tableReady): ?>
            <div class="flash flash-error">
                <strong>Migration lazımdır.</strong>
                phpMyAdmin və ya MySQL ilə
                <code>database/migration_category_brands.sql</code>
                faylını işlədin, sonra səhifəni yeniləyin.
            </div>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title">Kateqoriyaya brend bağla</h3>
            <p class="spec-help" style="margin-bottom: 12px;">
                Yalnız əsas kateqoriyalar seçilə bilər. Seçilmiş əsas kateqoriya və onun alt kateqoriyalarında
                mağazada yalnız işarələnmiş brendlər görünəcək.
            </p>
            <form method="post" id="relation-form">
                <input type="hidden" name="action" value="save_relations">
                <div class="form-grid">
                    <div>
                        <label for="category_id">Kateqoriya</label>
                        <select name="category_id" id="category_id" required <?php echo $tableReady ? '' : 'disabled'; ?>>
                            <option value="">— Kateqoriya seçin —</option>
                            <?php foreach ($categoryOptions as $opt): ?>
                                <?php $cid = (int) $opt['id']; ?>
                                <option value="<?php echo $cid; ?>" <?php echo $editCategoryId === $cid ? 'selected' : ''; ?>>
                                    <?php echo e((string) $opt['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label style="display:block; margin-top:14px; font-weight:600;">Markalar</label>
                <?php if (empty($allBrands)): ?>
                    <p class="spec-help">Əvvəlcə <a href="admin_brands.php">Brendler</a> bölməsindən brend əlavə edin.</p>
                <?php else: ?>
                    <div class="brand-check-grid">
                        <?php foreach ($allBrands as $b): ?>
                            <?php $bid = (int) $b['id']; ?>
                            <label>
                                <input type="checkbox" name="brand_ids[]" value="<?php echo $bid; ?>"
                                    <?php echo isset($selectedBrandIdSet[$bid]) ? 'checked' : ''; ?>
                                    <?php echo $tableReady ? '' : 'disabled'; ?>>
                                <?php echo e((string) $b['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="stack-sm" style="margin-top: 14px;">
                    <button class="btn btn-primary" type="submit" <?php echo $tableReady && !empty($allBrands) ? '' : 'disabled'; ?>>
                        Saxla
                    </button>
                    <?php if ($editCategoryId > 0): ?>
                        <a class="btn btn-muted" href="admin_brand_categories.php">Yeni secim</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <?php if ($tableReady): ?>
            <section class="card" style="margin-bottom: 14px;">
                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
                    <h3 class="section-title" style="margin:0;">Köhnə yarpaqlardan köçür</h3>
                    <form method="post" class="inline-form" onsubmit="return confirm('categories.brand_id olan yarpaqlar category_brands cedveline elave olunsun?');">
                        <input type="hidden" name="action" value="sync_legacy">
                        <button class="btn btn-muted" type="submit">Kohne brend yarpaqlarini import et</button>
                    </form>
                </div>
                <p class="spec-help" style="margin-top:8px;">
                    Mega menyu üçün əvvəl yaradılmış brend yarpaqlarını (categories.brand_id) bir dəfəlik bu cədvələ köçürür.
                </p>
            </section>
        <?php endif; ?>

        <section class="card table-wrap">
            <h3 class="section-title">Mövcud əlaqələr (<?php echo count($relations); ?>)</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Kateqoriya</th>
                    <th>Marka</th>
                    <th>Əməliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$tableReady): ?>
                    <tr><td colspan="4">Cədvəl hazır deyil.</td></tr>
                <?php elseif (empty($relations)): ?>
                    <tr><td colspan="4">Hələ əlaqə yoxdur. Yuxarıdan kateqoriya seçib brendləri işarələyin.</td></tr>
                <?php else: ?>
                    <?php foreach ($relations as $idx => $rel): ?>
                        <?php
                        $depth = (int) ($rel['depth'] ?? 0);
                        $pad = str_repeat('→ ', $depth);
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <?php echo e($pad . (string) ($rel['category_name'] ?? '')); ?>
                                <br><small class="spec-help"><code><?php echo e((string) ($rel['category_slug'] ?? '')); ?></code></small>
                            </td>
                            <td><?php echo e((string) ($rel['brand_name'] ?? '')); ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_brand_categories.php?category=<?php echo (int) ($rel['category_id'] ?? 0); ?>">Düzəlt</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu elaqe silinsin?');">
                                        <input type="hidden" name="action" value="delete_relation">
                                        <input type="hidden" name="category_id" value="<?php echo (int) ($rel['category_id'] ?? 0); ?>">
                                        <input type="hidden" name="brand_id" value="<?php echo (int) ($rel['brand_id'] ?? 0); ?>">
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
(function () {
    var catSelect = document.getElementById('category_id');
    if (!catSelect) return;
    catSelect.addEventListener('change', function () {
        var id = catSelect.value;
        if (id) {
            window.location.href = 'admin_brand_categories.php?category=' + encodeURIComponent(id);
        }
    });
})();
</script>
</body>
</html>
