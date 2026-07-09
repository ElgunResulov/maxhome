<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();
$message = '';
$messageType = 'success';

$categories = $pdo->query(
    "SELECT id, parent_id, name
     FROM categories
     WHERE is_active = 1
     ORDER BY sort_order ASC, name ASC"
)->fetchAll() ?: [];
$primaryCategories = [];
$subcategoriesByParent = [];
foreach ($categories as $categoryRow) {
    $id = (int) ($categoryRow['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $parentId = $categoryRow['parent_id'] !== null ? (int) $categoryRow['parent_id'] : null;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'update_discount') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new RuntimeException('Mehsul secimi sehvdir.');
            }

            $newCompareRaw = trim((string) ($_POST['compare_at_price'] ?? ''));
            $newPercentRaw = trim((string) ($_POST['discount_percent'] ?? ''));
            $newCompare = null;

            $productStmt = $pdo->prepare(
                "SELECT id, name, base_price, compare_at_price
                 FROM products
                 WHERE id = :id
                 LIMIT 1"
            );
            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();
            if (!$product) {
                throw new RuntimeException('Mehsul tapilmadi.');
            }

            $basePrice = (float) ($product['base_price'] ?? 0);
            if ($basePrice <= 0) {
                throw new RuntimeException('Bu mehsul ucun cari qiymet duzgun deyil.');
            }

            if ($newPercentRaw !== '') {
                if (!is_numeric($newPercentRaw)) {
                    throw new RuntimeException('Endirim faizi say olmalidir.');
                }
                $discountPercent = round((float) $newPercentRaw, 2);
                if ($discountPercent <= 0 || $discountPercent >= 100) {
                    throw new RuntimeException('Endirim faizi 0 ile 100 arasinda olmalidir.');
                }
                $newBasePrice = round($basePrice * (1 - ($discountPercent / 100)), 2);
                if ($newBasePrice <= 0) {
                    throw new RuntimeException('Endirimden sonra qiymet 0-dan boyuk olmalidir.');
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE products
                     SET base_price = :base_price,
                         compare_at_price = :compare_at_price,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    'id' => $productId,
                    'base_price' => $newBasePrice,
                    'compare_at_price' => $basePrice,
                ]);

                $message = 'Endirim tetbiq olundu: ' . (string) $product['name']
                    . ' (' . rtrim(rtrim((string) $discountPercent, '0'), '.') . '%) '
                    . number_format($basePrice, 2) . ' ₼ -> ' . number_format($newBasePrice, 2) . ' ₼';
            } elseif ($newCompareRaw !== '') {
                if (!is_numeric($newCompareRaw)) {
                    throw new RuntimeException('Endirimden evvelki qiymet say olmalidir.');
                }
                $newCompare = round((float) $newCompareRaw, 2);
                if ($newCompare <= 0) {
                    throw new RuntimeException('Endirimden evvelki qiymet 0-dan boyuk olmalidir.');
                }
                if ($newCompare <= $basePrice) {
                    throw new RuntimeException('Compare qiymeti cari qiymetden boyuk olmalidir.');
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE products
                     SET compare_at_price = :compare_at_price,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    'id' => $productId,
                    'compare_at_price' => $newCompare,
                ]);

                $percent = (float) round((1 - ($basePrice / $newCompare)) * 100, 2);
                $message = 'Endirim yenilendi: ' . (string) $product['name'] . ' (' . rtrim(rtrim((string) $percent, '0'), '.') . '%)';
            } else {
                $message = 'Deyisiklik edilmedi: ' . (string) $product['name'] . '. Endirimi legv etmek ucun "Temizle" duymesinden istifade edin.';
            }
        } elseif ($action === 'bulk_discount') {
            $bulkPercentRaw = trim((string) ($_POST['bulk_discount_percent'] ?? ''));
            $selectedIdsRaw = trim((string) ($_POST['selected_product_ids'] ?? ''));

            if ($bulkPercentRaw === '' || !is_numeric($bulkPercentRaw)) {
                throw new RuntimeException('Toplu endirim faizi duzgun daxil edilmelidir.');
            }
            $bulkPercent = round((float) $bulkPercentRaw, 2);
            if ($bulkPercent <= 0 || $bulkPercent >= 100) {
                throw new RuntimeException('Toplu endirim faizi 0 ile 100 arasinda olmalidir.');
            }

            $selectedIds = [];
            foreach (preg_split('/[,\s]+/', $selectedIdsRaw) ?: [] as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $selectedIds[$id] = $id;
                }
            }
            $selectedIds = array_values($selectedIds);
            if (empty($selectedIds)) {
                throw new RuntimeException('Toplu emeliyyat ucun en azi bir mehsul secin.');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $bulkFetchStmt = $pdo->prepare(
                "SELECT id, base_price
                 FROM products
                 WHERE id IN ({$placeholders})"
            );
            $bulkFetchStmt->execute($selectedIds);
            $rows = $bulkFetchStmt->fetchAll() ?: [];

            if (empty($rows)) {
                throw new RuntimeException('Secilen mehsullar tapilmadi.');
            }

            $bulkUpdateStmt = $pdo->prepare(
                "UPDATE products
                 SET base_price = :base_price,
                     compare_at_price = :compare_at_price,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $updatedCount = 0;
            foreach ($rows as $row) {
                $basePrice = (float) ($row['base_price'] ?? 0);
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || $basePrice <= 0) {
                    continue;
                }
                $newBasePrice = round($basePrice * (1 - ($bulkPercent / 100)), 2);
                if ($newBasePrice <= 0) {
                    continue;
                }
                $bulkUpdateStmt->execute([
                    'base_price' => $newBasePrice,
                    'compare_at_price' => $basePrice,
                    'id' => $id,
                ]);
                $updatedCount++;
            }

            if ($updatedCount === 0) {
                throw new RuntimeException('Secilen mehsullarda yenilenecek duzgun qiymet tapilmadi.');
            }

            $message = $updatedCount . ' mehsula toplu olaraq ' . rtrim(rtrim((string) $bulkPercent, '0'), '.') . '% endirim tetbiq edildi.';
        } elseif ($action === 'bulk_clear_selected') {
            $selectedIdsRaw = trim((string) ($_POST['selected_product_ids'] ?? ''));
            $selectedIds = [];
            foreach (preg_split('/[,\s]+/', $selectedIdsRaw) ?: [] as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $selectedIds[$id] = $id;
                }
            }
            $selectedIds = array_values($selectedIds);
            if (empty($selectedIds)) {
                throw new RuntimeException('Secilenleri temizlemek ucun en azi bir mehsul secin.');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $fetchStmt = $pdo->prepare(
                "SELECT id, base_price, compare_at_price
                 FROM products
                 WHERE id IN ({$placeholders})"
            );
            $fetchStmt->execute($selectedIds);
            $rows = $fetchStmt->fetchAll() ?: [];
            if (empty($rows)) {
                throw new RuntimeException('Secilen mehsullar tapilmadi.');
            }

            $restoreStmt = $pdo->prepare(
                "UPDATE products
                 SET base_price = :base_price,
                     compare_at_price = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $clearCompareOnlyStmt = $pdo->prepare(
                "UPDATE products
                 SET compare_at_price = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $updatedCount = 0;
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $basePrice = (float) ($row['base_price'] ?? 0);
                $oldCompare = $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null;
                if ($id <= 0 || $oldCompare === null) {
                    continue;
                }

                if ($oldCompare > $basePrice) {
                    $restoreStmt->execute([
                        'id' => $id,
                        'base_price' => $oldCompare,
                    ]);
                } else {
                    $clearCompareOnlyStmt->execute(['id' => $id]);
                }
                $updatedCount++;
            }

            if ($updatedCount === 0) {
                throw new RuntimeException('Secilen mehsullarda temizlenecek endirim tapilmadi.');
            }

            $message = 'Secilen mehsullarda endirim temizlendi. Yenilenen mehsul sayi: ' . $updatedCount . '.';
        } elseif ($action === 'clear_discount') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new RuntimeException('Mehsul secimi sehvdir.');
            }

            $productStmt = $pdo->prepare(
                "SELECT id, name, base_price, compare_at_price
                 FROM products
                 WHERE id = :id
                 LIMIT 1"
            );
            $productStmt->execute(['id' => $productId]);
            $product = $productStmt->fetch();
            if (!$product) {
                throw new RuntimeException('Mehsul tapilmadi.');
            }

            $basePrice = (float) ($product['base_price'] ?? 0);
            $oldCompare = $product['compare_at_price'] !== null ? (float) $product['compare_at_price'] : null;

            if ($oldCompare !== null && $oldCompare > $basePrice) {
                $updateStmt = $pdo->prepare(
                    "UPDATE products
                     SET base_price = :base_price,
                         compare_at_price = NULL,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    'id' => $productId,
                    'base_price' => $oldCompare,
                ]);
                $message = 'Endirim temizlendi: ' . (string) $product['name'] . ' qiymeti evvelki deyere qaytarildi.';
            } else {
                $updateStmt = $pdo->prepare(
                    "UPDATE products
                     SET compare_at_price = NULL,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );
                $updateStmt->execute(['id' => $productId]);
                $message = 'Endirim temizlendi: ' . (string) $product['name'];
            }
        } elseif ($action === 'clear_all_discounts') {
            $rows = $pdo->query(
                "SELECT id, base_price, compare_at_price
                 FROM products
                 WHERE compare_at_price IS NOT NULL"
            )->fetchAll() ?: [];

            if (empty($rows)) {
                throw new RuntimeException('Temizlenecek aktiv endirim tapilmadi.');
            }

            $restoreStmt = $pdo->prepare(
                "UPDATE products
                 SET base_price = :base_price,
                     compare_at_price = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $clearCompareOnlyStmt = $pdo->prepare(
                "UPDATE products
                 SET compare_at_price = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $updatedCount = 0;
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $basePrice = (float) ($row['base_price'] ?? 0);
                $oldCompare = $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null;
                if ($id <= 0 || $oldCompare === null) {
                    continue;
                }

                if ($oldCompare > $basePrice) {
                    $restoreStmt->execute([
                        'id' => $id,
                        'base_price' => $oldCompare,
                    ]);
                } else {
                    $clearCompareOnlyStmt->execute(['id' => $id]);
                }
                $updatedCount++;
            }

            if ($updatedCount === 0) {
                throw new RuntimeException('Temizlenecek aktiv endirim tapilmadi.');
            }

            $message = 'Butun endirimler temizlendi. Yenilenen mehsul sayi: ' . $updatedCount . '.';
        } else {
            throw new RuntimeException('Emeliyyat taninmadi.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);
$subcategoryId = (int) ($_GET['subcategory_id'] ?? 0);
$effectiveCategoryId = $subcategoryId > 0 ? $subcategoryId : $categoryId;

$sql = "SELECT p.id, p.name, p.sku, p.status, p.base_price, p.compare_at_price, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id";
$params = [];
$whereParts = [];
if ($q !== '') {
    $whereParts[] = "(p.name LIKE :q OR p.sku LIKE :q)";
    $params['q'] = '%' . $q . '%';
}
if ($effectiveCategoryId > 0) {
    if ($subcategoryId > 0) {
        $whereParts[] = "p.category_id = :subcategory_id";
        $params['subcategory_id'] = $subcategoryId;
    } else {
        $whereParts[] = "(p.category_id = :category_id OR p.category_id IN (
            SELECT sc.id FROM categories sc WHERE sc.parent_id = :category_id_sub
        ))";
        $params['category_id'] = $categoryId;
        $params['category_id_sub'] = $categoryId;
    }
}
if (!empty($whereParts)) {
    $sql .= " WHERE " . implode(' AND ', $whereParts);
}
$sql .= " ORDER BY id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Endirimler</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('discounts'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Endirim paneli</h1>
                <p class="admin-subtitle">Endirimi faizle ve ya compare qiymetle idare edin. Endirimi legv etmek ucun her setirde "Temizle" duymesine basin.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <form method="get" id="discount-filter-form" style="display:flex; gap:10px; margin-bottom: 12px;">
                <input name="q" value="<?php echo e($q); ?>" placeholder="Mehsul adina ve ya SKU-a gore axtar...">
                <select name="category_id" id="discount-category-id">
                    <option value="">Kateqoriya secin</option>
                    <?php foreach ($primaryCategories as $category): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $category['id'] === $categoryId ? 'selected' : ''; ?>>
                            <?php echo e((string) $category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="subcategory_id" id="discount-subcategory-id">
                    <option value="">Alt kateqoriya secin</option>
                    <?php foreach (($subcategoriesByParent[$categoryId] ?? []) as $subCategory): ?>
                        <option value="<?php echo (int) $subCategory['id']; ?>" <?php echo (int) $subCategory['id'] === $subcategoryId ? 'selected' : ''; ?>>
                            <?php echo e((string) $subCategory['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-muted" type="submit">Axtar</button>
                <button class="btn btn-muted" type="button" id="discount-filter-clear">Filterleri temizle</button>
            </form>
            <form method="post" style="margin-bottom:12px;" onsubmit="return confirm('Butun endirimler temizlensin? Bu emeliyyat geri qaytarilmayacaq.');">
                <input type="hidden" name="action" value="clear_all_discounts">
                <button class="btn btn-danger" type="submit">Butun endirimleri temizle</button>
            </form>
            <form method="post" id="bulk-discount-form" style="display:flex; gap:10px; align-items:center; margin-bottom: 12px; flex-wrap: wrap;">
                <input type="hidden" name="action" value="bulk_discount">
                <input type="hidden" name="selected_product_ids" id="selected-product-ids" value="">
                <label for="bulk_discount_percent">Toplu faiz:</label>
                <input id="bulk_discount_percent" name="bulk_discount_percent" type="number" step="0.01" min="0.01" max="99.99" placeholder="Mes: 15" style="max-width:120px;">
                <button class="btn btn-primary" type="submit">Secilenlere tetbiq et</button>
                <button class="btn btn-danger" type="submit" formaction="admin_discounts.php" formmethod="post" name="action" value="bulk_clear_selected">Secilenleri temizle</button>
                <span class="spec-help">Evvelce soldan mehsullari secin.</span>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-products" title="Hamısını seç"></th>
                        <th>№</th>
                        <th>Mehsul</th>
                        <th>Status</th>
                        <th>Cari qiymet</th>
                        <th>Compare qiymet</th>
                        <th>Faiz (%)</th>
                        <th>Endirim</th>
                        <th>Emeliyyat</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="9">Mehsul tapilmadi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $idx => $product): ?>
                            <?php
                            $basePrice = (float) ($product['base_price'] ?? 0);
                            $compareAtPrice = $product['compare_at_price'] !== null ? (float) $product['compare_at_price'] : null;
                            $discountPercent = 0.0;
                            if ($compareAtPrice !== null && $compareAtPrice > $basePrice && $compareAtPrice > 0) {
                                $discountPercent = round((1 - ($basePrice / $compareAtPrice)) * 100, 2);
                            }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="bulk-product-checkbox" value="<?php echo (int) $product['id']; ?>">
                                </td>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <?php echo e((string) $product['name']); ?>
                                    <br>
                                    <small><?php echo e((string) $product['sku']); ?></small>
                                    <br>
                                    <small class="spec-help">Kateqoriya: <?php echo e((string) ($product['category_name'] ?? '-')); ?></small>
                                    <br>
                                    <small class="spec-help">DB ID: <?php echo (int) $product['id']; ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo e((string) $product['status']); ?>">
                                        <?php echo e((string) $product['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($basePrice, 2); ?> ₼</td>
                                <td>
                                    <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <input type="hidden" name="action" value="update_discount">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <input
                                            name="compare_at_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="Compare qiymet"
                                            value="<?php echo $compareAtPrice !== null ? e(number_format($compareAtPrice, 2, '.', '')) : ''; ?>"
                                            style="max-width: 150px;"
                                        >
                                </td>
                                <td>
                                        <input
                                            name="discount_percent"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="99.99"
                                            placeholder="Mes: 20"
                                            value="<?php echo $discountPercent > 0 ? e(rtrim(rtrim((string) $discountPercent, '0'), '.')) : ''; ?>"
                                            style="max-width: 110px;"
                                        >
                                </td>
                                <td>
                                    <?php if ($discountPercent > 0): ?>
                                        <strong><?php echo rtrim(rtrim((string) $discountPercent, '0'), '.'); ?>%</strong>
                                    <?php else: ?>
                                        <span class="spec-help">Yoxdur</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                        <button class="btn btn-primary" type="submit">Yadda saxla</button>
                                    </form>
                                    <form method="post" style="margin-top:6px;">
                                        <input type="hidden" name="action" value="clear_discount">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <button class="btn btn-muted" type="submit">Endirimi temizle</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script>
(() => {
    const selectionStorageKey = 'admin_discounts_selected_ids';
    const categorySelect = document.getElementById('discount-category-id');
    const subcategorySelect = document.getElementById('discount-subcategory-id');
    const filterForm = document.getElementById('discount-filter-form');
    const filterClearBtn = document.getElementById('discount-filter-clear');
    const subcategoriesByParent = <?php
        echo json_encode(
            $subcategoriesByParent,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?> || {};

    const checkboxes = Array.from(document.querySelectorAll('.bulk-product-checkbox'));
    const selectAll = document.getElementById('select-all-products');
    const hiddenIds = document.getElementById('selected-product-ids');
    const bulkForm = document.getElementById('bulk-discount-form');

    function refreshSubcategories() {
        if (!categorySelect || !subcategorySelect) return;
        const parentId = categorySelect.value;
        const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
        const selectedValue = subcategorySelect.value;
        subcategorySelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Alt kateqoriya secin';
        subcategorySelect.appendChild(placeholder);

        options.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = String(item.name);
            if (String(item.id) === String(selectedValue)) {
                option.selected = true;
            }
            subcategorySelect.appendChild(option);
        });

        subcategorySelect.disabled = options.length === 0;
        if (options.length === 0) {
            subcategorySelect.value = '';
        }
    }

    function readStoredSelectedIds() {
        try {
            const raw = window.localStorage.getItem(selectionStorageKey) || '';
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map((v) => String(v)) : [];
        } catch (e) {
            return [];
        }
    }

    function writeStoredSelectedIds(ids) {
        try {
            window.localStorage.setItem(selectionStorageKey, JSON.stringify(ids));
        } catch (e) {
            // ignore storage errors
        }
    }

    function syncSelectedIds() {
        if (!hiddenIds) return;
        const ids = checkboxes.filter((cb) => cb.checked).map((cb) => cb.value);
        hiddenIds.value = ids.join(',');
        writeStoredSelectedIds(ids);
    }

    function restoreCheckboxSelection() {
        const storedIds = readStoredSelectedIds();
        if (!storedIds.length) return;
        const idSet = new Set(storedIds);
        checkboxes.forEach((cb) => {
            cb.checked = idSet.has(String(cb.value));
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            refreshSubcategories();
        });
        refreshSubcategories();
    }

    if (filterClearBtn && filterForm) {
        filterClearBtn.addEventListener('click', () => {
            const qInput = filterForm.querySelector('input[name="q"]');
            if (qInput) qInput.value = '';
            if (categorySelect) categorySelect.value = '';
            refreshSubcategories();
            if (subcategorySelect) subcategorySelect.value = '';
            syncSelectedIds();
            filterForm.submit();
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            syncSelectedIds();
        });
    }

    checkboxes.forEach((cb) => {
        cb.addEventListener('change', () => {
            const allChecked = checkboxes.length > 0 && checkboxes.every((x) => x.checked);
            if (selectAll) {
                selectAll.checked = allChecked;
            }
            syncSelectedIds();
        });
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', (e) => {
            const submitter = e.submitter;
            const submitAction = submitter && submitter.value ? String(submitter.value) : '';
            if (submitAction === 'bulk_clear_selected') {
                try {
                    window.localStorage.removeItem(selectionStorageKey);
                } catch (err) {
                    // ignore storage errors
                }
                checkboxes.forEach((cb) => {
                    cb.checked = false;
                });
                if (selectAll) {
                    selectAll.checked = false;
                }
            }
            syncSelectedIds();
            if (!hiddenIds || hiddenIds.value.trim() === '') {
                e.preventDefault();
                window.alert('Bu emeliyyat ucun en azi bir mehsul secin.');
            }
        });
    }

    restoreCheckboxSelection();
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && checkboxes.every((x) => x.checked);
    }
    syncSelectedIds();
})();
</script>
</body>
</html>
