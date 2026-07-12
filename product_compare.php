<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/product_compare.php';

maxhome_start_i18n_buffer();

$pdo = db();
$slugA = trim((string) ($_GET['a'] ?? ''));
$slugB = trim((string) ($_GET['b'] ?? ''));
$placeholderImage = 'assets/defaultP.jpg';

$productA = $slugA !== '' ? maxhomeFetchProductForCompare($pdo, $slugA) : null;
$productB = $slugB !== '' ? maxhomeFetchProductForCompare($pdo, $slugB) : null;

$compareRows = [];
$generalRows = [];
$specRows = [];
$diffCount = 0;

if ($productA && $productB) {
    $compareRows = maxhomeBuildFullCompareRows($productA, $productB, $pdo);
    $generalRows = array_slice($compareRows, 0, 5);
    $specRows = array_slice($compareRows, 5);
    foreach ($compareRows as $row) {
        if (!empty($row['diff'])) {
            $diffCount++;
        }
    }
}

$pageTitle = 'Məhsul müqayisəsi';

/**
 * @param array<string, mixed> $product
 * @return array{image:string,price:array{base_price:float,list_price:?float},in_stock:bool}
 */
function maxhomeCompareProductMeta(array $product, string $placeholderImage): array
{
    $image = trim((string) ($product['image_url'] ?? ''));

    return [
        'image' => $image !== '' ? $image : $placeholderImage,
        'price' => maxhomeProductPriceDisplay($product),
        'in_stock' => (int) ($product['stock_qty'] ?? 0) > 0,
    ];
}

/**
 * @param list<array{label:string,left:string,right:string,diff:bool}> $rows
 */
function maxhomeRenderCompareRows(array $rows): void
{
    foreach ($rows as $row): ?>
        <div class="compare-row<?php echo !empty($row['diff']) ? ' compare-row--diff' : ''; ?>">
            <div class="compare-row__label">
                <?php echo e($row['label']); ?>
                <?php if (!empty($row['diff'])): ?>
                    <span class="compare-row__badge">Fərqli</span>
                <?php endif; ?>
            </div>
            <div class="compare-row__values">
                <div class="compare-row__value compare-row__value--left<?php echo !empty($row['diff']) ? ' is-different' : ''; ?>">
                    <?php echo e($row['left']); ?>
                </div>
                <div class="compare-row__value compare-row__value--right<?php echo !empty($row['diff']) ? ' is-different' : ''; ?>">
                    <?php echo e($row['right']); ?>
                </div>
            </div>
        </div>
    <?php endforeach;
}
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo e($pageTitle); ?> | MAXHOME</title>
    <meta name="description" content="Seçilmiş iki məhsulu xüsusiyyətləri ilə müqayisə edin." />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/product_compare.css">
</head>

<body class="compare-body">
    <?php include 'navbar.php'; ?>

    <main class="compare-page">
        <div class="compare-page__inner">
            <header class="compare-page__head">
                <a class="compare-page__back" href="shop_page.php">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Mağazaya qayıt
                </a>
                <div class="compare-page__head-main">
                    <div>
                        <p class="compare-page__eyebrow">MAXHOME · Müqayisə</p>
                        <h1 class="compare-page__title"><?php echo e($pageTitle); ?></h1>
                        <p class="compare-page__subtitle">Seçilmiş məhsulların qiymət, stok və texniki göstəricilərini yan-yana müqayisə edin.</p>
                    </div>
                    <?php if ($productA && $productB): ?>
                        <div class="compare-page__stats">
                            <div class="compare-stat">
                                <span class="compare-stat__value"><?php echo count($compareRows); ?></span>
                                <span class="compare-stat__label">Göstərici</span>
                            </div>
                            <div class="compare-stat compare-stat--accent">
                                <span class="compare-stat__value"><?php echo $diffCount; ?></span>
                                <span class="compare-stat__label">Fərq</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (!$productA || !$productB): ?>
                <div class="compare-empty">
                    <span class="material-symbols-outlined compare-empty__icon">balance</span>
                    <h2>Müqayisə üçün məhsul tapılmadı</h2>
                    <p>İki məhsul seçin və ya mağazaya qayıdın.</p>
                    <a class="compare-empty__btn" href="shop_page.php">Mağazaya keç</a>
                </div>
            <?php else: ?>
                <?php
                $metaA = maxhomeCompareProductMeta($productA, $placeholderImage);
                $metaB = maxhomeCompareProductMeta($productB, $placeholderImage);
                $products = [
                    ['product' => $productA, 'meta' => $metaA, 'side' => 'left'],
                    ['product' => $productB, 'meta' => $metaB, 'side' => 'right'],
                ];
                ?>
                <section class="compare-board">
                    <div class="compare-products">
                        <?php foreach ($products as $index => $item): ?>
                            <?php
                            $product = $item['product'];
                            $meta = $item['meta'];
                            $price = $meta['price'];
                            ?>
                            <?php if ($index === 1): ?>
                                <div class="compare-vs" aria-hidden="true">
                                    <span>VS</span>
                                </div>
                            <?php endif; ?>
                            <article class="compare-product compare-product--<?php echo e($item['side']); ?>">
                                <div class="compare-product__image-wrap">
                                    <img
                                        class="compare-product__image"
                                        src="<?php echo e($meta['image']); ?>"
                                        alt="<?php echo e((string) $product['name']); ?>"
                                        loading="lazy" />
                                </div>
                                <div class="compare-product__body">
                                    <div class="compare-product__chips">
                                        <span class="compare-chip"><?php echo e((string) ($product['brand_name'] ?: 'MAXHOME')); ?></span>
                                        <span class="compare-chip compare-chip--<?php echo $meta['in_stock'] ? 'stock' : 'out'; ?>">
                                            <?php echo $meta['in_stock'] ? 'Stokda var' : 'Stokda yoxdur'; ?>
                                        </span>
                                    </div>
                                    <h2 class="compare-product__title">
                                        <a href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                                            <?php echo e((string) $product['name']); ?>
                                        </a>
                                    </h2>
                                    <p class="compare-product__category"><?php echo e((string) ($product['category_name'] ?: 'Ümumi')); ?></p>
                                    <div class="compare-product__rating">
                                        <span class="material-symbols-outlined compare-product__star">star</span>
                                        <?php echo number_format((float) ($product['rating_avg'] ?? 0), 1); ?>
                                        <span class="compare-product__rating-count">(<?php echo (int) ($product['rating_count'] ?? 0); ?> rəy)</span>
                                    </div>
                                    <div class="compare-product__price-block">
                                        <?php if ($price['list_price'] !== null): ?>
                                            <span class="compare-product__price-old"><?php echo number_format($price['list_price'], 2); ?> ₼</span>
                                        <?php endif; ?>
                                        <span class="compare-product__price"><?php echo number_format($price['base_price'], 2); ?> ₼</span>
                                    </div>
                                    <a class="compare-product__link" href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                                        Məhsula bax
                                        <span class="material-symbols-outlined">arrow_forward</span>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="compare-specs">
                        <div class="compare-specs__section">
                            <div class="compare-specs__heading">
                                <span class="material-symbols-outlined">info</span>
                                <h3>Ümumi məlumat</h3>
                            </div>
                            <div class="compare-rows">
                                <?php maxhomeRenderCompareRows($generalRows); ?>
                            </div>
                        </div>

                        <?php if ($specRows !== []): ?>
                            <div class="compare-specs__section">
                                <div class="compare-specs__heading">
                                    <span class="material-symbols-outlined">tune</span>
                                    <h3>Texniki xüsusiyyətlər</h3>
                                </div>
                                <div class="compare-rows">
                                    <?php maxhomeRenderCompareRows($specRows); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer class="compare-footer">
        <p>© <?php echo date('Y'); ?> MAXHOME</p>
    </footer>
</body>

</html>
