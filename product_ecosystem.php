<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$pdo = db();
$catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
$flashMessage = '';
$flashType = 'success';

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

if ($slug !== '') {
    $productStmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, b.name AS brand_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN brands b ON b.id = p.brand_id
         WHERE p.slug = :slug AND p.status = 'active'{$catalogOnlineSql}
         LIMIT 1"
    );
    $productStmt->execute(['slug' => $slug]);
} else {
    $productStmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, b.name AS brand_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN brands b ON b.id = p.brand_id
         WHERE p.id = :id AND p.status = 'active'{$catalogOnlineSql}
         LIMIT 1"
    );
    $productStmt->execute(['id' => $id]);
}

$product = $productStmt->fetch();

if (!$product) {
    http_response_code(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ecosystem_cart'])) {
    try {
        if (!$product) {
            throw new RuntimeException('Ürün bulunamadı.');
        }
        if (!productBundleItemsTableExists($pdo)) {
            throw new RuntimeException('Bu özellik henüz hazır değil (veritabanı migrasyonu gerekir).');
        }
        $parentId = (int) ($_POST['ecosystem_parent_id'] ?? 0);
        if ($parentId !== (int) $product['id']) {
            throw new RuntimeException('Geçersiz istek.');
        }
        $bundleAddStmt = $pdo->prepare(
            'SELECT p.id, p.stock_qty
             FROM product_bundle_items pbi
             INNER JOIN products p ON p.id = pbi.bundle_product_id AND p.status = \'active\'' . maxhomeProductsOnlineVisibleSql($pdo, 'p') . '
             WHERE pbi.parent_product_id = :pid
             ORDER BY pbi.sort_order ASC, pbi.id ASC'
        );
        $bundleAddStmt->execute(['pid' => $parentId]);
        $bundleRowsToAdd = [];
        foreach ($bundleAddStmt->fetchAll() ?: [] as $brow) {
            if ((int) $brow['stock_qty'] > 0) {
                $bundleRowsToAdd[] = (int) $brow['id'];
            }
        }

        addToCart($pdo, $parentId, 1);
        foreach ($bundleRowsToAdd as $bid) {
            addToCart($pdo, $bid, 1);
        }

        $redirectQuery = $slug !== '' ? '?slug=' . urlencode($slug) : '?id=' . $parentId;
        header('Location: product_ecosystem.php' . $redirectQuery . '&ecosystem_added=1');
        exit;
    } catch (Throwable $e) {
        $flashMessage = $e->getMessage();
        $flashType = 'error';
    }
}

if (isset($_GET['ecosystem_added']) && $_GET['ecosystem_added'] === '1') {
    $flashMessage = 'Paket sepete eklendi (stokta olan ürünler).';
}

$images = [];
$bundleProducts = [];
if ($product) {
    $imgStmt = $pdo->prepare('SELECT image_url, alt_text FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, sort_order ASC, id ASC');
    $imgStmt->execute(['pid' => (int) $product['id']]);
    $images = $imgStmt->fetchAll() ?: [];

    if (productBundleItemsTableExists($pdo)) {
        $bundleStmt = $pdo->prepare(
            "SELECT p.id, p.name, p.slug, p.base_price, p.stock_qty,
                    (SELECT pi.image_url FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_url
             FROM product_bundle_items pbi
             INNER JOIN products p ON p.id = pbi.bundle_product_id AND p.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'p') . "
             WHERE pbi.parent_product_id = :pid
             ORDER BY pbi.sort_order ASC, pbi.id ASC"
        );
        $bundleStmt->execute(['pid' => (int) $product['id']]);
        $bundleProducts = $bundleStmt->fetchAll() ?: [];
    }
}

$detailUrl = $product
    ? ($slug !== '' ? 'product_details.php?slug=' . urlencode($slug) : 'product_details.php?id=' . (int) $product['id'])
    : 'shop_page.php';

$mainImage = $images[0]['image_url'] ?? 'https://via.placeholder.com/800x800?text=No+Image';
$mainAlt = $images[0]['alt_text'] ?? ($product ? (string) $product['name'] : '');
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo $product ? e((string) $product['name']) . ' — Ekosistem' : 'Ekosistem'; ?> | MAXHOME</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@300;400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/product_details.css">
</head>
<body>
    <?php $currentPage = 'laptops'; ?>
    <?php include 'navbar.php'; ?>
    <main class="main-content ecosystem-page">
        <?php if ($flashMessage !== ''): ?>
            <div style="margin-bottom: 12px; padding: 12px; border-radius: 10px; color: #fff; background: <?php echo $flashType === 'error' ? '#b91c1c' : '#047857'; ?>;">
                <?php echo e($flashMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!$product): ?>
            <div class="buy-card">
                <h1 class="product-title">Məhsul tapılmadı</h1>
                <p style="margin-top: 8px;">Ekosistem səhifəsi mövcud deyil.</p>
                <a class="btn-primary" href="shop_page.php" style="display:inline-block; margin-top: 16px; text-decoration:none;">Mağazaya qayıt</a>
            </div>
        <?php elseif (empty($bundleProducts)): ?>
            <div class="breadcrumb">
                <a href="<?php echo e($detailUrl); ?>" class="ecosystem-page__back"><?php echo e((string) $product['name']); ?></a>
                <span class="material-symbols-outlined" style="font-size: 0.875rem;">chevron_right</span>
                <span class="breadcrumb__current">Ekosistem</span>
            </div>
            <div class="buy-card">
                <h1 class="product-title">Ekosistem təyin edilməyib</h1>
                <p style="margin-top: 8px;">Bu məhsul üçün administrator tərəfindən əlavə tamamlayıcı məhsul yoxdur.</p>
                <a class="btn-primary" href="<?php echo e($detailUrl); ?>" style="display:inline-block; margin-top: 16px; text-decoration:none;">Məhsul səhifəsinə qayıt</a>
            </div>
        <?php else: ?>
            <?php
            $mainPrice = (float) $product['base_price'];
            $ecosystemMainInStock = (int) $product['stock_qty'] > 0;
            $ecosystemBundleSum = 0.0;
            $ecosystemBundleInStockCount = 0;
            foreach ($bundleProducts as $bpRow) {
                if ((int) $bpRow['stock_qty'] > 0) {
                    $ecosystemBundleSum += (float) $bpRow['base_price'];
                    $ecosystemBundleInStockCount++;
                }
            }
            $ecosystemTotalPrice = $mainPrice + $ecosystemBundleSum;
            $ecosystemTotalCount = 1 + $ecosystemBundleInStockCount;
            $chainParts = [(string) $product['name']];
            foreach ($bundleProducts as $bp) {
                $chainParts[] = (string) $bp['name'];
            }
            $ecosystemChainText = implode(' + ', $chainParts);
            ?>
            <div class="breadcrumb">
                <a href="<?php echo e($detailUrl); ?>"><?php echo e((string) $product['name']); ?></a>
                <span class="material-symbols-outlined" style="font-size: 0.875rem;">chevron_right</span>
                <span class="breadcrumb__current">Ekosistem</span>
            </div>
            <header class="ecosystem-page__header">
                <h1 class="ecosystem-title ecosystem-page__title">Ekosistem paketi</h1>
                <p class="ecosystem-chain ecosystem-page__chain"><?php echo e($ecosystemChainText); ?></p>
                <p class="ecosystem-intro">Bu səhifədə administratorun seçdiyi bütün tamamlayıcı məhsullar göstərilir. Detal səhifədə sıra təsadüfi ola bilər; burada tam siyahı sabit qaydada verilir.</p>
            </header>

            <section class="ecosystem-section ecosystem-page__section" id="ecosystem-full">
                <form class="ecosystem-bundle" method="post" action="">
                    <input type="hidden" name="ecosystem_parent_id" value="<?php echo (int) $product['id']; ?>">
                    <div class="ecosystem-items">
                        <div class="ecosystem-card ecosystem-card--main">
                            <img alt="<?php echo e($mainAlt); ?>" class="ecosystem-img" src="<?php echo e($mainImage); ?>" />
                            <span class="ecosystem-name">Əsas məhsul</span>
                            <p class="ecosystem-price"><?php echo number_format($mainPrice, 2); ?> ₼</p>
                            <a class="ecosystem-page__mini-link" href="<?php echo e($detailUrl); ?>">Məhsul təsviri</a>
                        </div>
                        <?php foreach ($bundleProducts as $bp): ?>
                            <span class="material-symbols-outlined ecosystem-plus" aria-hidden="true">add</span>
                            <?php
                            $bInStock = (int) $bp['stock_qty'] > 0;
                            $bImg = $bp['image_url'] !== null && $bp['image_url'] !== ''
                                ? (string) $bp['image_url']
                                : 'https://via.placeholder.com/320x240?text=No+Image';
                            $bPrice = (float) $bp['base_price'];
                            $bpUrl = !empty($bp['slug'])
                                ? 'product_details.php?slug=' . urlencode((string) $bp['slug'])
                                : 'product_details.php?id=' . (int) $bp['id'];
                            ?>
                            <div class="ecosystem-card<?php echo $bInStock ? '' : ' ecosystem-card--muted'; ?>">
                                <a href="<?php echo e($bpUrl); ?>">
                                    <img alt="<?php echo e((string) $bp['name']); ?>" class="ecosystem-img ecosystem-img--cover" src="<?php echo e($bImg); ?>" />
                                </a>
                                <div>
                                    <p class="ecosystem-name"><a class="ecosystem-page__mini-link" href="<?php echo e($bpUrl); ?>"><?php echo e((string) $bp['name']); ?></a></p>
                                    <p class="ecosystem-price"><?php echo number_format($bPrice, 2); ?> ₼</p>
                                    <?php if (!$bInStock): ?>
                                        <p class="ecosystem-stock-note">Stokta yok (pakete dahil edilmez)</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ecosystem-summary">
                        <div class="ecosystem-total-label">
                            <p><?php echo (int) $ecosystemTotalCount; ?> məhsul üçün toplam (stokda olanlar):</p>
                            <p class="ecosystem-total-price"><?php echo number_format($ecosystemTotalPrice, 2); ?> ₼</p>
                        </div>
                        <button class="btn-bundle" type="submit" name="ecosystem_cart" value="1" <?php echo $ecosystemMainInStock ? '' : 'disabled'; ?>>
                            <?php echo (int) $ecosystemTotalCount; ?> məhsulu səbətə əlavə et
                        </button>
                        <?php if (!$ecosystemMainInStock): ?>
                            <p class="ecosystem-stock-note ecosystem-stock-note--center">Əsas məhsul stokda olmadığı üçün paket əlavə edilə bilməz.</p>
                        <?php elseif ($ecosystemBundleInStockCount === 0 && count($bundleProducts) > 0): ?>
                            <p class="ecosystem-stock-note ecosystem-stock-note--center">Tamamlayıcı məhsullar stokda deyil; yalnız əsas məhsul səbətə əlavə olunacaq.</p>
                        <?php endif; ?>
                        <a class="ecosystem-page__back-link" href="<?php echo e($detailUrl); ?>">← Məhsul səhifəsinə qayıt</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <footer class="footer">
        <div class="footer__grid">
            <div class="footer__col">
                <div class="footer__logo">MAXHOME</div>
                <p class="footer__desc">Experience the future of personal computing through our Digital Concierge service.</p>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Support</h4>
                <div class="footer__links">
                    <a class="footer__link" href="support_center.php">Track Order</a>
                </div>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Privacy &amp; Legal</h4>
                <div class="footer__links">
                    <a class="footer__link" href="support_center.php">Privacy Policy</a>
                </div>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Newsletter</h4>
                <p class="footer__desc">Join the MAXHOME circle.</p>
            </div>
        </div>
        <div class="footer__bottom">
            <p>© 2024 MAXHOME Electronics.</p>
        </div>
    </footer>
</body>
</html>
