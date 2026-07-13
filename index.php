<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/i18n.php';
maxhome_start_i18n_buffer();

$pdo = db();
$catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);

function homepageSlidesTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'homepage_slides' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();
    return $exists;
}

// Newsletter kaydı
$newsletterMessage = '';
$newsletterType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $email = trim((string) ($_POST['newsletter_email'] ?? ''));

    if ($email === '') {
        $newsletterMessage = 'Zəhmət olmasa email daxil edin.';
        $newsletterType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $newsletterMessage = 'Düzgün email ünvanı daxil edin.';
        $newsletterType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO newsletter_subscribers (email, source_page)
                 VALUES (:email, :source_page)
                 ON DUPLICATE KEY UPDATE is_active = 1"
            );
            $stmt->execute([
                'email' => $email,
                'source_page' => 'home',
            ]);
            $newsletterMessage = 'Newsletter-ə uğurla abunə oldunuz.';
            $newsletterType = 'success';
        } catch (Throwable $e) {
            $newsletterMessage = 'Abunəlik zamanı xəta baş verdi.';
            $newsletterType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        addToCart($pdo, $productId, $qty);
        header('Location: shopping_cart.php');
        exit;
    } catch (Throwable $e) {
        $cartFlashMessage = $e->getMessage();
        $cartFlashType = 'error';
    }
}

$cartFlashMessage = $cartFlashMessage ?? '';
$cartFlashType = $cartFlashType ?? 'error';

// Ana kateqoriyalar (3 ədəd)
$categoriesStmt = $pdo->query(
    "SELECT id, name, slug, description, image_url
     FROM categories
     WHERE is_active = 1 AND parent_id IS NULL
     ORDER BY sort_order ASC, name ASC
     LIMIT 3"
);
$heroCategories = $categoriesStmt->fetchAll() ?: [];

/**
 * Ana səhifə məhsul kartı üçün ortaq select sütunları.
 */
$maxhomeProductSelectSql = "
    p.id,
    p.slug,
    p.name,
    p.short_description,
    p.base_price,
    p.compare_at_price,
    p.stock_qty,
    p.rating_avg,
    p.rating_count,
    (SELECT image_url
     FROM product_images
     WHERE product_id = p.id
     ORDER BY is_primary DESC, sort_order ASC, id ASC
     LIMIT 1) AS image_url
";

/**
 * Kateqoriya slug-una görə aktiv məhsulları gətirir (alt kateqoriyalar daxil).
 *
 * @return list<array<string, mixed>>
 */
$maxhomeFetchByCategorySlug = static function (PDO $pdo, string $slug, int $limit, string $catalogOnlineSql, string $selectSql): array {
    $slug = trim($slug);
    if ($slug === '' || $limit < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "WITH RECURSIVE cat_tree AS (
            SELECT id FROM categories WHERE slug = :slug AND is_active = 1
            UNION ALL
            SELECT c.id
            FROM categories c
            INNER JOIN cat_tree t ON c.parent_id = t.id
            WHERE c.is_active = 1
        )
        SELECT {$selectSql}
        FROM products p
        WHERE p.status = 'active'{$catalogOnlineSql}
          AND p.category_id IN (SELECT id FROM cat_tree)
        ORDER BY p.created_at DESC, p.rating_avg DESC, p.id DESC
        LIMIT {$limit}"
    );
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetchAll() ?: [];
};

// Trending məhsullar - en çox satanlar (order_items əsasında TOP 12).
$trendingStmt = $pdo->query(
    "SELECT
        {$maxhomeProductSelectSql},
        COALESCE(SUM(oi.quantity), 0) AS sold_qty
     FROM products p
     LEFT JOIN order_items oi ON oi.product_id = p.id
     WHERE p.status = 'active'{$catalogOnlineSql}
     GROUP BY p.id, p.slug, p.name, p.short_description, p.base_price, p.compare_at_price, p.stock_qty, p.rating_avg, p.rating_count
     HAVING sold_qty > 0
     ORDER BY sold_qty DESC, p.rating_avg DESC, p.id DESC
     LIMIT 12"
);
$trendingProducts = $trendingStmt->fetchAll() ?: [];

if (count($trendingProducts) < 12) {
    $needed = 12 - count($trendingProducts);
    $existingIds = array_column($trendingProducts, 'id');
    $notInClause = '';
    if (!empty($existingIds)) {
        $safeIds = array_map('intval', $existingIds);
        $notInClause = 'AND p.id NOT IN (' . implode(',', $safeIds) . ')';
    }

    $fallbackSql = "
        SELECT
            {$maxhomeProductSelectSql},
            0 AS sold_qty
        FROM products p
        WHERE p.status = 'active'{$catalogOnlineSql} {$notInClause}
        ORDER BY p.created_at DESC, p.rating_avg DESC, p.id DESC
        LIMIT {$needed}
    ";
    $fallbackStmt = $pdo->query($fallbackSql);
    $fallbackProducts = $fallbackStmt->fetchAll() ?: [];
    $trendingProducts = array_merge($trendingProducts, $fallbackProducts);
}

$robotVacuumProducts = $maxhomeFetchByCategorySlug($pdo, 'robot-tozsoranlar', 12, $catalogOnlineSql, $maxhomeProductSelectSql);
$airConditionerProducts = $maxhomeFetchByCategorySlug($pdo, 'kondisionerler', 12, $catalogOnlineSql, $maxhomeProductSelectSql);

/**
 * Ana səhifə məhsul kartını render edir.
 *
 * @param array<string, mixed> $product
 */
$maxhomeRenderHomeProductCard = static function (array $product): void {
    $basePrice = (float) ($product['base_price'] ?? 0);
    $comparePrice = (float) ($product['compare_at_price'] ?? 0);
    $oldPrice = $comparePrice > $basePrice ? $comparePrice : null;
    $discountAmount = $oldPrice !== null ? ($oldPrice - $basePrice) : 0.0;
    $discountPct = ($oldPrice !== null && $oldPrice > 0)
        ? (int) round(($discountAmount / $oldPrice) * 100)
        : 0;
    $stockQty = (int) ($product['stock_qty'] ?? 0);
    $outOfStock = $stockQty < 1;
    ?>
    <article class="mh-card">
        <div class="mh-card__media">
            <?php if ($discountPct > 0): ?>
                <span class="mh-card__pct">-<?php echo $discountPct; ?>%</span>
            <?php endif; ?>
            <div class="mh-card__icons">
                <button
                    class="mh-card__icon mh-card__icon--compare js-product-compare-btn"
                    type="button"
                    aria-label="<?php echo e(t('product.compare')); ?>"
                    data-product-id="<?php echo (int) $product['id']; ?>"
                    data-product-slug="<?php echo e((string) $product['slug']); ?>"
                    data-product-name="<?php echo e((string) $product['name']); ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">balance</span>
                </button>
                <button
                    class="mh-card__icon mh-card__icon--wishlist js-product-wishlist-btn"
                    type="button"
                    aria-pressed="false"
                    aria-label="<?php echo e(t('product.add_wishlist')); ?>"
                    data-product-id="<?php echo (int) $product['id']; ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">favorite</span>
                </button>
            </div>
            <a class="mh-card__img-link" href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                <img
                    alt="<?php echo e((string) $product['name']); ?>"
                    class="mh-card__img"
                    src="<?php echo e((string) ($product['image_url'] ?: 'https://via.placeholder.com/600x600?text=Product')); ?>"
                    loading="lazy" />
            </a>
        </div>
        <h3 class="mh-card__title">
            <a href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                <?php echo e((string) $product['name']); ?>
            </a>
        </h3>
        <div class="mh-card__pricing">
            <?php if ($discountAmount > 0): ?>
                <span class="mh-card__save">-<?php echo number_format($discountAmount, 2); ?> ₼</span>
            <?php endif; ?>
            <?php if ($oldPrice !== null): ?>
                <span class="mh-card__old"><?php echo number_format($oldPrice, 2); ?> ₼</span>
            <?php endif; ?>
            <span class="mh-card__price"><?php echo number_format($basePrice, 2); ?> ₼</span>
        </div>
        <form method="post" class="mh-card__form">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
            <input type="hidden" name="quantity" value="1">
            <button
                class="mh-card__cart"
                type="submit"
                name="add_to_cart"
                <?php echo $outOfStock ? 'disabled' : ''; ?>>
                <span class="material-symbols-outlined">shopping_cart</span>
                <?php echo $outOfStock ? e(t('product.out_of_stock', 'Stokda yoxdur')) : e(t('product.add_to_cart')); ?>
            </button>
        </form>
    </article>
    <?php
};

/**
 * Ana səhifə məhsul carousel sırasını render edir.
 *
 * @param list<array<string, mixed>> $products
 */
$maxhomeRenderHomeRail = static function (string $title, array $products, callable $renderCard): void {
    if (empty($products)) {
        return;
    }
    ?>
    <div class="home-rail" data-home-rail>
        <div class="home-rail__head">
            <h2 class="home-rail__title"><?php echo e($title); ?></h2>
            <div class="home-rail__nav" aria-label="<?php echo e($title); ?>">
                <button class="home-rail__btn" type="button" data-rail-prev aria-label="Əvvəlki">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button class="home-rail__btn" type="button" data-rail-next aria-label="Növbəti">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>
        <div class="home-rail__viewport">
            <div class="home-rail__track" data-rail-track>
                <?php foreach ($products as $product): ?>
                    <?php $renderCard($product); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
};

$homeProductRails = [
    [
        'title' => t('home.rail_robot', 'Robot Tozsoranlar'),
        'products' => $robotVacuumProducts,
    ],
    [
        'title' => t('home.rail_bestsellers', 'Ən çox satılanlar'),
        'products' => $trendingProducts,
    ],
    [
        'title' => t('home.rail_ac', 'Evinizə uyğun sərinlik'),
        'products' => $airConditionerProducts,
    ],
];

// Homepage setting: Flash sale config
$settingsStmt = $pdo->query(
    "SELECT setting_key, setting_value
     FROM site_settings
     WHERE setting_key IN ('flash_start_at','flash_end_at','flash_product_id','flash_product_ids','flash_hide_on_expire')"
);
$settingsRows = $settingsStmt->fetchAll() ?: [];
$settings = [];
foreach ($settingsRows as $r) {
    $settings[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
}
$flashStartAt = (string) ($settings['flash_start_at'] ?? '');
$flashEndAt = (string) ($settings['flash_end_at'] ?? '');
$flashProductId = (int) ($settings['flash_product_id'] ?? 0);
$flashProductIdsRaw = (string) ($settings['flash_product_ids'] ?? '');
$flashHideOnExpire = ($settings['flash_hide_on_expire'] ?? '1') !== '0';

$nowTs = time();
$startTs = $flashStartAt !== '' ? strtotime($flashStartAt) : false;
$endTs = $flashEndAt !== '' ? strtotime($flashEndAt) : false;
$flashWindowConfigured = $startTs !== false && $endTs !== false && $endTs > $startTs;
$flashIsActive = $flashWindowConfigured && $nowTs >= (int) $startTs && $nowTs < (int) $endTs;
$flashIsExpired = $flashWindowConfigured && $nowTs >= (int) $endTs;

// Flash deal məhsulları: admin seçibsə onları göstər, yoxsa auto seç.
/** @var list<int> $flashSelectedIds */
$flashSelectedIds = [];
if ($flashProductIdsRaw !== '') {
    $parts = preg_split('/[,\s]+/', $flashProductIdsRaw) ?: [];
    foreach ($parts as $part) {
        $pid = (int) trim((string) $part);
        if ($pid > 0) {
            $flashSelectedIds[$pid] = $pid;
        }
    }
}
if (empty($flashSelectedIds) && $flashProductId > 0) {
    $flashSelectedIds[$flashProductId] = $flashProductId;
}
$flashSelectedIds = array_values($flashSelectedIds);

/** @var list<array<string, mixed>> $flashDeals */
$flashDeals = [];
if (!empty($flashSelectedIds)) {
    $placeholders = implode(',', array_fill(0, count($flashSelectedIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT
            p.id,
            p.slug,
            p.name,
            p.base_price,
            p.compare_at_price,
            p.rating_avg,
            p.rating_count,
            p.stock_qty,
            (SELECT image_url
             FROM product_images
             WHERE product_id = p.id
             ORDER BY is_primary DESC, sort_order ASC, id ASC
             LIMIT 1) AS image_url
         FROM products p
         WHERE p.status = 'active'{$catalogOnlineSql} AND p.id IN ({$placeholders})"
    );
    $stmt->execute($flashSelectedIds);
    $rows = $stmt->fetchAll() ?: [];
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) ($row['id'] ?? 0)] = $row;
    }
    foreach ($flashSelectedIds as $sid) {
        if (isset($byId[$sid])) {
            $flashDeals[] = $byId[$sid];
        }
    }
} else {
    $flashStmt = $pdo->query(
        "SELECT
            p.id,
            p.slug,
            p.name,
            p.base_price,
            p.compare_at_price,
            p.rating_avg,
            p.rating_count,
            p.stock_qty,
            (SELECT image_url
             FROM product_images
             WHERE product_id = p.id
             ORDER BY is_primary DESC, sort_order ASC, id ASC
             LIMIT 1) AS image_url
         FROM products p
         WHERE
            p.status = 'active'{$catalogOnlineSql}
            AND p.stock_qty > 0
            AND p.compare_at_price IS NOT NULL
            AND p.compare_at_price > p.base_price
            AND (p.compare_at_price - p.base_price) / p.compare_at_price >= 0.15
         ORDER BY (p.compare_at_price - p.base_price) DESC, p.created_at DESC, p.rating_avg DESC
         LIMIT 4"
    );
    $flashDeals = $flashStmt->fetchAll() ?: [];
}

// Hero üçün vurğulanan məhsullar (slider üçün)
$heroStmt = $pdo->query(
    "SELECT
        p.id,
        p.slug,
        p.name,
        p.short_description,
        (SELECT image_url
         FROM product_images
         WHERE product_id = p.id
         ORDER BY is_primary DESC, sort_order ASC, id ASC
         LIMIT 1) AS image_url
     FROM products p
     WHERE p.status = 'active'{$catalogOnlineSql}
     ORDER BY p.rating_avg DESC, p.rating_count DESC, p.created_at DESC, p.id DESC
     LIMIT 5"
);
$heroProducts = $heroStmt->fetchAll() ?: [];
$heroSlideCount = max(1, count($heroProducts));

// Admin slider slaydları (əgər cədvəl varsa və aktiv slaydlar mövcuddursa, onlardan istifadə et)
$homepageSlides = [];
if (homepageSlidesTableExists($pdo)) {
    $homepageSlides = $pdo->query(
        "SELECT
            id, badge_text, title, body_text,
            primary_label, primary_url,
            secondary_label, secondary_url,
            image_url
         FROM homepage_slides
         WHERE is_active = 1
         ORDER BY sort_order ASC, id DESC"
    )->fetchAll() ?: [];
}

// Marka siyahısı (Brands bölümü üçün)
$brandsStmt = $pdo->query(
    "SELECT name
     FROM brands
     ORDER BY name ASC
     LIMIT 12"
);
$brands = $brandsStmt->fetchAll() ?: [];

// Why Choose Us üçün xüsusiyyətlər
$featuresStmt = $pdo->query(
    "SELECT icon, title, body
     FROM site_features
     WHERE is_active = 1
     GROUP BY icon, title, body
     ORDER BY MIN(sort_order) ASC, MIN(id) ASC"
);
$siteFeatures = $featuresStmt->fetchAll() ?: [];

// Footer linkləri
$footerLinksStmt = $pdo->query(
    "SELECT section, label, url
     FROM footer_links
     WHERE is_active = 1
     GROUP BY section, label, url
     ORDER BY section ASC, MIN(sort_order) ASC, MIN(id) ASC"
);
$footerLinks = $footerLinksStmt->fetchAll() ?: [];

// DB-dən gələn statik mətnləri də aktiv dilə çevir (xüsusilə AZ görünüşü üçün).
$phraseMap = maxhome_current_lang() === 'en' ? maxhome_phrase_map_az_to_en() : maxhome_phrase_map_en_to_az();
$translateDynamicText = static function (string $text) use ($phraseMap): string {
    $trimmed = trim($text);
    if ($trimmed === '' || $phraseMap === []) {
        return $text;
    }
    if (isset($phraseMap[$trimmed])) {
        return $phraseMap[$trimmed];
    }
    return strtr($text, $phraseMap);
};

foreach ($siteFeatures as &$featureRow) {
    $featureRow['title'] = $translateDynamicText((string) ($featureRow['title'] ?? ''));
    $featureRow['body'] = $translateDynamicText((string) ($featureRow['body'] ?? ''));
}
unset($featureRow);

foreach ($footerLinks as &$footerLinkRow) {
    $footerLinkRow['label'] = $translateDynamicText((string) ($footerLinkRow['label'] ?? ''));
}
unset($footerLinkRow);

foreach ($homepageSlides as &$slideRow) {
    $slideRow['badge_text'] = $translateDynamicText((string) ($slideRow['badge_text'] ?? ''));
    $slideRow['title'] = $translateDynamicText((string) ($slideRow['title'] ?? ''));
    $slideRow['body_text'] = $translateDynamicText((string) ($slideRow['body_text'] ?? ''));
    $slideRow['primary_label'] = $translateDynamicText((string) ($slideRow['primary_label'] ?? ''));
    $slideRow['secondary_label'] = $translateDynamicText((string) ($slideRow['secondary_label'] ?? ''));
}
unset($slideRow);

?>
<!DOCTYPE html>

<html lang="<?php echo e(maxhome_current_lang()); ?>">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>MAXHOME | <?php echo e(t('footer.copy')); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/product_compare.css">
</head>

<body>
    <?php $currentPage = 'home'; ?>
    <?php include 'navbar.php'; ?>
    <main>
        <!-- Hero -->
        <section class="hero">
            <div class="hero-slider" data-hero-slider>
                <div class="hero-slides">
                    <?php if (!empty($homepageSlides)): ?>
                        <?php foreach ($homepageSlides as $slideIndex => $slide): ?>
                            <?php
                            $badge = (string) ($slide['badge_text'] ?? '');
                            $title = (string) ($slide['title'] ?? '');
                            $body = (string) ($slide['body_text'] ?? '');
                            $primaryLabel = (string) ($slide['primary_label'] ?? '');
                            $primaryUrl = (string) ($slide['primary_url'] ?? '');
                            $secondaryLabel = (string) ($slide['secondary_label'] ?? '');
                            $secondaryUrl = (string) ($slide['secondary_url'] ?? '');
                            $heroDeviceImg = maxhome_public_asset_url((string) ($slide['image_url'] ?? ''));
                            ?>
                            <article class="hero-slide <?php echo $slideIndex === 0 ? 'is-active' : ''; ?>" data-hero-slide>
                                <div class="hero-container">
                                    <div class="hero-content">
                                        <span class="badge"><?php echo e($badge !== '' ? $badge : t('hero.badge')); ?></span>
                                        <h1 class="hero-title"><?php echo e($title !== '' ? $title : t('hero.fallback_title')); ?></h1>
                                        <p class="hero-description">
                                            <?php echo e($body !== '' ? $body : t('hero.fallback_description')); ?>
                                        </p>
                                        <div class="btn-group">
                                            <?php if ($primaryLabel !== '' && $primaryUrl !== ''): ?>
                                                <a class="btn btn--primary" href="<?php echo e($primaryUrl); ?>">
                                                    <?php echo e($primaryLabel); ?>
                                                </a>
                                            <?php else: ?>
                                                <a class="btn btn--primary" href="shop_page.php">
                                                    <?php echo e(t('hero.shop_now')); ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($secondaryLabel !== '' && $secondaryUrl !== ''): ?>
                                                <a class="btn btn--secondary" href="<?php echo e($secondaryUrl); ?>">
                                                    <?php echo e($secondaryLabel); ?>
                                                </a>
                                            <?php else: ?>
                                                <a class="btn btn--secondary" href="shop_page.php?sort=rating">
                                                    <?php echo e(t('hero.view_deals')); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="hero-visual">
                                        <div class="visual-wrapper">
                                            <div class="visual-blob"></div>
                                            <div class="visual-glass"></div>
                                            <?php include __DIR__ . '/includes/hero_device_art.php'; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php elseif (!empty($heroProducts)): ?>
                        <?php foreach ($heroProducts as $slideIndex => $heroProduct): ?>
                            <?php
                            $heroDeviceImg = maxhome_public_asset_url((string) ($heroProduct['image_url'] ?? ''));
                            ?>
                            <article class="hero-slide <?php echo $slideIndex === 0 ? 'is-active' : ''; ?>" data-hero-slide>
                                <div class="hero-container">
                                    <div class="hero-content">
                                        <span class="badge"><?php echo e(t('hero.badge')); ?></span>
                                        <h1 class="hero-title">
                                            <?php echo e((string) $heroProduct['name']); ?>
                                        </h1>
                                        <p class="hero-description">
                                            <?php echo e(maxhomeProductPlainText((string) ($heroProduct['short_description'] ?: t('hero.default_short_description')))); ?>
                                        </p>
                                        <div class="btn-group">
                                            <a class="btn btn--primary" href="product_details.php?slug=<?php echo urlencode((string) $heroProduct['slug']); ?>">
                                                <?php echo e(t('hero.shop_now')); ?>
                                            </a>
                                            <a class="btn btn--secondary" href="shop_page.php?sort=rating">
                                                <?php echo e(t('hero.view_deals')); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="hero-visual">
                                        <div class="visual-wrapper">
                                            <div class="visual-blob"></div>
                                            <div class="visual-glass"></div>
                                            <?php include __DIR__ . '/includes/hero_device_art.php'; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php $heroDeviceImg = ''; ?>
                        <article class="hero-slide is-active" data-hero-slide>
                            <div class="hero-container">
                                <div class="hero-content">
                                    <span class="badge"><?php echo e(t('hero.badge')); ?></span>
                                    <h1 class="hero-title"><?php echo t('hero.fallback_title'); ?></h1>
                                    <p class="hero-description">
                                        <?php echo e(t('hero.fallback_description')); ?>
                                    </p>
                                    <div class="btn-group">
                                        <a class="btn btn--primary" href="shop_page.php">
                                            <?php echo e(t('hero.shop_now')); ?>
                                        </a>
                                        <a class="btn btn--secondary" href="shop_page.php?sort=rating">
                                            <?php echo e(t('hero.view_deals')); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="hero-visual">
                                    <div class="visual-wrapper">
                                        <div class="visual-blob"></div>
                                        <div class="visual-glass"></div>
                                        <?php include __DIR__ . '/includes/hero_device_art.php'; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>
                <?php
                $effectiveSlideCount = !empty($homepageSlides) ? count($homepageSlides) : $heroSlideCount;
                ?>
                <?php if ($effectiveSlideCount > 1): ?>
                    <div class="hero-controls" aria-label="Hero slider controls">
                        <button class="hero-control-btn" type="button" data-hero-prev aria-label="Əvvəlki slayd">
                            <span class="material-symbols-outlined">chevron_left</span>
                        </button>
                        <div class="hero-dots">
                            <?php for ($dotIndex = 0; $dotIndex < $effectiveSlideCount; $dotIndex++): ?>
                                <button
                                    class="hero-dot <?php echo $dotIndex === 0 ? 'is-active' : ''; ?>"
                                    type="button"
                                    data-hero-dot="<?php echo $dotIndex; ?>"
                                    aria-label="Slayd <?php echo $dotIndex + 1; ?>"
                                    aria-current="<?php echo $dotIndex === 0 ? 'true' : 'false'; ?>">
                                </button>
                            <?php endfor; ?>
                        </div>
                        <button class="hero-control-btn" type="button" data-hero-next aria-label="Növbəti slayd">
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <!-- Categories -->
        <section class="section-padding container">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><?php echo e(t('categories.title')); ?></h2>
                    <p class="section-subtitle"><?php echo e(t('categories.subtitle')); ?></p>
                </div>
                <a class="text-link" href="categories.php"><?php echo e(t('categories.view_all')); ?> <span class="material-symbols-outlined" style="margin-left: 0.5rem;">arrow_forward</span></a>
            </div>
            <div class="category-grid">
                <?php if (!empty($heroCategories)): ?>
                    <?php foreach ($heroCategories as $index => $category): ?>
                        <a
                            class="category-card <?php echo $index === 0 ? 'col-span-2' : ''; ?>"
                            href="shop_page.php?<?php echo http_build_query(['category_slug' => (string) ($category['slug'] ?? '')]); ?>">
                            <img
                                alt="<?php echo e((string) $category['name']); ?>"
                                class="category-img"
                                src="<?php echo e((string) ($category['image_url'] ?: ('https://via.placeholder.com/800x600?text=' . urlencode((string) $category['name'])))); ?>" />
                            <div class="category-overlay"></div>
                            <div class="category-content">
                                <h3><?php echo e((string) $category['name']); ?></h3>
                                <p><?php echo e((string) ($category['description'] ?? t('categories.default_desc'))); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php echo e(t('categories.empty')); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <!-- Flash Deals -->
        <section class="flash-deals" id="flash-sale" data-hide-on-expire="<?php echo $flashHideOnExpire ? '1' : '0'; ?>" data-flash-active="<?php echo $flashIsActive ? '1' : '0'; ?>" data-flash-expired="<?php echo $flashIsExpired ? '1' : '0'; ?>">
            <div class="flash-container">
                <div class="flash-info">
                    <div class="flash-blob"></div>
                    <h2 class="flash-title"><?php echo t('flash.title'); ?></h2>
                    <div class="countdown" data-flash-start="<?php echo e($flashStartAt); ?>" data-flash-end="<?php echo e($flashEndAt); ?>">
                        <div class="timer-unit">
                            <div class="timer-box" id="flash-hours">00</div>
                            <div class="timer-label"><?php echo e(t('flash.hours')); ?></div>
                        </div>
                        <div class="timer-sep">:</div>
                        <div class="timer-unit">
                            <div class="timer-box" id="flash-minutes">00</div>
                            <div class="timer-label"><?php echo e(t('flash.minutes')); ?></div>
                        </div>
                        <div class="timer-sep">:</div>
                        <div class="timer-unit">
                            <div class="timer-box" id="flash-seconds">00</div>
                            <div class="timer-label"><?php echo e(t('flash.seconds')); ?></div>
                        </div>
                    </div>
                    <a class="btn btn--white" id="flash-cta" href="shop_page.php?sort=price_low"><?php echo e(t('flash.explore')); ?></a>
                </div>
                <div>
                    <div class="deal-card">
                        <div class="flash-expired-badge" id="flash-expired-badge" aria-hidden="true"><?php echo e(t('flash.expired')); ?></div>
                        <?php if (!empty($flashDeals)): ?>
                            <?php foreach ($flashDeals as $flashDeal): ?>
                                <?php
                                $discountPercent = 0;
                                if (!empty($flashDeal['compare_at_price']) && (float) $flashDeal['compare_at_price'] > 0) {
                                    $discountPercent = round(
                                        (1 - ((float) $flashDeal['base_price'] / (float) $flashDeal['compare_at_price'])) * 100
                                    );
                                }
                                ?>
                                <div class="deal-entry">
                                    <a class="deal-item" href="product_details.php?slug=<?php echo urlencode((string) $flashDeal['slug']); ?>">
                                        <div class="deal-thumb">
                                            <img
                                                alt="<?php echo e((string) $flashDeal['name']); ?>"
                                                src="<?php echo e((string) ($flashDeal['image_url'] ?: 'https://via.placeholder.com/400x400?text=Deal')); ?>" />
                                        </div>
                                        <div>
                                            <?php if ($discountPercent > 0): ?>
                                                <span class="deal-tag"><?php echo e(t('flash.save')); ?> <?php echo $discountPercent; ?>%</span>
                                            <?php endif; ?>
                                            <h4 class="deal-name"><?php echo e((string) $flashDeal['name']); ?></h4>
                                            <div class="deal-prices">
                                                <span class="price-now"><?php echo number_format((float) $flashDeal['base_price'], 2); ?> ₼</span>
                                                <?php if (!empty($flashDeal['compare_at_price'])): ?>
                                                    <span class="price-old"><?php echo number_format((float) $flashDeal['compare_at_price'], 2); ?> ₼</span>
                                                <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <div class="progress-bar">
                                        <div class="progress-fill"></div>
                                    </div>
                                    <div class="deal-stats">
                                        <span><?php echo e(t('flash.rating')); ?>: <?php echo number_format((float) $flashDeal['rating_avg'], 1); ?> (<?php echo (int) $flashDeal['rating_count']; ?>)</span>
                                        <span><?php echo e(t('flash.left')); ?>: <?php echo (int) $flashDeal['stock_qty']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="padding: 1rem;"><?php echo e(t('flash.empty')); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <!-- Məhsul carousel sıraları -->
        <section class="home-rails container">
            <?php if ($cartFlashMessage !== ''): ?>
                <p class="trending-flash trending-flash--<?php echo e($cartFlashType); ?>"><?php echo e($cartFlashMessage); ?></p>
            <?php endif; ?>
            <?php
            $hasAnyRail = false;
            foreach ($homeProductRails as $rail) {
                if (!empty($rail['products'])) {
                    $hasAnyRail = true;
                    $maxhomeRenderHomeRail((string) $rail['title'], $rail['products'], $maxhomeRenderHomeProductCard);
                }
            }
            if (!$hasAnyRail):
            ?>
                <p class="home-rails__empty"><?php echo e(t('trending.empty')); ?></p>
            <?php endif; ?>
        </section>
        <script>
            (function() {
                const elHours = document.getElementById('flash-hours');
                const elMinutes = document.getElementById('flash-minutes');
                const elSeconds = document.getElementById('flash-seconds');
                const countdown = document.querySelector('.countdown');
                const flashSection = document.getElementById('flash-sale');
                const cta = document.getElementById('flash-cta');
                const expiredBadge = document.getElementById('flash-expired-badge');
                if (!elHours || !elMinutes || !elSeconds || !countdown || !flashSection) return;

                const rawStart = countdown.getAttribute('data-flash-start') || '';
                const rawEnd = countdown.getAttribute('data-flash-end') || '';
                const hideOnExpire = flashSection.getAttribute('data-hide-on-expire') === '1';

                if (!rawStart || !rawEnd) {
                    elHours.textContent = '00';
                    elMinutes.textContent = '00';
                    elSeconds.textContent = '00';
                    if (hideOnExpire) {
                        flashSection.style.display = 'none';
                    }
                    if (expiredBadge) expiredBadge.style.display = 'none';
                    return;
                }

                // raw format: "YYYY-MM-DD HH:MM:SS" (MySQL DATETIME)
                const start = new Date(rawStart.replace(' ', 'T'));
                const end = new Date(rawEnd.replace(' ', 'T'));
                if (isNaN(start.getTime()) || isNaN(end.getTime()) || end.getTime() <= start.getTime()) {
                    elHours.textContent = '00';
                    elMinutes.textContent = '00';
                    elSeconds.textContent = '00';
                    if (hideOnExpire) {
                        flashSection.style.display = 'none';
                    }
                    if (expiredBadge) expiredBadge.style.display = 'none';
                    return;
                }

                function pad2(n) {
                    return String(n).padStart(2, '0');
                }

                function tick() {
                    const now = new Date();
                    const isActive = now.getTime() >= start.getTime() && now.getTime() < end.getTime();
                    const isExpired = now.getTime() >= end.getTime();

                    if (!isActive) {
                        elHours.textContent = '00';
                        elMinutes.textContent = '00';
                        elSeconds.textContent = '00';
                        if (isExpired && hideOnExpire) {
                            flashSection.style.display = 'none';
                        }
                        if (isExpired && !hideOnExpire) {
                            flashSection.classList.add('is-expired');
                            if (expiredBadge) expiredBadge.style.display = 'inline-flex';
                            if (cta) {
                                cta.textContent = <?php echo json_encode(t('flash.expired'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                                cta.setAttribute('aria-disabled', 'true');
                                cta.removeAttribute('href');
                                cta.style.pointerEvents = 'none';
                            }
                        }
                        return;
                    }

                    // Active state: ensure UI is interactive
                    flashSection.classList.remove('is-expired');
                    if (expiredBadge) expiredBadge.style.display = 'none';
                    if (cta) {
                        cta.textContent = <?php echo json_encode(t('flash.explore'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                        if (!cta.getAttribute('href')) {
                            cta.setAttribute('href', 'shop_page.php?sort=price_low');
                        }
                        cta.removeAttribute('aria-disabled');
                        cta.style.pointerEvents = '';
                    }

                    let diff = Math.floor((end.getTime() - now.getTime()) / 1000);
                    if (diff < 0) diff = 0;

                    const hours = Math.floor(diff / 3600);
                    diff -= hours * 3600;
                    const minutes = Math.floor(diff / 60);
                    const seconds = diff - minutes * 60;

                    elHours.textContent = pad2(hours);
                    elMinutes.textContent = pad2(minutes);
                    elSeconds.textContent = pad2(seconds);
                }

                tick();
                setInterval(tick, 1000);
            })();
        </script>
        <!-- Brands -->
        <section class="brands">
            <div class="container brands-list">
                <?php if (!empty($brands)): ?>
                    <?php foreach ($brands as $brand): ?>
                        <span class="brand-item">
                            <?php echo strtoupper((string) $brand['name']); ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="brand-item">MAXHOME</span>
                <?php endif; ?>
            </div>
        </section>
        <!-- Why Choose Us -->
        <section class="features">
            <div class="features-grid">
                <?php if (!empty($siteFeatures)): ?>
                    <?php foreach ($siteFeatures as $feature): ?>
                        <div class="feature-item">
                            <div class="feature-icon-box">
                                <span class="material-symbols-outlined feature-icon">
                                    <?php echo e((string) $feature['icon']); ?>
                                </span>
                            </div>
                            <h4 class="feature-title">
                                <?php echo e((string) $feature['title']); ?>
                            </h4>
                            <p class="feature-text">
                                <?php echo e((string) $feature['body']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="feature-item">
                        <div class="feature-icon-box"><span class="material-symbols-outlined feature-icon">verified</span></div>
                        <h4 class="feature-title"><?php echo e(t('features.fallback_title')); ?></h4>
                        <p class="feature-text"><?php echo e(t('features.fallback_text')); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <h4>MAXHOME</h4>
                <p><?php echo e(t('footer.brand_text')); ?></p>
                <div class="footer-social">
                    <a class="social-link" href="support_center.php"><span class="material-symbols-outlined">public</span></a>
                    <a class="social-link" href="support_center.php"><span class="material-symbols-outlined">alternate_email</span></a>
                </div>
            </div>
            <div class="footer-col">
                <h5><?php echo e(t('footer.explore')); ?></h5>
                <ul class="footer-list">
                    <?php foreach ($footerLinks as $link): ?>
                        <?php if ($link['section'] === 'explore'): ?>
                            <li>
                                <a class="footer-link" href="<?php echo e((string) $link['url']); ?>">
                                    <?php echo e((string) $link['label']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h5><?php echo e(t('footer.assistance')); ?></h5>
                <ul class="footer-list">
                    <?php foreach ($footerLinks as $link): ?>
                        <?php if ($link['section'] === 'assistance'): ?>
                            <li>
                                <a class="footer-link" href="<?php echo e((string) $link['url']); ?>">
                                    <?php echo e((string) $link['label']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h5><?php echo e(t('footer.legal')); ?></h5>
                <ul class="footer-list">
                    <?php foreach ($footerLinks as $link): ?>
                        <?php if ($link['section'] === 'legal'): ?>
                            <li>
                                <a class="footer-link" href="<?php echo e((string) $link['url']); ?>">
                                    <?php echo e((string) $link['label']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="copyright">© 2024 <?php echo e(t('footer.copy')); ?></p>
        </div>
    </footer>
<script>
    (function() {
        const slider = document.querySelector('[data-hero-slider]');
        if (!slider) {
            return;
        }

        const slides = Array.from(slider.querySelectorAll('[data-hero-slide]'));
        if (slides.length <= 1) {
            return;
        }

        const prevBtn = slider.querySelector('[data-hero-prev]');
        const nextBtn = slider.querySelector('[data-hero-next]');
        const dots = Array.from(slider.querySelectorAll('[data-hero-dot]'));
        const intervalMs = 6000;
        let currentIndex = 0;
        let autoTimer = null;

        function goToSlide(index) {
            const normalizedIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === normalizedIndex);
            });
            dots.forEach((dot, dotIndex) => {
                const isActive = dotIndex === normalizedIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
            currentIndex = normalizedIndex;
        }

        function startAutoSlide() {
            stopAutoSlide();
            autoTimer = window.setInterval(function() {
                goToSlide(currentIndex + 1);
            }, intervalMs);
        }

        function stopAutoSlide() {
            if (autoTimer !== null) {
                window.clearInterval(autoTimer);
                autoTimer = null;
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                goToSlide(currentIndex - 1);
                startAutoSlide();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                goToSlide(currentIndex + 1);
                startAutoSlide();
            });
        }

        dots.forEach((dot, dotIndex) => {
            dot.addEventListener('click', function() {
                goToSlide(dotIndex);
                startAutoSlide();
            });
        });

        slider.addEventListener('mouseenter', stopAutoSlide);
        slider.addEventListener('mouseleave', startAutoSlide);
        slider.addEventListener('focusin', stopAutoSlide);
        slider.addEventListener('focusout', startAutoSlide);

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoSlide();
            } else {
                startAutoSlide();
            }
        });

        startAutoSlide();
    })();
</script>
<script src="assets/js/product_compare.js"></script>
<script>
(function () {
    document.querySelectorAll('[data-home-rail]').forEach(function (rail) {
        var track = rail.querySelector('[data-rail-track]');
        var prevBtn = rail.querySelector('[data-rail-prev]');
        var nextBtn = rail.querySelector('[data-rail-next]');
        if (!track || !prevBtn || !nextBtn) {
            return;
        }

        function scrollAmount() {
            var card = track.querySelector('.mh-card');
            if (!card) {
                return track.clientWidth * 0.8;
            }
            var styles = window.getComputedStyle(track);
            var gap = parseFloat(styles.columnGap || styles.gap || '16') || 16;
            return card.getBoundingClientRect().width + gap;
        }

        function updateButtons() {
            var maxScroll = track.scrollWidth - track.clientWidth - 2;
            prevBtn.disabled = track.scrollLeft <= 2;
            nextBtn.disabled = track.scrollLeft >= maxScroll;
        }

        prevBtn.addEventListener('click', function () {
            track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
        });
        nextBtn.addEventListener('click', function () {
            track.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
        });
        track.addEventListener('scroll', updateButtons, { passive: true });
        window.addEventListener('resize', updateButtons);
        updateButtons();
    });

    var WISHLIST_KEY = 'maxhome_wishlist_ids';

    function readWishlist() {
        try {
            var raw = localStorage.getItem(WISHLIST_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (error) {
            return [];
        }
    }

    function writeWishlist(ids) {
        localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
    }

    function syncWishlistButtons() {
        var ids = readWishlist();
        document.querySelectorAll('.js-product-wishlist-btn').forEach(function (btn) {
            var id = String(btn.dataset.productId || '');
            var active = id !== '' && ids.indexOf(id) !== -1;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('.js-product-wishlist-btn');
        if (!(btn instanceof HTMLElement)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        var id = String(btn.dataset.productId || '');
        if (id === '') {
            return;
        }
        var ids = readWishlist();
        var index = ids.indexOf(id);
        if (index === -1) {
            ids.push(id);
        } else {
            ids.splice(index, 1);
        }
        writeWishlist(ids);
        syncWishlistButtons();
    });

    syncWishlistButtons();
})();
</script>
</body>

</html>