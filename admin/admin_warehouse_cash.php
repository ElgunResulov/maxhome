<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../user_auth.php';
require_once __DIR__ . '/admin_layout.php';

$isStorekeeper = isUserLoggedIn() && userHasAnyRole(['storekeeper']);
$isAdmin = isAdminLoggedIn();

if (!$isAdmin && !$isStorekeeper) {
    if (!isUserLoggedIn() && !isAdminLoggedIn()) {
        header('Location: ../user_login.php?redirect=' . urlencode('admin/admin_warehouse_cash.php'));
        exit;
    }
    http_response_code(403);
    echo 'Bu səhifəyə giriş üçün storekeeper və ya admin icazəsi tələb olunur.';
    exit;
}
$pdo = db();

$message = '';
$messageType = 'success';

function makeSlug(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $map = [
        'ə' => 'e', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c', 'ı' => 'i',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'mehsul';
}

$categories = $pdo->query(
    "SELECT id, parent_id, name
     FROM categories
     WHERE is_active = 1
     ORDER BY sort_order ASC, id ASC"
)->fetchAll() ?: [];

/**
 * @param array<string, list<array{id:int,name:string}>> $subcategoriesByParent
 * @return list<int>
 */
function warehouseCategorySubtreeIds(int $rootId, array $subcategoriesByParent): array
{
    $out = [$rootId];
    foreach ($subcategoriesByParent[(string) $rootId] ?? [] as $child) {
        $cid = (int) $child['id'];
        $out = array_merge($out, warehouseCategorySubtreeIds($cid, $subcategoriesByParent));
    }

    return $out;
}

/**
 * @return array{parent:int,sub:int,third:int}
 */
function warehouseResolveCategoryPicker(int $leafCategoryId, PDO $pdo): array
{
    $out = ['parent' => 0, 'sub' => 0, 'third' => 0];
    if ($leafCategoryId <= 0) {
        return $out;
    }

    $stmt = $pdo->prepare(
        "SELECT id, parent_id FROM categories WHERE id = :id LIMIT 1"
    );
    $stmt->execute(['id' => $leafCategoryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return $out;
    }

    $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    if ($parentId === null || $parentId <= 0) {
        $out['parent'] = (int) $row['id'];

        return $out;
    }

    $parentStmt = $pdo->prepare(
        "SELECT id, parent_id FROM categories WHERE id = :id LIMIT 1"
    );
    $parentStmt->execute(['id' => $parentId]);
    $parentRow = $parentStmt->fetch() ?: null;
    $grandParentId = $parentRow && $parentRow['parent_id'] !== null
        ? (int) $parentRow['parent_id']
        : null;

    if ($grandParentId !== null && $grandParentId > 0) {
        $out['parent'] = $grandParentId;
        $out['sub'] = $parentId;
        $out['third'] = (int) $row['id'];
    } else {
        $out['parent'] = $parentId;
        $out['sub'] = (int) $row['id'];
    }

    return $out;
}

/**
 * @throws RuntimeException
 */
function warehouseResolveFinalCategoryId(
    PDO $pdo,
    int $parentCategoryId,
    int $subcategoryId,
    int $thirdCategoryId
): int {
    if ($parentCategoryId <= 0) {
        throw new RuntimeException('Kateqoriya mutleqdir.');
    }

    $parentStmt = $pdo->prepare(
        "SELECT id
         FROM categories
         WHERE id = :id
           AND parent_id IS NULL
           AND is_active = 1
         LIMIT 1"
    );
    $parentStmt->execute(['id' => $parentCategoryId]);
    if (!$parentStmt->fetch()) {
        throw new RuntimeException('Secilen kateqoriya tapilmadi.');
    }

    $categoryId = $parentCategoryId;
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
            throw new RuntimeException('Alt kateqoriya secimi duzgun deyil.');
        }
        $categoryId = $subcategoryId;
    }

    if ($thirdCategoryId > 0) {
        if ($subcategoryId <= 0) {
            throw new RuntimeException('Alt-alt kateqoriya ucun once alt kateqoriya secin.');
        }
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
            throw new RuntimeException('Alt-alt kateqoriya secimi duzgun deyil.');
        }
        $categoryId = $thirdCategoryId;
    }

    return $categoryId;
}

/**
 * @return array<string, string>
 */
function warehouseRedirectParamsFromRequest(): array
{
    $redirectParams = [];
    foreach (
        [
            'warehouse_q',
            'warehouse_cat',
            'warehouse_sub',
            'warehouse_third',
            'warehouse_page',
        ] as $key
    ) {
        if (isset($_POST[$key]) && (string) $_POST[$key] !== '') {
            $redirectParams[$key] = (string) $_POST[$key];
        }
    }

    return $redirectParams;
}

$topCategories = [];
$subcategoriesByParent = [];
foreach ($categories as $categoryRow) {
    $id = (int) $categoryRow['id'];
    $parentId = $categoryRow['parent_id'] !== null ? (int) $categoryRow['parent_id'] : null;
    $item = [
        'id' => $id,
        'name' => (string) $categoryRow['name'],
    ];
    if ($parentId === null) {
        $topCategories[] = $item;
        continue;
    }
    $subcategoriesByParent[(string) $parentId][] = $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'quick_add_product') {
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $barcode = trim((string) ($_POST['barcode'] ?? ''));
        $salePrice = (float) ($_POST['sale_price'] ?? 0);
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $stockQty = (int) ($_POST['stock_qty'] ?? 0);
        $parentCategoryId = (int) ($_POST['category_id'] ?? 0);
        $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
        $thirdCategoryId = (int) ($_POST['third_category_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'active');

        if ($name === '' || $barcode === '' || $parentCategoryId <= 0) {
            throw new RuntimeException('Mehsul adi, barkod ve kateqoriya mutleqdir.');
        }
        if ($salePrice < 0 || $costPrice < 0 || $stockQty < 0) {
            throw new RuntimeException('Qiymet ve stok menfi ola bilmez.');
        }
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'active';
        }

        $categoryId = warehouseResolveFinalCategoryId($pdo, $parentCategoryId, $subcategoryId, $thirdCategoryId);

        $slugBase = makeSlug($name);
        if ($slugBase === '' || $slugBase === 'mehsul') {
            $slugBase = 'p-' . bin2hex(random_bytes(8));
        }
        $slug = $slugBase;
        $slugTry = 1;
        $slugCheckStmt = $pdo->prepare("SELECT id FROM products WHERE slug = :slug LIMIT 1");
        while (true) {
            $slugCheckStmt->execute(['slug' => $slug]);
            if (!$slugCheckStmt->fetch()) {
                break;
            }
            $slugTry++;
            $slug = $slugBase . '-' . $slugTry;
        }

        $skuCheckStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
        $skuCheckStmt->execute(['sku' => $barcode]);
        if ($skuCheckStmt->fetch()) {
            throw new RuntimeException('Bu barkod artiq movcuddur.');
        }

        if (maxhomeProductsOnlineVisibleColumnExists($pdo)) {
            $insertProduct = $pdo->prepare(
                "INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, stock_qty, status, online_visible)
                 VALUES (:category_id, NULL, :name, :slug, NULL, NULL, :sku, :base_price, :stock_qty, :status, 0)"
            );
        } else {
            $insertProduct = $pdo->prepare(
                "INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, stock_qty, status)
                 VALUES (:category_id, NULL, :name, :slug, NULL, NULL, :sku, :base_price, :stock_qty, :status)"
            );
        }
        $insertProduct->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'sku' => $barcode,
            'base_price' => $salePrice,
            'stock_qty' => $stockQty,
            'status' => $status,
        ]);
        $newProductId = (int) $pdo->lastInsertId();

        $upsertCostSpec = $pdo->prepare(
            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
             VALUES (:product_id, 'maya_deyeri', :spec_value, 0)"
        );
        $upsertCostSpec->execute([
            'product_id' => $newProductId,
            'spec_value' => number_format($costPrice, 2, '.', ''),
        ]);

        $message = 'Mehsul ugurla elave edildi.';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'edit_warehouse_product') {
    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $barcode = trim((string) ($_POST['barcode'] ?? ''));
        $salePrice = (float) ($_POST['sale_price'] ?? 0);
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $stockQty = (int) ($_POST['stock_qty'] ?? 0);
        $parentCategoryId = (int) ($_POST['category_id'] ?? 0);
        $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
        $thirdCategoryId = (int) ($_POST['third_category_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'active');

        if ($productId <= 0 || $name === '' || $barcode === '' || $parentCategoryId <= 0) {
            throw new RuntimeException('Mehsul, ad, barkod ve kateqoriya mutleqdir.');
        }
        if ($salePrice < 0 || $costPrice < 0 || $stockQty < 0) {
            throw new RuntimeException('Qiymet ve stok menfi ola bilmez.');
        }
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'active';
        }

        $existsStmt = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $existsStmt->execute(['id' => $productId]);
        if (!$existsStmt->fetch()) {
            throw new RuntimeException('Mehsul tapilmadi.');
        }

        $skuCheckStmt = $pdo->prepare(
            'SELECT id FROM products WHERE sku = :sku AND id <> :id LIMIT 1'
        );
        $skuCheckStmt->execute(['sku' => $barcode, 'id' => $productId]);
        if ($skuCheckStmt->fetch()) {
            throw new RuntimeException('Bu barkod basqa mehsulda istifade olunur.');
        }

        $categoryId = warehouseResolveFinalCategoryId($pdo, $parentCategoryId, $subcategoryId, $thirdCategoryId);

        $updateProduct = $pdo->prepare(
            'UPDATE products
             SET category_id = :category_id,
                 name = :name,
                 sku = :sku,
                 base_price = :base_price,
                 stock_qty = :stock_qty,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $updateProduct->execute([
            'id' => $productId,
            'category_id' => $categoryId,
            'name' => $name,
            'sku' => $barcode,
            'base_price' => $salePrice,
            'stock_qty' => $stockQty,
            'status' => $status,
        ]);

        $pdo->prepare(
            "DELETE FROM product_specs
             WHERE product_id = :product_id
               AND spec_key IN ('maya_deyeri', 'cost_price', 'purchase_price')"
        )->execute(['product_id' => $productId]);

        $upsertCostSpec = $pdo->prepare(
            "INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
             VALUES (:product_id, 'maya_deyeri', :spec_value, 0)"
        );
        $upsertCostSpec->execute([
            'product_id' => $productId,
            'spec_value' => number_format($costPrice, 2, '.', ''),
        ]);

        $redirectParams = warehouseRedirectParamsFromRequest();
        $redirectParams['warehouse_updated'] = '1';
        header('Location: admin_warehouse_cash.php?' . http_build_query($redirectParams));
        exit;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'warehouse_add_stock') {
    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $addQty = (int) ($_POST['add_qty'] ?? 0);

        if ($productId <= 0 || $addQty <= 0) {
            throw new RuntimeException('Mehsul ve elave olunan stok miqdari duzgun secilmelidir.');
        }

        $productStmt = $pdo->prepare(
            'SELECT id, name, stock_qty FROM products WHERE id = :id LIMIT 1'
        );
        $productStmt->execute(['id' => $productId]);
        $productRow = $productStmt->fetch();
        if (!$productRow) {
            throw new RuntimeException('Mehsul tapilmadi.');
        }

        $newStock = (int) $productRow['stock_qty'] + $addQty;
        $pdo->prepare(
            'UPDATE products
             SET stock_qty = :stock_qty, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute([
            'id' => $productId,
            'stock_qty' => $newStock,
        ]);

        $redirectParams = warehouseRedirectParamsFromRequest();
        $redirectParams['warehouse_stock_added'] = '1';
        $redirectParams['warehouse_stock_name'] = (string) $productRow['name'];
        $redirectParams['warehouse_stock_qty'] = (string) $addQty;
        header('Location: admin_warehouse_cash.php?' . http_build_query($redirectParams));
        exit;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

if (isset($_GET['warehouse_updated']) && (string) $_GET['warehouse_updated'] === '1') {
    $message = 'Mehsul ugurla yenilendi.';
    $messageType = 'success';
}

if (isset($_GET['warehouse_stock_added']) && (string) $_GET['warehouse_stock_added'] === '1') {
    $addedName = trim((string) ($_GET['warehouse_stock_name'] ?? ''));
    $addedQty = (int) ($_GET['warehouse_stock_qty'] ?? 0);
    if ($addedName !== '' && $addedQty > 0) {
        $message = $addedName . ' ucun stoka ' . $addedQty . ' eded elave edildi.';
    } else {
        $message = 'Stok ugurla artirildi.';
    }
    $messageType = 'success';
}

$warehouseStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_qty), 0) AS total_stock_units,
        COALESCE(SUM(stock_qty * base_price), 0) AS total_stock_value,
        COALESCE(SUM(CASE WHEN stock_qty < 10 THEN 1 ELSE 0 END), 0) AS low_stock_products,
        COALESCE(SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_products
     FROM products"
)->fetch() ?: [];

$cashStats = $pdo->query(
    "SELECT
        COALESCE(SUM(total_amount), 0) AS total_order_turnover,
        COALESCE(SUM(CASE WHEN status IN ('paid', 'processing', 'shipped', 'completed') THEN total_amount ELSE 0 END), 0) AS confirmed_turnover,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) AS pending_turnover,
        COALESCE(SUM(CASE WHEN status IN ('cancelled', 'refunded') THEN total_amount ELSE 0 END), 0) AS cancelled_or_refunded_turnover
     FROM orders"
)->fetch() ?: [];

$paymentByMethod = $pdo->query(
    "SELECT method, COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS payment_total
     FROM payments
     GROUP BY method
     ORDER BY payment_total DESC"
)->fetchAll() ?: [];

$productStockSelectSql = "
        p.id,
        p.name,
        p.sku,
        p.category_id,
        p.stock_qty,
        p.base_price,
        p.status,
        COALESCE((
            SELECT CAST(ps.spec_value AS DECIMAL(12,2))
            FROM product_specs ps
            WHERE ps.product_id = p.id
              AND ps.spec_key IN ('maya_deyeri', 'cost_price', 'purchase_price')
              AND ps.spec_value REGEXP '^[0-9]+(\\.[0-9]+)?$'
            ORDER BY ps.id DESC
            LIMIT 1
        ), p.base_price) AS cost_price
";

$lowStockItems = $pdo->query(
    "SELECT {$productStockSelectSql}
     FROM products p
     WHERE p.stock_qty < 10
     ORDER BY p.stock_qty ASC, p.id DESC
     LIMIT 100"
)->fetchAll() ?: [];

$warehouseQ = trim((string) ($_GET['warehouse_q'] ?? ''));
$warehouseFilterParent = (int) ($_GET['warehouse_cat'] ?? 0);
$warehouseFilterSub = (int) ($_GET['warehouse_sub'] ?? 0);
$warehouseFilterThird = (int) ($_GET['warehouse_third'] ?? 0);

/** @var list<int>|null $warehouseCategoryIds null = filtre yox */
$warehouseCategoryIds = null;
if ($warehouseFilterThird > 0) {
    if ($warehouseFilterSub <= 0 || $warehouseFilterParent <= 0) {
        $warehouseCategoryIds = null;
    } else {
        $chk = $pdo->prepare(
            "SELECT c.id
             FROM categories c
             INNER JOIN categories p ON p.id = c.parent_id AND p.id = :sub AND p.parent_id = :par
             WHERE c.id = :third AND c.is_active = 1
             LIMIT 1"
        );
        $chk->execute([
            'sub' => $warehouseFilterSub,
            'par' => $warehouseFilterParent,
            'third' => $warehouseFilterThird,
        ]);
        if ($chk->fetch()) {
            $warehouseCategoryIds = [$warehouseFilterThird];
        }
    }
} elseif ($warehouseFilterSub > 0) {
    if ($warehouseFilterParent <= 0) {
        $warehouseCategoryIds = null;
    } else {
        $chk = $pdo->prepare(
            "SELECT id FROM categories
             WHERE id = :sub AND parent_id = :par AND is_active = 1 LIMIT 1"
        );
        $chk->execute(['sub' => $warehouseFilterSub, 'par' => $warehouseFilterParent]);
        if ($chk->fetch()) {
            $warehouseCategoryIds = array_values(array_unique(warehouseCategorySubtreeIds($warehouseFilterSub, $subcategoriesByParent)));
        }
    }
} elseif ($warehouseFilterParent > 0) {
    $chk = $pdo->prepare(
        "SELECT id FROM categories
         WHERE id = :id AND parent_id IS NULL AND is_active = 1 LIMIT 1"
    );
    $chk->execute(['id' => $warehouseFilterParent]);
    if ($chk->fetch()) {
        $warehouseCategoryIds = array_values(array_unique(warehouseCategorySubtreeIds($warehouseFilterParent, $subcategoriesByParent)));
    }
}

$warehouseWhereSql = 'p.stock_qty > 0';
$warehouseQueryParams = [];
if ($warehouseQ !== '') {
    $warehouseWhereSql .= ' AND p.name LIKE :warehouse_q';
    $escapedQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $warehouseQ);
    $warehouseQueryParams['warehouse_q'] = '%' . $escapedQ . '%';
}
if ($warehouseCategoryIds !== null && $warehouseCategoryIds !== []) {
    $idList = implode(',', array_map(static fn (int $id): string => (string) $id, $warehouseCategoryIds));
    $warehouseWhereSql .= " AND p.category_id IN ({$idList})";
}

$warehouseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$warehouseWhereSql}");
foreach ($warehouseQueryParams as $k => $v) {
    $warehouseCountStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$warehouseCountStmt->execute();
$warehouseTotalInStock = (int) $warehouseCountStmt->fetchColumn();

$warehousePerPage = 50;
$warehouseTotalPages = max(1, (int) ceil($warehouseTotalInStock / $warehousePerPage));
$warehousePage = (int) ($_GET['warehouse_page'] ?? 1);
if ($warehousePage < 1) {
    $warehousePage = 1;
}
if ($warehousePage > $warehouseTotalPages) {
    $warehousePage = $warehouseTotalPages;
}
$warehouseOffset = ($warehousePage - 1) * $warehousePerPage;
$warehouseLimitSql = (int) $warehousePerPage;
$warehouseOffsetSql = (int) $warehouseOffset;

$warehouseListSql = "SELECT {$productStockSelectSql}
     FROM products p
     WHERE {$warehouseWhereSql}
     ORDER BY p.name ASC, p.id DESC
     LIMIT {$warehouseLimitSql} OFFSET {$warehouseOffsetSql}";
$warehouseListStmt = $pdo->prepare($warehouseListSql);
foreach ($warehouseQueryParams as $k => $v) {
    $warehouseListStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$warehouseListStmt->execute();
$warehouseInStockItems = $warehouseListStmt->fetchAll() ?: [];

$warehouseUiInitialTab = (
    isset($_GET['warehouse_page'])
    || isset($_GET['warehouse_updated'])
    || isset($_GET['warehouse_stock_added'])
    || $warehouseQ !== ''
    || $warehouseFilterParent > 0
    || $warehouseFilterSub > 0
    || $warehouseFilterThird > 0
) ? 'warehouse' : 'low';

function warehousePaginationUrl(int $page): string
{
    $params = $_GET;
    $params['warehouse_page'] = (string) $page;
    return 'admin_warehouse_cash.php?' . http_build_query($params);
}

function warehouseFilterClearUrl(): string
{
    return 'admin_warehouse_cash.php';
}

$warehouseSubOptions = $warehouseFilterParent > 0
    ? ($subcategoriesByParent[(string) $warehouseFilterParent] ?? [])
    : [];
$warehouseThirdOptions = $warehouseFilterSub > 0
    ? ($subcategoriesByParent[(string) $warehouseFilterSub] ?? [])
    : [];

$inventoryTotals = $pdo->query(
    "SELECT
        COALESCE(SUM(p.stock_qty * COALESCE((
            SELECT CAST(ps.spec_value AS DECIMAL(12,2))
            FROM product_specs ps
            WHERE ps.product_id = p.id
              AND ps.spec_key IN ('maya_deyeri', 'cost_price', 'purchase_price')
              AND ps.spec_value REGEXP '^[0-9]+(\\.[0-9]+)?$'
            ORDER BY ps.id DESC
            LIMIT 1
        ), p.base_price)), 0) AS total_cost_value,
        COALESCE(SUM(p.stock_qty * p.base_price), 0) AS total_sale_value
     FROM products p"
)->fetch() ?: [];

$totalCostValue = (float) ($inventoryTotals['total_cost_value'] ?? 0);
$totalSaleValue = (float) ($inventoryTotals['total_sale_value'] ?? 0);

$latestCashFlow = $pdo->query(
    "SELECT id, order_number, status, payment_status, total_amount, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 15"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ambar ve kassa</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .warehouse-edit-dialog {
            border: none;
            border-radius: 12px;
            padding: 0;
            max-width: min(720px, calc(100vw - 32px));
            width: 100%;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
        }
        .warehouse-edit-dialog::backdrop {
            background: rgba(0, 0, 0, 0.45);
        }
        .warehouse-edit-dialog-inner {
            padding: 20px 22px 22px;
        }
        .warehouse-edit-dialog-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .warehouse-edit-dialog-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .warehouse-panel-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 14px;
        }
        .warehouse-table-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 118px;
        }
        .warehouse-sticky-col {
            position: sticky;
            right: 0;
            z-index: 2;
            background: var(--card-bg, #fff);
            box-shadow: -6px 0 8px rgba(0, 0, 0, 0.04);
        }
        .warehouse-stock-selected {
            margin: 12px 0;
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php if ($isAdmin): ?>
        <?php adminSidebar('warehouse_cash'); ?>
    <?php endif; ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Ambar ve kassa</h1>
                <p class="admin-subtitle">Stok ve satisdan gelen pul axini bir ekranda izleyin.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section id="warehouse-quick-add-section" class="card" style="margin-bottom:16px;">
            <h3 class="section-title">Mehsul elave et (ambar paneli)</h3>
            <form method="post">
                <input type="hidden" name="action" value="quick_add_product">
                <div class="form-grid">
                    <div>
                        <label for="name">Mehsul adi</label>
                        <input id="name" name="name" required>
                    </div>
                    <div>
                        <label for="barcode">Barkod</label>
                        <input id="barcode" name="barcode" required>
                    </div>
                    <div>
                        <label for="category_id">Kateqoriya</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Secin</option>
                            <?php foreach ($topCategories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>"><?php echo e((string) $category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="subcategory_id">Alt kateqoriya</label>
                        <select id="subcategory_id" name="subcategory_id" disabled>
                            <option value="">Secin</option>
                        </select>
                        <div class="spec-help">Eger varsa, alt kateqoriya secin.</div>
                    </div>
                    <div>
                        <label for="third_category_id">Alt-alt kateqoriya</label>
                        <select id="third_category_id" name="third_category_id" disabled>
                            <option value="">Secin</option>
                        </select>
                        <div class="spec-help">Eger varsa, alt-alt kateqoriya secin.</div>
                    </div>
                    <div>
                        <label for="cost_price">Maya deyeri</label>
                        <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="0.00" required>
                    </div>
                    <div>
                        <label for="sale_price">Satis deyeri</label>
                        <input id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="0.00" required>
                    </div>
                    <div>
                        <label for="stock_qty">Stok</label>
                        <input id="stock_qty" name="stock_qty" type="number" min="0" value="0" required>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">active</option>
                            <option value="draft">draft</option>
                            <option value="archived">archived</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit" style="margin-top:12px;">Mehsulu elave et</button>
            </form>
        </section>

        <section class="stats-grid">
            <div class="card">
                <div class="card-k">Mehsul cesidi</div>
                <div class="card-v"><?php echo (int) ($warehouseStats['total_products'] ?? 0); ?></div>
            </div>
            <div class="card">
                <div class="card-k">Umumi stok</div>
                <div class="card-v"><?php echo (int) ($warehouseStats['total_stock_units'] ?? 0); ?></div>
            </div>
            <div class="card">
                <div class="card-k">Stok deyeri</div>
                <div class="card-v"><?php echo number_format((float) ($warehouseStats['total_stock_value'] ?? 0), 2); ?> ₼</div>
            </div>
            <div class="card">
                <div class="card-k">Toplam Maya</div>
                <div class="card-v"><?php echo number_format($totalCostValue, 2); ?> ₼</div>
            </div>
            <div class="card">
                <div class="card-k">Toplam Satis</div>
                <div class="card-v"><?php echo number_format($totalSaleValue, 2); ?> ₼</div>
            </div>
            <div class="card">
                <div class="card-k">Asagi stok (&lt;10)</div>
                <div class="card-v"><?php echo (int) ($warehouseStats['low_stock_products'] ?? 0); ?></div>
            </div>
            <div class="card">
                <div class="card-k">Stokda yoxdur</div>
                <div class="card-v"><?php echo (int) ($warehouseStats['out_of_stock_products'] ?? 0); ?></div>
            </div>
            <div class="card">
                <div class="card-k">Tesdiqli dovriyye</div>
                <div class="card-v"><?php echo number_format((float) ($cashStats['confirmed_turnover'] ?? 0), 2); ?> ₼</div>
            </div>
            <div class="card">
                <div class="card-k">Gozleyen dovriyye</div>
                <div class="card-v"><?php echo number_format((float) ($cashStats['pending_turnover'] ?? 0), 2); ?> ₼</div>
            </div>
            <div class="card">
                <div class="card-k">Legv/return meblegi</div>
                <div class="card-v"><?php echo number_format((float) ($cashStats['cancelled_or_refunded_turnover'] ?? 0), 2); ?> ₼</div>
            </div>
        </section>

        <div class="warehouse-tab-bar" style="display:flex; flex-wrap:wrap; gap:10px; margin: 16px 0;">
            <button type="button" class="btn btn-primary warehouse-tab-btn" data-warehouse-tab="low" aria-selected="true">Asagi stok mehsullari</button>
            <button type="button" class="btn btn-muted warehouse-tab-btn" data-warehouse-tab="warehouse" aria-selected="false">Anbardaki mehsullar</button>
            <button type="button" class="btn btn-muted warehouse-tab-btn" data-warehouse-tab="orders" aria-selected="false">Sifarisler</button>
        </div>

        <section id="warehouse-tab-panel-low" class="warehouse-tab-panel card table-wrap" data-warehouse-panel="low">
            <h3 class="section-title">Asagi stok mehsullari</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Mehsul</th>
                    <th>Barkod</th>
                    <th>Stok</th>
                    <th>Maya deyeri</th>
                    <th>Satis deyeri</th>
                    <th>Toplam Maya</th>
                    <th>Toplam Satis</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($lowStockItems)): ?>
                    <tr><td colspan="9">Mehsul yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($lowStockItems as $idx => $item): ?>
                        <?php
                        $qty = (int) $item['stock_qty'];
                        $costPrice = (float) $item['cost_price'];
                        $salePrice = (float) $item['base_price'];
                        $rowTotalCost = $costPrice * $qty;
                        $rowTotalSale = $salePrice * $qty;
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo e((string) $item['name']); ?><br><small class="spec-help">DB ID: <?php echo (int) $item['id']; ?></small></td>
                            <td><?php echo e((string) $item['sku']); ?></td>
                            <td><?php echo $qty; ?></td>
                            <td><?php echo number_format($costPrice, 2); ?> ₼</td>
                            <td><?php echo number_format($salePrice, 2); ?> ₼</td>
                            <td><?php echo number_format($rowTotalCost, 2); ?> ₼</td>
                            <td><?php echo number_format($rowTotalSale, 2); ?> ₼</td>
                            <td><span class="status-badge status-<?php echo e((string) $item['status']); ?>"><?php echo e((string) $item['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="warehouse-tab-panel-warehouse" class="warehouse-tab-panel card table-wrap" data-warehouse-panel="warehouse" hidden>
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:12px;">
                <div>
                    <h3 class="section-title" style="margin-bottom:4px;">Anbardaki mehsullar (stok &gt; 0)</h3>
                    <p class="admin-subtitle" style="margin:0;">
                        Cemi <?php echo $warehouseTotalInStock; ?> mehsul · Sehife <?php echo $warehousePage; ?> / <?php echo $warehouseTotalPages; ?> (<?php echo $warehousePerPage; ?> / sehife)
                    </p>
                </div>
                <div class="warehouse-panel-toolbar">
                    <button type="button" class="btn btn-primary" id="warehouse-open-add-stock">Stoka elave et</button>
                    <a class="btn btn-muted" href="#warehouse-quick-add-section">Yeni mehsul</a>
                </div>
            </div>
            <form class="warehouse-filter-form" method="get" action="admin_warehouse_cash.php" style="margin-bottom:16px;">
                <input type="hidden" name="warehouse_page" value="1">
                <div class="form-grid">
                    <div style="grid-column: 1 / -1;">
                        <label for="warehouse_q">Mehsul adi (axtar)</label>
                        <input id="warehouse_q" name="warehouse_q" type="search" value="<?php echo e($warehouseQ); ?>" placeholder="Ad ile axtar...">
                    </div>
                    <div>
                        <label for="warehouse_filter_cat">Kateqoriya</label>
                        <select id="warehouse_filter_cat" name="warehouse_cat">
                            <option value="">Butun kateqoriyalar</option>
                            <?php foreach ($topCategories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $category['id'] === $warehouseFilterParent ? 'selected' : ''; ?>>
                                    <?php echo e((string) $category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="warehouse_filter_sub">Alt kateqoriya</label>
                        <select id="warehouse_filter_sub" name="warehouse_sub" <?php echo $warehouseFilterParent > 0 ? '' : 'disabled'; ?>>
                            <option value="">Butun alt kateqoriyalar</option>
                            <?php foreach ($warehouseSubOptions as $sub): ?>
                                <option value="<?php echo (int) $sub['id']; ?>" <?php echo (int) $sub['id'] === $warehouseFilterSub ? 'selected' : ''; ?>>
                                    <?php echo e((string) $sub['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="spec-help">Eger varsa, alt kateqoriya secin.</div>
                    </div>
                    <div>
                        <label for="warehouse_filter_third">Alt-alt kateqoriya</label>
                        <select id="warehouse_filter_third" name="warehouse_third" <?php echo $warehouseFilterSub > 0 ? '' : 'disabled'; ?>>
                            <option value="">Butun alt-alt kateqoriyalar</option>
                            <?php foreach ($warehouseThirdOptions as $third): ?>
                                <option value="<?php echo (int) $third['id']; ?>" <?php echo (int) $third['id'] === $warehouseFilterThird ? 'selected' : ''; ?>>
                                    <?php echo e((string) $third['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="spec-help">Eger varsa, alt-alt kateqoriya secin.</div>
                    </div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px;">
                    <button class="btn btn-primary" type="submit">Filtrle</button>
                    <a class="btn btn-muted" href="<?php echo e(warehouseFilterClearUrl()); ?>">Temizle</a>
                </div>
            </form>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Mehsul</th>
                    <th>Barkod</th>
                    <th>Stok</th>
                    <th>Maya deyeri</th>
                    <th>Satis deyeri</th>
                    <th>Toplam Maya</th>
                    <th>Toplam Satis</th>
                    <th>Status</th>
                    <th class="warehouse-sticky-col">Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($warehouseInStockItems)): ?>
                    <tr><td colspan="10">
                        <?php if ($warehouseQ !== '' || $warehouseCategoryIds !== null): ?>
                            Filtr sertlerine uygun mehsul tapilmadi.
                        <?php else: ?>
                            Ambarda stoklu mehsul yoxdur.
                        <?php endif; ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($warehouseInStockItems as $idx => $item): ?>
                        <?php
                        $qty = (int) $item['stock_qty'];
                        $costPrice = (float) $item['cost_price'];
                        $salePrice = (float) $item['base_price'];
                        $rowTotalCost = $costPrice * $qty;
                        $rowTotalSale = $salePrice * $qty;
                        $rowNo = $warehouseOffset + $idx + 1;
                        $catPicker = warehouseResolveCategoryPicker((int) ($item['category_id'] ?? 0), $pdo);
                        ?>
                        <tr>
                            <td><?php echo $rowNo; ?></td>
                            <td><?php echo e((string) $item['name']); ?><br><small class="spec-help">DB ID: <?php echo (int) $item['id']; ?></small></td>
                            <td><?php echo e((string) $item['sku']); ?></td>
                            <td><?php echo $qty; ?></td>
                            <td><?php echo number_format($costPrice, 2); ?> ₼</td>
                            <td><?php echo number_format($salePrice, 2); ?> ₼</td>
                            <td><?php echo number_format($rowTotalCost, 2); ?> ₼</td>
                            <td><?php echo number_format($rowTotalSale, 2); ?> ₼</td>
                            <td><span class="status-badge status-<?php echo e((string) $item['status']); ?>"><?php echo e((string) $item['status']); ?></span></td>
                            <td class="warehouse-sticky-col">
                                <div class="warehouse-table-actions">
                                    <button
                                        type="button"
                                        class="btn btn-primary warehouse-add-stock-open"
                                        data-product-id="<?php echo (int) $item['id']; ?>"
                                        data-name="<?php echo e((string) $item['name']); ?>"
                                        data-barcode="<?php echo e((string) $item['sku']); ?>"
                                        data-stock="<?php echo $qty; ?>"
                                    >Stoka elave et</button>
                                    <button
                                        type="button"
                                        class="btn btn-muted warehouse-edit-open"
                                        data-product-id="<?php echo (int) $item['id']; ?>"
                                        data-name="<?php echo e((string) $item['name']); ?>"
                                        data-barcode="<?php echo e((string) $item['sku']); ?>"
                                        data-stock="<?php echo $qty; ?>"
                                        data-cost="<?php echo e(number_format($costPrice, 2, '.', '')); ?>"
                                        data-sale="<?php echo e(number_format($salePrice, 2, '.', '')); ?>"
                                        data-status="<?php echo e((string) $item['status']); ?>"
                                        data-cat-parent="<?php echo (int) $catPicker['parent']; ?>"
                                        data-cat-sub="<?php echo (int) $catPicker['sub']; ?>"
                                        data-cat-third="<?php echo (int) $catPicker['third']; ?>"
                                    >Redakte et</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($warehouseTotalPages > 1): ?>
                <nav class="warehouse-pagination" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:16px; padding-top:12px; border-top:1px solid rgba(0,0,0,0.08);">
                    <?php if ($warehousePage > 1): ?>
                        <a class="btn btn-muted" href="<?php echo e(warehousePaginationUrl($warehousePage - 1)); ?>">Evvelki</a>
                    <?php else: ?>
                        <span class="btn btn-muted" style="opacity:0.5; pointer-events:none;">Evvelki</span>
                    <?php endif; ?>
                    <span class="spec-help">Sehife <?php echo $warehousePage; ?> / <?php echo $warehouseTotalPages; ?></span>
                    <?php if ($warehousePage < $warehouseTotalPages): ?>
                        <a class="btn btn-muted" href="<?php echo e(warehousePaginationUrl($warehousePage + 1)); ?>">Novbeti</a>
                    <?php else: ?>
                        <span class="btn btn-muted" style="opacity:0.5; pointer-events:none;">Novbeti</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>

        <section id="warehouse-tab-panel-orders" class="warehouse-tab-panel card table-wrap" data-warehouse-panel="orders" hidden>
            <h3 class="section-title">Son kassa hereketleri (sifarisler)</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Nomre</th>
                    <th>Sifaris statusu</th>
                    <th>Odeme statusu</th>
                    <th>Mebleg</th>
                    <th>Tarix</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($latestCashFlow)): ?>
                    <tr><td colspan="6">Sifaris yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($latestCashFlow as $idx => $order): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo e((string) $order['order_number']); ?><br><small class="spec-help">DB ID: <?php echo (int) $order['id']; ?></small></td>
                            <td><span class="status-badge status-<?php echo e((string) $order['status']); ?>"><?php echo e((string) $order['status']); ?></span></td>
                            <td><span class="status-badge status-<?php echo e((string) $order['payment_status']); ?>"><?php echo e((string) $order['payment_status']); ?></span></td>
                            <td><?php echo number_format((float) $order['total_amount'], 2); ?> ₼</td>
                            <td><?php echo e((string) $order['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <dialog id="warehouse-add-stock-dialog" class="warehouse-edit-dialog">
            <form method="post" id="warehouse-add-stock-form">
                <input type="hidden" name="action" value="warehouse_add_stock">
                <input type="hidden" name="product_id" id="add_stock_product_id" value="">
                <?php if ($warehouseQ !== ''): ?>
                    <input type="hidden" name="warehouse_q" value="<?php echo e($warehouseQ); ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterParent > 0): ?>
                    <input type="hidden" name="warehouse_cat" value="<?php echo $warehouseFilterParent; ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterSub > 0): ?>
                    <input type="hidden" name="warehouse_sub" value="<?php echo $warehouseFilterSub; ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterThird > 0): ?>
                    <input type="hidden" name="warehouse_third" value="<?php echo $warehouseFilterThird; ?>">
                <?php endif; ?>
                <?php if ($warehousePage > 1): ?>
                    <input type="hidden" name="warehouse_page" value="<?php echo $warehousePage; ?>">
                <?php endif; ?>
                <div class="warehouse-edit-dialog-inner">
                    <div class="warehouse-edit-dialog-head">
                        <h3 class="section-title" style="margin:0;">Stoka mehsul elave et</h3>
                        <button type="button" class="btn btn-muted" id="warehouse-add-stock-close">Bagla</button>
                    </div>
                    <div id="add_stock_selected" class="warehouse-stock-selected" hidden>
                        <strong id="add_stock_selected_name"></strong><br>
                        <span class="spec-help">Barkod: <span id="add_stock_selected_sku"></span> · Cari stok: <span id="add_stock_selected_qty"></span></span>
                    </div>
                    <div class="bundle-picker" id="warehouse-stock-picker">
                        <label class="bundle-picker__label" for="add_stock_search">Mehsul axtar</label>
                        <div class="bundle-picker__search-wrap">
                            <input id="add_stock_search" class="bundle-picker__search" type="search" placeholder="Ad ve ya barkod (en az 2 herf)...">
                        </div>
                        <div id="add_stock_results" class="bundle-picker__results" hidden></div>
                    </div>
                    <div style="margin-top:14px;">
                        <label for="add_stock_qty">Elave olunacaq miqdar</label>
                        <input id="add_stock_qty" name="add_qty" type="number" min="1" value="1" required>
                    </div>
                    <div class="warehouse-edit-dialog-actions">
                        <button class="btn btn-primary" type="submit">Stoka elave et</button>
                        <button type="button" class="btn btn-muted" id="warehouse-add-stock-cancel">Legv et</button>
                    </div>
                </div>
            </form>
        </dialog>

        <dialog id="warehouse-edit-dialog" class="warehouse-edit-dialog">
            <form method="post" id="warehouse-edit-form">
                <input type="hidden" name="action" value="edit_warehouse_product">
                <input type="hidden" name="product_id" id="edit_product_id" value="">
                <?php if ($warehouseQ !== ''): ?>
                    <input type="hidden" name="warehouse_q" value="<?php echo e($warehouseQ); ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterParent > 0): ?>
                    <input type="hidden" name="warehouse_cat" value="<?php echo $warehouseFilterParent; ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterSub > 0): ?>
                    <input type="hidden" name="warehouse_sub" value="<?php echo $warehouseFilterSub; ?>">
                <?php endif; ?>
                <?php if ($warehouseFilterThird > 0): ?>
                    <input type="hidden" name="warehouse_third" value="<?php echo $warehouseFilterThird; ?>">
                <?php endif; ?>
                <?php if ($warehousePage > 1): ?>
                    <input type="hidden" name="warehouse_page" value="<?php echo $warehousePage; ?>">
                <?php endif; ?>
                <div class="warehouse-edit-dialog-inner">
                    <div class="warehouse-edit-dialog-head">
                        <h3 class="section-title" style="margin:0;">Mehsulu redakte et</h3>
                        <button type="button" class="btn btn-muted" id="warehouse-edit-close">Bagla</button>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label for="edit_name">Mehsul adi</label>
                            <input id="edit_name" name="name" required>
                        </div>
                        <div>
                            <label for="edit_barcode">Barkod</label>
                            <input id="edit_barcode" name="barcode" required>
                        </div>
                        <div>
                            <label for="edit_category_id">Kateqoriya</label>
                            <select id="edit_category_id" name="category_id" required>
                                <option value="">Secin</option>
                                <?php foreach ($topCategories as $category): ?>
                                    <option value="<?php echo (int) $category['id']; ?>"><?php echo e((string) $category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="edit_subcategory_id">Alt kateqoriya</label>
                            <select id="edit_subcategory_id" name="subcategory_id" disabled>
                                <option value="">Secin</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_third_category_id">Alt-alt kateqoriya</label>
                            <select id="edit_third_category_id" name="third_category_id" disabled>
                                <option value="">Secin</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_cost_price">Maya deyeri</label>
                            <input id="edit_cost_price" name="cost_price" type="number" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label for="edit_sale_price">Satis deyeri</label>
                            <input id="edit_sale_price" name="sale_price" type="number" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label for="edit_stock_qty">Stok</label>
                            <input id="edit_stock_qty" name="stock_qty" type="number" min="0" required>
                        </div>
                        <div>
                            <label for="edit_status">Status</label>
                            <select id="edit_status" name="status">
                                <option value="active">active</option>
                                <option value="draft">draft</option>
                                <option value="archived">archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="warehouse-edit-dialog-actions">
                        <button class="btn btn-primary" type="submit">Yadda saxla</button>
                        <button type="button" class="btn btn-muted" id="warehouse-edit-cancel">Legv et</button>
                    </div>
                </div>
            </form>
        </dialog>

        <section class="card" style="margin-top:16px;">
            <h3 class="section-title">Odeme usullarina gore yekun</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Usul</th>
                        <th>Say</th>
                        <th>Yekun</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($paymentByMethod)): ?>
                        <tr><td colspan="3">Odeme hereketi yoxdur.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paymentByMethod as $row): ?>
                            <tr>
                                <td><?php echo e((string) $row['method']); ?></td>
                                <td><?php echo (int) $row['payment_count']; ?></td>
                                <td><?php echo number_format((float) $row['payment_total'], 2); ?> ₼</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="stack-sm" style="margin-top: 12px;">
                <div><strong>Toplam Maya:</strong> <?php echo number_format($totalCostValue, 2); ?> ₼</div>
                <div><strong>Toplam Satis:</strong> <?php echo number_format($totalSaleValue, 2); ?> ₼</div>
            </div>
        </section>
    </main>
</div>
<script>
(() => {
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const thirdCategorySelect = document.getElementById('third_category_id');
    if (!categorySelect || !subcategorySelect || !thirdCategorySelect) return;

    const subcategoriesByParent = <?php echo json_encode($subcategoriesByParent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const fillOptions = (selectEl, options) => {
        selectEl.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Secin';
        selectEl.appendChild(placeholder);

        for (const opt of options) {
            const option = document.createElement('option');
            option.value = String(opt.id);
            option.textContent = opt.name;
            selectEl.appendChild(option);
        }
    };

    const syncSubcategories = () => {
        const parentId = categorySelect.value;
        const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
        fillOptions(subcategorySelect, options);
        subcategorySelect.disabled = options.length === 0;

        fillOptions(thirdCategorySelect, []);
        thirdCategorySelect.disabled = true;
    };

    const syncThirdCategories = () => {
        const subId = subcategorySelect.value;
        const options = subId && Array.isArray(subcategoriesByParent[subId]) ? subcategoriesByParent[subId] : [];
        fillOptions(thirdCategorySelect, options);
        thirdCategorySelect.disabled = options.length === 0;
    };

    categorySelect.addEventListener('change', syncSubcategories);
    subcategorySelect.addEventListener('change', syncThirdCategories);
})();

(() => {
    const buttons = document.querySelectorAll('.warehouse-tab-btn');
    const panels = document.querySelectorAll('.warehouse-tab-panel');
    if (!buttons.length || !panels.length) return;

    const initialTab = <?php echo json_encode($warehouseUiInitialTab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const showTab = (key) => {
        buttons.forEach((btn) => {
            const active = btn.getAttribute('data-warehouse-tab') === key;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-muted', !active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const match = panel.getAttribute('data-warehouse-panel') === key;
            panel.hidden = !match;
        });
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-warehouse-tab');
            if (key) showTab(key);
        });
    });

    showTab(['low', 'warehouse', 'orders'].includes(initialTab) ? initialTab : 'low');
})();

(() => {
    const cat = document.getElementById('warehouse_filter_cat');
    const sub = document.getElementById('warehouse_filter_sub');
    const third = document.getElementById('warehouse_filter_third');
    if (!cat || !sub || !third) return;

    const subcategoriesByParent = <?php echo json_encode($subcategoriesByParent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const fillOptions = (selectEl, options, placeholderText) => {
        const current = selectEl.value;
        selectEl.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholderText;
        selectEl.appendChild(ph);
        for (const opt of options) {
            const o = document.createElement('option');
            o.value = String(opt.id);
            o.textContent = opt.name;
            selectEl.appendChild(o);
        }
        const still = Array.from(selectEl.options).some((o) => o.value === current);
        if (still) selectEl.value = current;
    };

    const syncSub = () => {
        const parentId = cat.value;
        const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
        fillOptions(sub, options, 'Butun alt kateqoriyalar');
        sub.disabled = options.length === 0;
        fillOptions(third, [], 'Butun alt-alt kateqoriyalar');
        third.disabled = true;
    };

    const syncThird = () => {
        const subId = sub.value;
        const options = subId && Array.isArray(subcategoriesByParent[subId]) ? subcategoriesByParent[subId] : [];
        fillOptions(third, options, 'Butun alt-alt kateqoriyalar');
        third.disabled = options.length === 0;
    };

    cat.addEventListener('change', syncSub);
    sub.addEventListener('change', syncThird);
})();

(() => {
    const dialog = document.getElementById('warehouse-edit-dialog');
    const form = document.getElementById('warehouse-edit-form');
    const closeBtn = document.getElementById('warehouse-edit-close');
    const cancelBtn = document.getElementById('warehouse-edit-cancel');
    const categorySelect = document.getElementById('edit_category_id');
    const subcategorySelect = document.getElementById('edit_subcategory_id');
    const thirdCategorySelect = document.getElementById('edit_third_category_id');
    if (!dialog || !form || !categorySelect || !subcategorySelect || !thirdCategorySelect) return;

    const subcategoriesByParent = <?php echo json_encode($subcategoriesByParent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const fillOptions = (selectEl, options, keepValue) => {
        selectEl.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Secin';
        selectEl.appendChild(placeholder);
        for (const opt of options) {
            const option = document.createElement('option');
            option.value = String(opt.id);
            option.textContent = opt.name;
            selectEl.appendChild(option);
        }
        if (keepValue) {
            const match = Array.from(selectEl.options).some((o) => o.value === keepValue);
            if (match) selectEl.value = keepValue;
        }
    };

    const syncSubcategories = (subValue, thirdValue) => {
        const parentId = categorySelect.value;
        const options = parentId && Array.isArray(subcategoriesByParent[parentId]) ? subcategoriesByParent[parentId] : [];
        fillOptions(subcategorySelect, options, subValue || '');
        subcategorySelect.disabled = options.length === 0;
        syncThirdCategories(thirdValue || '');
    };

    const syncThirdCategories = (thirdValue) => {
        const subId = subcategorySelect.value;
        const options = subId && Array.isArray(subcategoriesByParent[subId]) ? subcategoriesByParent[subId] : [];
        fillOptions(thirdCategorySelect, options, thirdValue || '');
        thirdCategorySelect.disabled = options.length === 0;
    };

    categorySelect.addEventListener('change', () => syncSubcategories('', ''));
    subcategorySelect.addEventListener('change', () => syncThirdCategories(''));

    const closeDialog = () => {
        if (dialog.open) dialog.close();
    };

    closeBtn?.addEventListener('click', closeDialog);
    cancelBtn?.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (e) => {
        e.preventDefault();
        closeDialog();
    });
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) closeDialog();
    });

    document.querySelectorAll('.warehouse-edit-open').forEach((btn) => {
        btn.addEventListener('click', () => {
            const ds = btn.dataset;
            document.getElementById('edit_product_id').value = ds.productId || '';
            document.getElementById('edit_name').value = ds.name || '';
            document.getElementById('edit_barcode').value = ds.barcode || '';
            document.getElementById('edit_stock_qty').value = ds.stock || '0';
            document.getElementById('edit_cost_price').value = ds.cost || '0';
            document.getElementById('edit_sale_price').value = ds.sale || '0';
            document.getElementById('edit_status').value = ds.status || 'active';

            const parentCat = ds.catParent || '';
            const subCat = ds.catSub || '';
            const thirdCat = ds.catThird || '';
            categorySelect.value = parentCat;
            syncSubcategories(subCat, thirdCat);

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        });
    });
})();

(() => {
    const dialog = document.getElementById('warehouse-add-stock-dialog');
    const form = document.getElementById('warehouse-add-stock-form');
    const openBtn = document.getElementById('warehouse-open-add-stock');
    const closeBtn = document.getElementById('warehouse-add-stock-close');
    const cancelBtn = document.getElementById('warehouse-add-stock-cancel');
    const searchInput = document.getElementById('add_stock_search');
    const resultsEl = document.getElementById('add_stock_results');
    const productIdInput = document.getElementById('add_stock_product_id');
    const selectedBox = document.getElementById('add_stock_selected');
    const selectedName = document.getElementById('add_stock_selected_name');
    const selectedSku = document.getElementById('add_stock_selected_sku');
    const selectedQty = document.getElementById('add_stock_selected_qty');
    const addQtyInput = document.getElementById('add_stock_qty');
    if (!dialog || !form || !searchInput || !resultsEl || !productIdInput) return;

    let searchTimer = null;

    const openDialog = () => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    };

    const closeDialog = () => {
        if (dialog.open) dialog.close();
    };

    const clearResults = () => {
        resultsEl.innerHTML = '';
        resultsEl.hidden = true;
    };

    const selectProduct = (product) => {
        if (!product || !product.id) return;
        productIdInput.value = String(product.id);
        selectedName.textContent = product.name || '';
        selectedSku.textContent = product.sku || '';
        selectedQty.textContent = String(product.stock_qty ?? 0);
        selectedBox.hidden = false;
        clearResults();
        searchInput.value = product.name || '';
        addQtyInput?.focus();
    };

    const resetPicker = () => {
        productIdInput.value = '';
        selectedBox.hidden = true;
        searchInput.value = '';
        if (addQtyInput) addQtyInput.value = '1';
        clearResults();
    };

    const renderResults = (products) => {
        resultsEl.innerHTML = '';
        if (!products.length) {
            const empty = document.createElement('div');
            empty.className = 'bundle-picker__result-empty';
            empty.textContent = 'Netice tapilmadi';
            resultsEl.appendChild(empty);
            resultsEl.hidden = false;
            return;
        }
        products.forEach((p) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'bundle-picker__result-row';
            const nameSpan = document.createElement('span');
            nameSpan.className = 'bundle-picker__result-name';
            nameSpan.textContent = p.name;
            const metaSpan = document.createElement('span');
            metaSpan.className = 'bundle-picker__result-meta';
            metaSpan.textContent = (p.sku || '') + ' · stok: ' + (p.stock_qty ?? 0);
            row.appendChild(nameSpan);
            row.appendChild(metaSpan);
            row.addEventListener('click', () => selectProduct(p));
            resultsEl.appendChild(row);
        });
        resultsEl.hidden = false;
    };

    const runSearch = async (q) => {
        const url = 'ajax/warehouse_product_search.php?q=' + encodeURIComponent(q);
        const r = await fetch(url, { credentials: 'same-origin' });
        const data = await r.json().catch(() => null);
        if (!r.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'Axtaris ugursuz oldu');
        }
        return data.products || [];
    };

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim();
        if (searchTimer) clearTimeout(searchTimer);
        if (q.length < 2) {
            clearResults();
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                renderResults(await runSearch(q));
            } catch (err) {
                resultsEl.innerHTML = '';
                const msg = document.createElement('div');
                msg.className = 'bundle-picker__result-empty';
                msg.textContent = err instanceof Error ? err.message : 'Xeta';
                resultsEl.appendChild(msg);
                resultsEl.hidden = false;
            }
        }, 280);
    });

    form.addEventListener('submit', (e) => {
        if (!productIdInput.value) {
            e.preventDefault();
            window.alert('Evvelce mehsul secin.');
            searchInput.focus();
        }
    });

    openBtn?.addEventListener('click', () => {
        resetPicker();
        openDialog();
        searchInput.focus();
    });

    document.querySelectorAll('.warehouse-add-stock-open').forEach((btn) => {
        btn.addEventListener('click', () => {
            resetPicker();
            selectProduct({
                id: parseInt(btn.dataset.productId || '0', 10),
                name: btn.dataset.name || '',
                sku: btn.dataset.barcode || '',
                stock_qty: parseInt(btn.dataset.stock || '0', 10),
            });
            openDialog();
        });
    });

    closeBtn?.addEventListener('click', closeDialog);
    cancelBtn?.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (e) => {
        e.preventDefault();
        closeDialog();
    });
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) closeDialog();
    });
})();
</script>
</body>
</html>
