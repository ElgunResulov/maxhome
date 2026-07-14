<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = db();
$catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);
$currentPage = 'wishlist';

$idsRaw = trim((string) ($_GET['ids'] ?? ''));
$requestedIds = [];
if ($idsRaw !== '') {
    foreach (preg_split('/[,\s]+/', $idsRaw) ?: [] as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $requestedIds[$id] = $id;
        }
    }
}
$requestedIds = array_values($requestedIds);

/** @var list<array<string, mixed>> $wishlistProducts */
$wishlistProducts = [];
if (!empty($requestedIds)) {
    $placeholders = implode(',', array_fill(0, count($requestedIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT
            p.id,
            p.slug,
            p.name,
            p.base_price,
            p.compare_at_price,
            (SELECT image_url
             FROM product_images
             WHERE product_id = p.id
             ORDER BY is_primary DESC, sort_order ASC, id ASC
             LIMIT 1) AS image_url
         FROM products p
         WHERE p.status = 'active'{$catalogOnlineSql} AND p.id IN ({$placeholders})"
    );
    $stmt->execute($requestedIds);
    $rows = $stmt->fetchAll() ?: [];
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) ($row['id'] ?? 0)] = $row;
    }
    foreach ($requestedIds as $sid) {
        if (isset($byId[$sid])) {
            $wishlistProducts[] = $byId[$sid];
        }
    }
}

$hasIdsParam = array_key_exists('ids', $_GET);
?>
<!DOCTYPE html>
<html lang="<?php echo e(maxhome_current_lang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAXHOME | <?php echo e(t('nav.wishlist', 'Seçilmişlər')); ?></title>
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .wishlist-page {
            max-width: 80rem;
            margin: 0 auto;
            padding: 1.25rem clamp(1rem, 4vw, 2rem) 2rem;
        }
        .wishlist-page__title {
            font-family: 'Inter', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 0 1rem;
            color: #111827;
        }
        .wishlist-page__empty {
            padding: 2rem 1rem;
            text-align: center;
            color: #6b7280;
            background: #fff;
            border: 1px solid #e8eaee;
            border-radius: 12px;
        }
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.85rem;
        }
        .wishlist-card {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            text-decoration: none;
            color: inherit;
            background: #fff;
            border: 1px solid #e8eaee;
            border-radius: 12px;
            padding: 0.75rem;
        }
        .wishlist-card__img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: contain;
            background: #f8f9fb;
            border-radius: 8px;
        }
        .wishlist-card__name {
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.35;
            color: #1f2937;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .wishlist-card__price {
            font-size: 0.95rem;
            font-weight: 800;
            color: #111827;
        }
        .wishlist-card__old {
            margin-left: 0.35rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: #9ca3af;
            text-decoration: line-through;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="wishlist-page">
    <h1 class="wishlist-page__title"><?php echo e(t('nav.wishlist', 'Seçilmişlər')); ?></h1>
    <?php if (!$hasIdsParam): ?>
        <p class="wishlist-page__empty" id="wishlist-boot">Yüklənir...</p>
        <script>
            (function () {
                try {
                    var raw = localStorage.getItem('maxhome_wishlist_ids');
                    var ids = raw ? JSON.parse(raw) : [];
                    if (!Array.isArray(ids)) ids = [];
                    ids = ids.map(String).filter(Boolean);
                    var qs = ids.length ? ('?ids=' + encodeURIComponent(ids.join(','))) : '?ids=';
                    window.location.replace('wishlist.php' + qs);
                } catch (e) {
                    window.location.replace('wishlist.php?ids=');
                }
            })();
        </script>
    <?php elseif (empty($wishlistProducts)): ?>
        <p class="wishlist-page__empty">Seçilmiş məhsul yoxdur.</p>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlistProducts as $product): ?>
                <?php
                $price = (float) $product['base_price'];
                $compare = !empty($product['compare_at_price']) ? (float) $product['compare_at_price'] : 0.0;
                ?>
                <a class="wishlist-card" href="product_details.php?slug=<?php echo urlencode((string) $product['slug']); ?>">
                    <img
                        class="wishlist-card__img"
                        src="<?php echo e((string) ($product['image_url'] ?: 'https://via.placeholder.com/300x300?text=MAXHOME')); ?>"
                        alt="<?php echo e((string) $product['name']); ?>"
                        loading="lazy"
                        width="300"
                        height="300"
                    >
                    <div class="wishlist-card__name"><?php echo e((string) $product['name']); ?></div>
                    <div class="wishlist-card__price">
                        <?php echo number_format($price, 2); ?> ₼
                        <?php if ($compare > $price): ?>
                            <span class="wishlist-card__old"><?php echo number_format($compare, 2); ?> ₼</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
