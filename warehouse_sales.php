<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/admin/admin_auth.php';

$canAccessWarehouse = isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin']);
if (!$canAccessWarehouse) {
    if (!isUserLoggedIn()) {
        header('Location: user_login.php?redirect=' . urlencode('warehouse_sales.php'));
        exit;
    }
    http_response_code(403);
    echo 'Bu səhifəyə giriş üçün satıcı və ya admin rolu tələb olunur.';
    exit;
}

$pdo = db();
$currentPage = 'warehouse sales';
$flashMessage = '';
$flashType = 'success';

$search = trim((string) ($_GET['q'] ?? ''));
$stockFilter = trim((string) ($_GET['stock'] ?? 'all'));
$sort = trim((string) ($_GET['sort'] ?? 'updated_desc'));

$allowedStock = ['all', 'in_stock', 'low_stock'];
if (!in_array($stockFilter, $allowedStock, true)) {
    $stockFilter = 'all';
}

$allowedSort = [
    'updated_desc' => 'p.updated_at DESC, p.id DESC',
    'price_desc' => 'p.base_price DESC, p.id DESC',
    'price_asc' => 'p.base_price ASC, p.id DESC',
    'stock_desc' => 'p.stock_qty DESC, p.id DESC',
];
if (!isset($allowedSort[$sort])) {
    $sort = 'updated_desc';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 1);
        addToCart($pdo, $productId, $qty, true);
        $backQuery = http_build_query($_GET);
        header('Location: warehouse_sales.php' . ($backQuery !== '' ? '?' . $backQuery : ''));
        exit;
    } catch (Throwable $e) {
        $flashMessage = $e->getMessage();
        $flashType = 'error';
    }
}

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN stock_qty > 0 THEN 1 ELSE 0 END) AS ready_to_sell,
        SUM(CASE WHEN stock_qty BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock_count,
        COALESCE(SUM(CASE WHEN stock_qty > 0 THEN stock_qty ELSE 0 END), 0) AS total_units
     FROM products
     WHERE status = 'active'"
)->fetch() ?: [];

$sql = "SELECT
            p.id,
            p.slug,
            p.name,
            p.short_description,
            p.base_price,
            p.stock_qty,
            p.updated_at,
            b.name AS brand_name,
            c.name AS category_name,
            (
                SELECT image_url
                FROM product_images
                WHERE product_id = p.id
                ORDER BY is_primary DESC, sort_order ASC, id ASC
                LIMIT 1
            ) AS image_url
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'";

$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE :search_name OR b.name LIKE :search_brand OR c.name LIKE :search_category)";
    $searchValue = '%' . $search . '%';
    $params['search_name'] = $searchValue;
    $params['search_brand'] = $searchValue;
    $params['search_category'] = $searchValue;
}

if ($stockFilter === 'in_stock') {
    $sql .= " AND p.stock_qty > 0";
} elseif ($stockFilter === 'low_stock') {
    $sql .= " AND p.stock_qty BETWEEN 1 AND 5";
}

$sql .= " ORDER BY " . $allowedSort[$sort] . " LIMIT 36";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll() ?: [];

?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MAXHOME | Anbar Satış Mərkəzi</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/warehouse_sales.css">
</head>
<body class="ws-is-loading">
<?php include __DIR__ . '/navbar.php'; ?>

<main class="warehouse-page container">
    <section class="ws-panel">
        <div class="ws-skeleton" aria-hidden="true">
            <div class="ws-skeleton__header">
                <div>
                    <div class="ws-skeleton-block ws-skeleton-block--title"></div>
                    <div class="ws-skeleton-block ws-skeleton-block--subtitle"></div>
                </div>
                <div class="ws-skeleton__actions">
                    <div class="ws-skeleton-block ws-skeleton-block--btn"></div>
                    <div class="ws-skeleton-block ws-skeleton-block--btn"></div>
                </div>
            </div>

            <div class="ws-skeleton__stats">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="ws-skeleton-stat">
                        <div class="ws-skeleton-block ws-skeleton-block--stat-label"></div>
                        <div class="ws-skeleton-block ws-skeleton-block--stat-value"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="ws-skeleton__filters">
                <div class="ws-skeleton-block ws-skeleton-block--filter"></div>
                <div class="ws-skeleton-block ws-skeleton-block--filter"></div>
                <div class="ws-skeleton-block ws-skeleton-block--filter"></div>
                <div class="ws-skeleton-block ws-skeleton-block--filter-short"></div>
            </div>

            <div class="ws-skeleton__table-card">
                <div class="ws-skeleton__table-head">
                    <div class="ws-skeleton-block ws-skeleton-block--table-title"></div>
                    <div class="ws-skeleton-block ws-skeleton-block--table-meta"></div>
                </div>
                <div class="ws-skeleton__table-rows">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <div class="ws-skeleton-row">
                            <div class="ws-skeleton-block ws-skeleton-block--id"></div>
                            <div class="ws-skeleton-product">
                                <div class="ws-skeleton-block ws-skeleton-block--thumb"></div>
                                <div class="ws-skeleton-product__text">
                                    <div class="ws-skeleton-block ws-skeleton-block--line"></div>
                                    <div class="ws-skeleton-block ws-skeleton-block--line-short"></div>
                                </div>
                            </div>
                            <div class="ws-skeleton-block ws-skeleton-block--cell"></div>
                            <div class="ws-skeleton-block ws-skeleton-block--cell-short"></div>
                            <div class="ws-skeleton-block ws-skeleton-block--badge"></div>
                            <div class="ws-skeleton-block ws-skeleton-block--cell"></div>
                            <div class="ws-skeleton-block ws-skeleton-block--actions"></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="ws-content">
        <header class="ws-header">
            <div>
                <h1>Sifarişlər</h1>
                <p>Satış siyahısını izləyin və idarə edin</p>
            </div>
            <div class="ws-header__actions">
                <a class="ws-header__action ws-header__action--secondary" href="shopping_cart.php">Satış bitdi</a>
                <a class="ws-header__action" href="shop_page.php">+ Yeni Satış</a>
            </div>
        </header>

        <div class="ws-stat-grid">
            <article class="ws-stat-card">
                <span>Aktiv məhsul</span>
                <strong><?php echo (int) ($stats['total_products'] ?? 0); ?></strong>
            </article>
            <article class="ws-stat-card">
                <span>Satışa hazır model</span>
                <strong><?php echo (int) ($stats['ready_to_sell'] ?? 0); ?></strong>
            </article>
            <article class="ws-stat-card">
                <span>Azalan stok</span>
                <strong><?php echo (int) ($stats['low_stock_count'] ?? 0); ?></strong>
            </article>
            <article class="ws-stat-card">
                <span>Ümumi ədəd</span>
                <strong><?php echo number_format((float) ($stats['total_units'] ?? 0)); ?></strong>
            </article>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="ws-flash ws-flash--<?php echo $flashType === 'error' ? 'error' : 'success'; ?>">
                <?php echo e($flashMessage); ?>
            </div>
        <?php endif; ?>

        <form class="ws-filters" method="get" id="wsFiltersForm">
            <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Axtarış (məhsul, marka, kateqoriya)...">
            <select name="stock">
                <option value="all" <?php echo $stockFilter === 'all' ? 'selected' : ''; ?>>Bütün statuslar</option>
                <option value="in_stock" <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>>Stokda olan</option>
                <option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Azalan stok (1-5)</option>
            </select>
            <select name="sort">
                <option value="updated_desc" <?php echo $sort === 'updated_desc' ? 'selected' : ''; ?>>Son yenilənən</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Qiymət (çoxdan aza)</option>
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Qiymət (azdan çoxa)</option>
                <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stok (çoxdan aza)</option>
            </select>
            <a href="warehouse_sales.php" class="ws-btn ws-btn--muted">Sıfırla</a>
        </form>

        <section class="ws-table-card">
            <header class="ws-table-head">
                <h2>Məhsul siyahısı</h2>
                <p><?php echo count($products); ?> məhsul göstərilir</p>
            </header>

            <div class="ws-table-wrap">
                <table class="ws-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Məhsul</th>
                        <th>İstifadəçi</th>
                        <th>Məbləğ</th>
                        <th>Status</th>
                        <th>Tarix</th>
                        <th>Əməliyyatlar</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($products === []): ?>
                        <tr>
                            <td colspan="7" class="ws-empty">Bu kriteriyalara uyğun məhsul tapılmadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $image = trim((string) ($product['image_url'] ?? ''));
                            if ($image === '') {
                                $image = '/uploads/products/smartphone.svg';
                            }
                            $stockQty = (int) ($product['stock_qty'] ?? 0);
                            $stockClass = $stockQty > 5 ? 'stock-ok' : ($stockQty > 0 ? 'stock-low' : 'stock-out');
                            $stockLabel = $stockQty > 0 ? 'Stok: ' . $stockQty : 'Stok yoxdur';
                            $updatedAt = (string) ($product['updated_at'] ?? '');
                            $updatedAtTs = strtotime($updatedAt);
                            $updatedLabel = $updatedAtTs ? date('d.m.Y H:i', $updatedAtTs) : '-';
                            ?>
                            <tr>
                                <td class="ws-id">PRD-<?php echo (int) ($product['id'] ?? 0); ?></td>
                                <td>
                                    <div class="ws-product">
                                        <img src="<?php echo e($image); ?>" alt="<?php echo e((string) ($product['name'] ?? 'Məhsul')); ?>">
                                        <div>
                                            <strong><?php echo e((string) ($product['name'] ?? '')); ?></strong>
                                            <small><?php echo e(maxhomeProductPlainText((string) ($product['short_description'] ?: 'Satış üçün anbarda mövcuddur.'))); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo e((string) ($product['brand_name'] ?? 'MAXHOME')); ?><br><small><?php echo e((string) ($product['category_name'] ?? 'Ümumi')); ?></small></td>
                                <td><?php echo number_format((float) ($product['base_price'] ?? 0), 2); ?> ₼</td>
                                <td><span class="ws-badge <?php echo $stockClass; ?>"><?php echo e($stockLabel); ?></span></td>
                                <td><?php echo e($updatedLabel); ?></td>
                                <td>
                                    <div class="ws-actions">
                                        <a href="product_details.php?slug=<?php echo urlencode((string) ($product['slug'] ?? '')); ?>" class="ws-action ws-action--view">Bax</a>
                                        <form method="post" class="ws-inline-form">
                                            <input type="hidden" name="add_to_cart" value="1">
                                            <input type="hidden" name="product_id" value="<?php echo (int) ($product['id'] ?? 0); ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="ws-action ws-action--sale" <?php echo $stockQty <= 0 ? 'disabled' : ''; ?>>
                                                Satış
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        </div>
    </section>
</main>

<script>
(function () {
    var body = document.body;
    var form = document.getElementById('wsFiltersForm');

    function showContent() {
        body.classList.remove('ws-is-loading');
    }

    function showSkeleton() {
        body.classList.add('ws-is-loading');
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

    if (!form) {
        return;
    }

    var searchInput = form.querySelector('input[name="q"]');
    var selects = form.querySelectorAll('select');
    var debounceTimer = null;

    function submitFilters() {
        showSkeleton();
        form.submit();
    }

    form.addEventListener('submit', showSkeleton);

    selects.forEach(function (el) {
        el.addEventListener('change', submitFilters);
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitFilters, 320);
        });
    }
})();
</script>

</body>
</html>
