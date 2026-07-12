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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['ecosystem_cart'])) {
            if (!$product) {
                throw new RuntimeException('Məhsul tapılmadı.');
            }
            if (!productBundleItemsTableExists($pdo)) {
                throw new RuntimeException('Bu funksiya hələ hazır deyil (verilənlər bazası miqrasiyası lazımdır).');
            }
            $parentId = (int) ($_POST['ecosystem_parent_id'] ?? 0);
            if ($parentId !== (int) $product['id']) {
                throw new RuntimeException('Sorğu etibarsızdır.');
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
            header('Location: product_details.php' . $redirectQuery . '&ecosystem_added=1');
            exit;
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        addToCart($pdo, $productId, $quantity);

        if (isset($_POST['buy_now'])) {
            header('Location: checkout.php');
            exit;
        }

        $redirectQuery = isset($_GET['slug']) ? '?slug=' . urlencode($slug) : '?id=' . $productId;
        header('Location: product_details.php' . $redirectQuery . '&added=1');
        exit;
    } catch (Throwable $e) {
        $flashMessage = $e->getMessage();
        $flashType = 'error';
    }
}

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $flashMessage = 'Məhsul səbətə əlavə edildi.';
}

if (isset($_GET['ecosystem_added']) && $_GET['ecosystem_added'] === '1') {
    $flashMessage = 'Paket səbətə əlavə edildi (stokda olan məhsullar).';
}

$images = [];
$specs = [];
$visibleSpecs = [];
$bundleProducts = [];
$productRatingAvg = 0.0;
$productRatingCount = 0;
$productBasePrice = 0.0;
$productListPrice = null;
$installmentOptions = [];
$installmentHighlightMonths = 12;
$installmentHighlightMonthly = 0.0;
if ($product) {
    $ratingStats = maxhomeGetProductRatingStats(
        $pdo,
        (int) $product['id'],
        (float) ($product['rating_avg'] ?? 0),
        (int) ($product['rating_count'] ?? 0)
    );
    $productRatingAvg = $ratingStats['rating_avg'];
    $productRatingCount = $ratingStats['rating_count'];

    $priceDisplay = maxhomeProductPriceDisplay($product);
    $productBasePrice = $priceDisplay['base_price'];
    $productListPrice = $priceDisplay['list_price'];
    $installmentOptions = maxhomeProductInstallmentOptions($productBasePrice, $productListPrice);
    $installmentHighlightMonths = 12;
    $installmentHighlightMonthly = 0.0;
    foreach ($installmentOptions as $installmentOption) {
        if ((int) $installmentOption['months'] === $installmentHighlightMonths) {
            $installmentHighlightMonthly = (float) $installmentOption['monthly'];
            break;
        }
    }

    $imgStmt = $pdo->prepare("SELECT image_url, alt_text FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, sort_order ASC, id ASC");
    $imgStmt->execute(['pid' => (int) $product['id']]);
    $images = $imgStmt->fetchAll() ?: [];

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

    // Category mapindan kenar (custom) saheleri de sonda goster.
    foreach ($specs as $specRow) {
        $specKey = (string) ($specRow['spec_key'] ?? '');
        if ($specKey === '' || isset($usedSpecKeys[$specKey]) || maxhomeIsInternalProductSpecKey($specKey)) {
            continue;
        }
        $rawValue = trim((string) ($specRow['spec_value'] ?? ''));
        if ($rawValue === '') {
            continue;
        }
        $rendered = $renderSpecRow($specRow);
        if ($rendered !== null) {
            $visibleSpecs[] = $rendered;
        }
    }

    // Daha seliqeli gosterim ucun:
    // 1) Eyni etiketi tekrarlama (ilk dolu deyer saxlanilir),
    // 2) Cok qisa ve menasiz custom setrleri gizlet.
    $dedupedVisibleSpecs = [];
    foreach ($visibleSpecs as $row) {
        $label = trim((string) ($row['display_label'] ?? ''));
        $value = trim((string) ($row['display_value'] ?? ''));
        if ($label === '') {
            continue;
        }
        if ($value === '' || $value === '-') {
            continue;
        }

        $labelKey = mb_strtolower($label);
        $valueKey = mb_strtolower($value);

        // Mes: "sa = sa" kimi tesadufi custom setrler.
        if (mb_strlen($label) <= 2 && $labelKey === $valueKey) {
            continue;
        }

        if (!isset($dedupedVisibleSpecs[$labelKey])) {
            $dedupedVisibleSpecs[$labelKey] = [
                'display_label' => $label,
                'display_value' => $value,
            ];
            continue;
        }

        $existingValue = trim((string) ($dedupedVisibleSpecs[$labelKey]['display_value'] ?? ''));
        if ($existingValue === '' && $value !== '') {
            $dedupedVisibleSpecs[$labelKey]['display_value'] = $value;
        }
    }
    $visibleSpecs = array_values($dedupedVisibleSpecs);

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

$bundleProductsDisplay = $bundleProducts;
if (count($bundleProductsDisplay) > 1) {
    shuffle($bundleProductsDisplay);
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>MAXHOME - Premium Texnologiya Təcrübəsi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@300;400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/product_details.css">
</head>
<body>
    <?php $currentPage = 'laptops'; ?>
    <?php include 'navbar.php'; ?>
    <main class="main-content">
        <?php if ($flashMessage !== ''): ?>
            <div style="margin-bottom: 12px; padding: 12px; border-radius: 10px; color: #fff; background: <?php echo $flashType === 'error' ? '#b91c1c' : '#047857'; ?>;">
                <?php echo e($flashMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!$product): ?>
            <div class="buy-card">
                <h1 class="product-title">Məhsul tapılmadı</h1>
                <p style="margin-top: 8px;">Zəhmət olmasa məhsul linkini yoxlayın və ya kataloqa geri dönün.</p>
                <a class="btn-primary" href="shop_page.php" style="display:inline-block; margin-top: 16px; text-decoration:none;">Mağaza səhifəsi</a>
            </div>
        <?php else: ?>
            <?php
                $mainImage = $images[0]['image_url'] ?? 'https://via.placeholder.com/800x800?text=No+Image';
                $mainAlt = $images[0]['alt_text'] ?? $product['name'];
            ?>
            <div class="breadcrumb">
                <span><?php echo e($product['category_name'] ?: 'Kateqoriya'); ?></span>
                <span class="material-symbols-outlined" style="font-size: 0.875rem;">chevron_right</span>
                <span><?php echo e($product['brand_name'] ?: 'Brend'); ?></span>
                <span class="material-symbols-outlined" style="font-size: 0.875rem;">chevron_right</span>
                <span class="breadcrumb__current"><?php echo e($product['name']); ?></span>
            </div>
            <div class="product-hero">
                <div class="gallery">
                    <div class="gallery__main" id="product-gallery-main" data-gallery-count="<?php echo count($images); ?>" tabindex="0">
                        <img
                            alt="<?php echo e($mainAlt); ?>"
                            class="gallery__image"
                            id="product-main-image"
                            src="<?php echo e($mainImage); ?>"
                        />
                        <?php if (count($images) > 1): ?>
                            <button
                                type="button"
                                class="gallery__nav gallery__nav--prev"
                                id="product-gallery-prev"
                                aria-label="Əvvəlki şəkil">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </button>
                            <button
                                type="button"
                                class="gallery__nav gallery__nav--next"
                                id="product-gallery-next"
                                aria-label="Növbəti şəkil">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                            <div class="gallery__pagination" id="product-gallery-pagination" aria-live="polite">
                                <div class="gallery__dots" id="product-gallery-dots">
                                    <?php foreach ($images as $idx => $image): ?>
                                        <button
                                            type="button"
                                            class="gallery__dot<?php echo $idx === 0 ? ' gallery__dot--active' : ''; ?>"
                                            data-gallery-index="<?php echo $idx; ?>"
                                            aria-label="Şəkil <?php echo $idx + 1; ?>"
                                            <?php echo $idx === 0 ? 'aria-current="true"' : ''; ?>>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <span class="gallery__pagination-count" id="product-gallery-count">1 / <?php echo count($images); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="gallery__thumbs" id="product-gallery-thumbs">
                            <?php foreach ($images as $idx => $image): ?>
                                <button
                                    type="button"
                                    class="gallery__thumb<?php echo $idx === 0 ? ' gallery__thumb--active' : ''; ?>"
                                    data-gallery-index="<?php echo $idx; ?>"
                                    data-image-src="<?php echo e((string) $image['image_url']); ?>"
                                    data-image-alt="<?php echo e((string) ($image['alt_text'] ?: $product['name'])); ?>"
                                    aria-label="Şəkil <?php echo $idx + 1; ?>"
                                >
                                    <img alt="<?php echo e($image['alt_text'] ?: $product['name']); ?>" class="gallery__thumb-img" src="<?php echo e($image['image_url']); ?>" />
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div
                    class="gallery-lightbox"
                    id="gallery-lightbox"
                    hidden
                    aria-hidden="true">
                    <button type="button" class="gallery-lightbox__backdrop" id="gallery-lightbox-backdrop" aria-label="Bağla"></button>
                    <div class="gallery-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Böyüdülmüş şəkil">
                        <button type="button" class="gallery-lightbox__close" id="gallery-lightbox-close" aria-label="Bağla">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                        <?php if (count($images) > 1): ?>
                            <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--prev" id="gallery-lightbox-prev" aria-label="Əvvəlki şəkil">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </button>
                            <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--next" id="gallery-lightbox-next" aria-label="Növbəti şəkil">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        <?php endif; ?>
                        <img class="gallery-lightbox__image" id="gallery-lightbox-image" alt="<?php echo e($mainAlt); ?>" src="<?php echo e($mainImage); ?>" />
                        <?php if (count($images) > 1): ?>
                            <div class="gallery-lightbox__count" id="gallery-lightbox-count">1 / <?php echo count($images); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-info">
                    <header>
                        <div class="badge"><?php echo (int) $product['stock_qty'] > 0 ? 'STOKDA VAR' : 'STOKDA YOXDUR'; ?></div>
                        <h1 class="product-title"><?php echo e($product['name']); ?></h1>
                        <?php if ($productRatingCount > 0): ?>
                            <div class="rating">
                                <span class="rating__count">
                                    Reytinq: <?php echo number_format($productRatingAvg, 1); ?> (<?php echo $productRatingCount; ?> rəy)
                                </span>
                            </div>
                        <?php endif; ?>
                    </header>
                    <div class="buy-card">
                        <div class="price">
                            <?php if ($productListPrice !== null): ?>
                                <span class="price__compare"><?php echo number_format($productListPrice, 2); ?> ₼</span>
                                <span class="price__amount price__amount--sale"><?php echo number_format($productBasePrice, 2); ?> ₼</span>
                            <?php else: ?>
                                <span class="price__amount"><?php echo number_format($productBasePrice, 2); ?> ₼</span>
                            <?php endif; ?>
                        </div>
                        <?php
                        if ($productBasePrice > 0) {
                            require __DIR__ . '/includes/product_installment_section.php';
                        }
                        ?>
                        <form method="post" class="buy-card__form">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <div class="buy-card__description">
                                <p class="buy-card__description-label">Təsvir</p>
                                <div class="buy-card__description-box">
                                    <div class="buy-card__description-text product-rich-text">
                                        <?php
                                        $shortDescriptionHtml = maxhomeRenderProductHtml((string) ($product['short_description'] ?? ''));
                                        echo $shortDescriptionHtml !== ''
                                            ? $shortDescriptionHtml
                                            : e('Bu məhsul üçün qısa təsvir əlavə edilməyib.');
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="action-btns">
                                <button class="btn-primary" type="submit" name="add_to_cart" <?php echo (int) $product['stock_qty'] < 1 ? 'disabled' : ''; ?>>Səbətə əlavə et</button>
                                <button class="btn-secondary" type="submit" name="buy_now" <?php echo (int) $product['stock_qty'] < 1 ? 'disabled' : ''; ?>>İndi al</button>
                            </div>
                        </form>
                    </div>
                    <div class="concierge-box " >
                        <span class="material-symbols-outlined" style="font-size: 2rem;">verified_user</span>
                        <div>
                            <p class="concierge-box__title">Rəqəmsal konsyerj dəstəyi daxildir</p>
                            <p class="concierge-box__text"><?php echo e(maxhomeProductPlainText((string) ($product['short_description'] ?: '24/7 premium dəstək daxildir.'))); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tabs-section">
                <?php
                    $featureRows = [
                        ['label' => 'Brend', 'value' => (string) ($product['brand_name'] ?: 'MAXHOME')],
                        ['label' => 'Kateqoriya', 'value' => (string) ($product['category_name'] ?: 'Ümumi')],
                    ];
                    foreach ($visibleSpecs as $spec) {
                        $specLabel = trim((string) ($spec['display_label'] ?? ''));
                        $specValue = trim((string) ($spec['display_value'] ?? ''));
                        if ($specLabel === '' || $specValue === '' || $specValue === '-') {
                            continue;
                        }
                        $featureRows[] = [
                            'label' => $specLabel,
                            'value' => $specValue,
                        ];
                    }
                    if (empty($featureRows)) {
                        $featureRows[] = ['label' => 'Məlumat', 'value' => 'Xüsusiyyət daxil edilməyib.'];
                    }
                    $halfCount = (int) ceil(count($featureRows) / 2);
                    $leftFeatureRows = array_slice($featureRows, 0, $halfCount);
                    $rightFeatureRows = array_slice($featureRows, $halfCount);
                ?>
                <section class="features-card">
                    <header class="features-card__header">
                        <span class="material-symbols-outlined features-card__icon" aria-hidden="true">description</span>
                        <h3 class="features-card__title">Xüsusiyyətlər</h3>
                    </header>
                    <div class="features-grid">
                        <div class="features-col">
                            <?php foreach ($leftFeatureRows as $row): ?>
                                <div class="features-row">
                                    <span class="features-label"><?php echo e((string) ($row['label'] ?? '')); ?>:</span>
                                    <span class="features-value"><?php echo e((string) ($row['value'] ?? '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="features-col">
                            <?php foreach ($rightFeatureRows as $row): ?>
                                <div class="features-row">
                                    <span class="features-label"><?php echo e((string) ($row['label'] ?? '')); ?>:</span>
                                    <span class="features-value"><?php echo e((string) ($row['value'] ?? '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php if (!empty($product['description'])): ?>
                    <div class="product-rich-text product-description-content">
                        <?php echo maxhomeRenderProductHtml((string) $product['description']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($bundleProducts)): ?>
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
                $ecosystemChainParts = [(string) $product['name']];
                foreach ($bundleProductsDisplay as $bpChain) {
                    $ecosystemChainParts[] = (string) $bpChain['name'];
                }
                $ecosystemChainText = implode(' + ', $ecosystemChainParts);
                $ecosystemPageUrl = $slug !== ''
                    ? 'product_ecosystem.php?slug=' . urlencode($slug)
                    : 'product_ecosystem.php?id=' . (int) $product['id'];
                ?>
                <section class="ecosystem-section" id="ecosystem-section">
                    <h2 class="ecosystem-title">Ekosisteminizi tamamlayın</h2>
                    <p class="ecosystem-chain"><?php echo e($ecosystemChainText); ?></p>
                    <p class="ecosystem-intro">Tövsiyə olunan tamamlayıcı məhsullar bu paketlə birlikdə göstərilir. "Səbətə əlavə et" dedikdə <strong>stokda olanlar</strong> avtomatik əlavə edilir; stokda olmayanlar ötürülür.
                        <?php if (count($bundleProducts) > 1): ?>
                            <span class="ecosystem-intro-note">Birdən çox tamamlayıcı seçilibsə, kartların sırası hər yüklənmədə təsadüfi göstərilir; tam siyahı üçün aşağıdakı keçiddən istifadə edin.</span>
                        <?php endif; ?>
                    </p>
                    <p class="ecosystem-page-link-wrap">
                        <a class="ecosystem-full-page-link" href="<?php echo e($ecosystemPageUrl); ?>">Bütün ekosistem məhsulları — ayrıca səhifə</a>
                    </p>
                    <form class="ecosystem-bundle" method="post" action="">
                        <input type="hidden" name="ecosystem_parent_id" value="<?php echo (int) $product['id']; ?>">
                        <div class="ecosystem-items">
                            <div class="ecosystem-card ecosystem-card--main">
                                <img alt="<?php echo e($mainAlt); ?>" class="ecosystem-img" src="<?php echo e($mainImage); ?>" />
                                <span class="ecosystem-name">Bu məhsul</span>
                                <p class="ecosystem-price"><?php echo number_format($mainPrice, 2); ?> ₼</p>
                            </div>
                            <?php foreach ($bundleProductsDisplay as $bp): ?>
                                <span class="material-symbols-outlined ecosystem-plus" aria-hidden="true">add</span>
                                <?php
                                $bInStock = (int) $bp['stock_qty'] > 0;
                                $bImg = $bp['image_url'] !== null && $bp['image_url'] !== ''
                                    ? (string) $bp['image_url']
                                    : 'https://via.placeholder.com/320x240?text=No+Image';
                                $bPrice = (float) $bp['base_price'];
                                ?>
                                <div class="ecosystem-card<?php echo $bInStock ? '' : ' ecosystem-card--muted'; ?>">
                                    <img alt="<?php echo e((string) $bp['name']); ?>" class="ecosystem-img ecosystem-img--cover" src="<?php echo e($bImg); ?>" />
                                    <div>
                                        <p class="ecosystem-name"><?php echo e((string) $bp['name']); ?></p>
                                        <p class="ecosystem-price"><?php echo number_format($bPrice, 2); ?> ₼</p>
                                        <?php if (!$bInStock): ?>
                                            <p class="ecosystem-stock-note">Stokda yoxdur (paketə daxil edilmir)</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="ecosystem-summary">
                            <div class="ecosystem-total-label">
                                <p><?php echo (int) $ecosystemTotalCount; ?> məhsul üçün ümumi qiymət (stokda olanlar):</p>
                                <p class="ecosystem-total-price"><?php echo number_format($ecosystemTotalPrice, 2); ?> ₼</p>
                            </div>
                            <button class="btn-bundle" type="submit" name="ecosystem_cart" value="1" <?php echo $ecosystemMainInStock ? '' : 'disabled'; ?>>
                                <?php echo (int) $ecosystemTotalCount; ?> məhsulu səbətə əlavə et
                            </button>
                            <?php if (!$ecosystemMainInStock): ?>
                                <p class="ecosystem-stock-note ecosystem-stock-note--center">Ana məhsul stokda olmadığı üçün paket əlavə edilə bilməz.</p>
                            <?php elseif ($ecosystemBundleInStockCount === 0 && count($bundleProducts) > 0): ?>
                                <p class="ecosystem-stock-note ecosystem-stock-note--center">Tamamlayıcı məhsullar stokda deyil; yalnız ana məhsul səbətə əlavə ediləcək.</p>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <script>
    (() => {
        const mainImage = document.getElementById('product-main-image');
        const thumbsWrap = document.getElementById('product-gallery-thumbs');
        const prevBtn = document.getElementById('product-gallery-prev');
        const nextBtn = document.getElementById('product-gallery-next');
        const dotsWrap = document.getElementById('product-gallery-dots');
        const countLabel = document.getElementById('product-gallery-count');
        const galleryMain = document.getElementById('product-gallery-main');
        const lightbox = document.getElementById('gallery-lightbox');
        const lightboxImage = document.getElementById('gallery-lightbox-image');
        const lightboxClose = document.getElementById('gallery-lightbox-close');
        const lightboxBackdrop = document.getElementById('gallery-lightbox-backdrop');
        const lightboxPrev = document.getElementById('gallery-lightbox-prev');
        const lightboxNext = document.getElementById('gallery-lightbox-next');
        const lightboxCount = document.getElementById('gallery-lightbox-count');

        if (!mainImage || !galleryMain) {
            return;
        }

        const thumbs = thumbsWrap ? Array.from(thumbsWrap.querySelectorAll('.gallery__thumb')) : [];
        const galleryItems = thumbs.length > 0
            ? thumbs.map((thumb) => ({
                src: thumb.getAttribute('data-image-src') || '',
                alt: thumb.getAttribute('data-image-alt') || ''
            }))
            : [{
                src: mainImage.getAttribute('src') || '',
                alt: mainImage.getAttribute('alt') || ''
            }];

        const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.gallery__dot')) : [];
        let currentIndex = 0;
        let lastFocusedElement = null;

        function setActiveIndex(index) {
            const total = galleryItems.length;
            if (total === 0) {
                return;
            }

            currentIndex = (index + total) % total;
            const item = galleryItems[currentIndex];
            if (!item.src) {
                return;
            }

            mainImage.setAttribute('src', item.src);
            mainImage.setAttribute('alt', item.alt);

            thumbs.forEach((thumb, itemIndex) => {
                thumb.classList.toggle('gallery__thumb--active', itemIndex === currentIndex);
            });

            dots.forEach((dot, dotIndex) => {
                const isActive = dotIndex === currentIndex;
                dot.classList.toggle('gallery__dot--active', isActive);
                if (isActive) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });

            const countText = (currentIndex + 1) + ' / ' + total;
            if (countLabel) {
                countLabel.textContent = countText;
            }
            if (lightboxImage && lightbox && !lightbox.hidden) {
                lightboxImage.setAttribute('src', item.src);
                lightboxImage.setAttribute('alt', item.alt);
            }
            if (lightboxCount) {
                lightboxCount.textContent = countText;
            }
        }

        function isControlTarget(target) {
            return !!target.closest('.gallery__nav, .gallery__dot, .gallery__pagination, button');
        }

        function openLightbox() {
            if (!lightbox || !lightboxImage) {
                return;
            }

            const item = galleryItems[currentIndex];
            lightboxImage.setAttribute('src', item.src);
            lightboxImage.setAttribute('alt', item.alt);
            if (lightboxCount && galleryItems.length > 1) {
                lightboxCount.textContent = (currentIndex + 1) + ' / ' + galleryItems.length;
            }

            lastFocusedElement = document.activeElement;
            lightbox.hidden = false;
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.classList.add('gallery-lightbox-open');

            if (lightboxClose) {
                lightboxClose.focus();
            }
        }

        function closeLightbox() {
            if (!lightbox) {
                return;
            }

            lightbox.hidden = true;
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('gallery-lightbox-open');

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                const index = Number(thumb.getAttribute('data-gallery-index') || '0');
                setActiveIndex(index);
            });
        });

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                const index = Number(dot.getAttribute('data-gallery-index') || '0');
                setActiveIndex(index);
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                setActiveIndex(currentIndex - 1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                setActiveIndex(currentIndex + 1);
            });
        }

        galleryMain.addEventListener('click', (event) => {
            if (isControlTarget(event.target)) {
                return;
            }
            openLightbox();
        });

        galleryMain.addEventListener('keydown', (event) => {
            if (lightbox && !lightbox.hidden) {
                return;
            }
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openLightbox();
                return;
            }
            if (galleryItems.length <= 1) {
                return;
            }
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                setActiveIndex(currentIndex - 1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                setActiveIndex(currentIndex + 1);
            }
        });

        if (lightboxClose) {
            lightboxClose.addEventListener('click', closeLightbox);
        }
        if (lightboxBackdrop) {
            lightboxBackdrop.addEventListener('click', closeLightbox);
        }
        if (lightboxPrev) {
            lightboxPrev.addEventListener('click', (event) => {
                event.stopPropagation();
                setActiveIndex(currentIndex - 1);
            });
        }
        if (lightboxNext) {
            lightboxNext.addEventListener('click', (event) => {
                event.stopPropagation();
                setActiveIndex(currentIndex + 1);
            });
        }

        document.addEventListener('keydown', (event) => {
            if (!lightbox || lightbox.hidden) {
                return;
            }
            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft' && galleryItems.length > 1) {
                event.preventDefault();
                setActiveIndex(currentIndex - 1);
            } else if (event.key === 'ArrowRight' && galleryItems.length > 1) {
                event.preventDefault();
                setActiveIndex(currentIndex + 1);
            }
        });
    })();
    </script>
    <footer class="footer">
        <div class="footer__grid">
            <div class="footer__col">
                <div class="footer__logo">MAXHOME</div>
                <p class="footer__desc">Şəxsi hesablamanın gələcəyini rəqəmsal konsyerj xidmətimizlə yaşayın. Müasir baxış üçün premium alətlər hazırlayırıq.</p>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Dəstək</h4>
                <div class="footer__links">
                    <a class="footer__link" href="support_center.php">Sifarişi izlə</a>
                    <a class="footer__link" href="support_center.php">Çatdırılma</a>
                    <a class="footer__link" href="support_center.php">Geri qaytarma</a>
                </div>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Məxfilik və Hüquqi</h4>
                <div class="footer__links">
                    <a class="footer__link" href="support_center.php">Etibar mərkəzi</a>
                    <a class="footer__link" href="support_center.php">Məxfilik siyasəti</a>
                    <a class="footer__link" href="support_center.php">Cookie ayarları</a>
                </div>
            </div>
            <div class="footer__col">
                <h4 class="footer__title">Xəbər bülleteni</h4>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <p class="footer__desc">MAXHOME dairəsinə qoşulun və texnologiya yeniliklərinə erkən çıxış əldə edin.</p>
                    <div class="footer__newsletter-input">
                        <input class="footer__input" placeholder="E-poçt ünvanınız" type="email" />
                        <button class="footer__submit">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer__bottom">
            <p>© 2024 MAXHOME Electronics. Rəqəmsal konsyerj təcrübəsi.</p>
        </div>
    </footer>
</body>

</html>