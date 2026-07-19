<?php
require_once __DIR__ . '/db.php';

$defaultCategories = [
    ['name' => 'Smartfonlar və planşetlər', 'slug' => 'smartfonlar-ve-planshetler', 'description' => 'Smartfon, planşet, saat və qulaqlıqlar.'],
    ['name' => 'Noutbuklar və kompüter texnikası', 'slug' => 'noutbuklar-ve-komp', 'description' => 'Noutbuk, PC, monitor və ofis texnikası.'],
    ['name' => 'TV, audio və video', 'slug' => 'tv-audio-video', 'description' => 'Televizor, səs və video.'],
    ['name' => 'Oyun konsolları və aksesuarlar', 'slug' => 'oyun-konsollar', 'description' => 'Konsollar və oyun aksesuarları.'],
    ['name' => 'Böyük məişət texnikası', 'slug' => 'boyuk-meiset', 'description' => 'Soyuducu, paltaryuyan və mətbəx texnikası.'],
    ['name' => 'Kiçik məişət texnikası', 'slug' => 'kicik-meiset', 'description' => 'Mətbəx köməkçiləri və təmizlik.'],
];

$categoryCards = [];
try {
    $stmt = db()->query(
        "SELECT name, slug, description
         FROM categories
         WHERE is_active = 1 AND parent_id IS NULL
         ORDER BY sort_order ASC, name ASC
         LIMIT 6"
    );
    $categoryCards = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    $categoryCards = [];
}

for ($i = count($categoryCards); $i < count($defaultCategories); $i++) {
    $categoryCards[] = $defaultCategories[$i];
}

$cardLayouts = [
    [
        'span' => 'span-8-2',
        'template' => 'image',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCeg4E7aVOumJ_FMWFPiPqlekargKa_GH_7i6_zKs729RDA2VbI-I8H-Jz8ORtwirG4cUA9udAYvO2uiSHv0dMoLiMxzpRgTpkcb0hOT0pPZrFk0O045ulcNTpDeBXZqWv3ZnWpRrySkcnWO8hBc0Ldhwte0MMdiCwqrpYco3eFgXAubdptivU6iSqw_NEuNt8iAG1SdcoaB9O_uyXgg6zDlsQAos77FhqgPrTdnQyC-EF7WeeibubdV09YlgMv-AV4vXV14yALEXbi',
        'image_alt' => 'Close-up of sleek modern smartphones with glass and metallic finishes arranged elegantly on a dark stone surface with dramatic lighting',
    ],
    [
        'span' => 'span-4-2',
        'template' => 'image',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAL7ZQrmFz7GKYhKMrzqujEyt1BYQrCFshvHqJSKb-Wgs3bNT7ofrf2C5IKy47K90-jor9Vrg2b3kL3V8CX-QOLjr3hadzNcVwsEHlPLNq_695HQDDaKTLXqe8C707KFeoNBPprzKLgCm4W65bv7G4a9k9mnGgMiGZTC8qTCmXQRxD63E63yGY7a6jbw2PYK3dcWa51nh1GrddU0iy7RDwVb9liA48cIe4dhcV4CGHSQDv53U5Pind8KARP_ZcUY6A64Yzjs_9pc8tq',
        'image_alt' => 'Premium over-ear headphones resting on a minimalist wooden stand, soft studio lighting highlighting the leather and metallic details',
    ],
    [
        'span' => 'span-4-3',
        'template' => 'image',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDOVLkY-s5DuvGNv3zoejKu3GJLdhOX8xhHuFdyQYPt2Ut5hG9Jd6v3Xy6B0rEP11GCF_7M4zP5SwdrIlaXN8GPdjtj23SJomZQ1py_W9_TURsJ5ZL5pAoTueyK6xNcOEU7BVwU1qb_qpt0562ygt7Cr1Wn8QSE-20EBiPfnJyBOio96DeZ1Z03We2S8_yrIIltyeYXUIBAtFw_KZWV4kH-WauPkwuL1eYcs0Y4x0frgu1Yo86gNDbP3oDIGRJO434KvJ6cdpw_m_CF',
        'image_alt' => 'Sleek aluminum laptop open on a clean white desk next to a ceramic cup and notebook, bright natural light coming from a nearby window',
    ],
    [
        'span' => 'span-8-1',
        'template' => 'gaming',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfgrVC059bAAO5juNXNEssKqDEYRI26b_OKmnZtaPeA5LQjIoR7_Hj1vBoU3Wnt5Jh6U8vNzPX3oVpQ6RMIHMyqJLbJzshAAorPt3nY2vQ4IgrYBoJAOJN7KjvAGtx74vI9OJbAbxJ7XC1Cy4aehboLd3NTHlnQBPpp4IJYJpFLHERiKj96tiZUQDJWm_nEpr1Tx05vAzWK8xkBb1eiS1EhcWtvDrIPiOZHs69mhTii8l2Z2VgXAKSNDu7VvjQXccd-5mw6W3q2fdi',
        'image_alt' => 'Modern gaming console and controller in a dark room with neon violet and blue accent lighting, cinematic atmosphere',
    ],
    [
        'span' => 'span-4-2',
        'template' => 'image',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDWnqrR7kKoyEAtl49AUrBNis-fpZk9XWud6pWzxiFbZYVYksTbUVaCFG24MO3KS_kTcQLCqbYyfcBuG_QqGkm3B2Rc-u2wCo-R_pnsnzQUX0Hmu8j7SZsdqfYj_lsT0AJv37TKECD3LeGOUV4dTOz3lFlymiIzwvpu3csjYiKouRMJ9Y0oMf3aY_43vK8WhdiZtGXqslE8roaZyWjFOvOaDzsKEVKaIjV9Ggr79ey_z8OLkSaPBKiqk_qh4SAt1ieN9DjnDyFLWabj',
        'image_alt' => 'Modern minimalist living room featuring integrated smart home tech, automated blinds, and smart lighting system in a warm evening setting',
    ],
    [
        'span' => 'span-4-2',
        'template' => 'image',
        'image_src' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBA0ot6aci9BveZK8rw3rHXU1u6QM6xlbHqW-QAV9knaQ83h88xyCPKHZcn7W3jZhxpaZFbOJSl71qo9sRSrXAwVPQ729rmEg2F0MoDVYwstmJ_u-XhaNXEe5krjx6PTvDLrN5cFfttKJz2mN9XDMBW1wasc9Uf8XoW4FFd4EVdNZooN1cOK0oYldKaS7xDXXwUgYOvQUQk-voFxpaHolXv1IzFql2soJAmDoUzv3iAYfe3Worr4zooqGl_c5r7athLtKf67nqNzfek',
        'image_alt' => 'Close-up of a high-end smartwatch with a metallic band and vibrant screen showing health metrics, soft focus background',
    ],
];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&amp;family=Manrope:wght@400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/categories.css">
</head>
<body class="categories-page">
    <?php $currentPage = 'categories'; ?>
    <?php include 'navbar.php'; ?>

    <main class="categories-main">
        <div class="categories-intro">
            <h1 class="categories-title">
                Explore Our <br /><span class="text-on-primary-container">Ecosystem</span>
            </h1>
            <p class="categories-subtitle">
                Curated collections of world-class electronics, designed to elevate your lifestyle with seamless connectivity and performance.
            </p>
        </div>

        <div class="mosaic-grid">
            <?php foreach ($categoryCards as $index => $card): ?>
                <?php
                $layout = $cardLayouts[$index] ?? $cardLayouts[0];
                $categoryUrl = 'shop_page.php?' . http_build_query(['category_slug' => (string) ($card['slug'] ?? '')]);
                ?>
                <div class="mosaic-item <?php echo e($layout['span']); ?> group">
                    <?php if ($layout['template'] === 'gaming'): ?>
                        <div class="category-gaming">
                            <div class="category-gaming__copy">
                                <h2 class="category-gaming__title"><?php echo e($card['name']); ?></h2>
                                <p class="category-gaming__description"><?php echo e($card['description'] ?? 'Explore this category.'); ?></p>
                            </div>
                            <img alt="<?php echo e($layout['image_alt']); ?>" class="category-gaming__image" width="720" height="480" loading="lazy" decoding="async" src="<?php echo e($layout['image_src']); ?>" />
                        </div>
                        <a class="category-gaming__link" href="<?php echo e($categoryUrl); ?>">
                            <span class="category-round-icon material-symbols-outlined">chevron_right</span>
                        </a>
                    <?php elseif ($index === 0): ?>
                        <img alt="<?php echo e($layout['image_alt']); ?>" class="category-image" width="1200" height="800" decoding="async" fetchpriority="high" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="category-card-content">
                            <div class="glass-tag category-pill">
                                <span class="category-eyebrow"><?php echo e(strtoupper((string) ($card['slug'] ?? 'category'))); ?></span>
                            </div>
                            <h2 class="category-card-title"><?php echo e($card['name']); ?></h2>
                            <div class="category-card-actions">
                                <a class="category-button category-button--light" href="<?php echo e($categoryUrl); ?>">Explore</a>
                                <a class="category-button category-button--glass" href="<?php echo e($categoryUrl); ?>">Shop</a>
                            </div>
                        </div>
                    <?php elseif ($index === 1): ?>
                        <img alt="<?php echo e($layout['image_alt']); ?>" class="category-image" width="900" height="900" decoding="async" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="category-card-content">
                            <h2 class="category-card-title category-card-title--small"><?php echo e($card['name']); ?></h2>
                            <p class="category-card-description"><?php echo e($card['description'] ?? 'Explore this collection.'); ?></p>
                            <a class="category-button category-button--primary" href="<?php echo e($categoryUrl); ?>">Explore</a>
                        </div>
                    <?php elseif ($index === 2): ?>
                        <img alt="<?php echo e($layout['image_alt']); ?>" class="category-image" width="900" height="1200" loading="lazy" decoding="async" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/5 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="category-card-content">
                            <h2 class="category-card-title category-card-title--small"><?php echo e($card['name']); ?></h2>
                            <ul class="category-feature-list">
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> Featured collection</li>
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> Best sellers</li>
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> New arrivals</li>
                            </ul>
                            <a class="category-button category-button--light" href="<?php echo e($categoryUrl); ?>">View All</a>
                        </div>
                    <?php elseif ($index === 4): ?>
                        <img alt="<?php echo e($layout['image_alt']); ?>" class="category-image" width="900" height="900" loading="lazy" decoding="async" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="category-card-content category-card-content--top">
                            <span class="category-top-label"><?php echo e(strtoupper((string) $card['name'])); ?></span>
                            <div class="category-card-bottom">
                                <h3 class="category-card-subtitle"><?php echo e($card['description'] ?? 'Discover more products.'); ?></h3>
                                <div class="category-progress"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <img alt="<?php echo e($layout['image_alt']); ?>" class="category-image" width="900" height="900" loading="lazy" decoding="async" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="category-card-content category-card-content--center">
                            <h2 class="category-card-title category-card-title--small"><?php echo e($card['name']); ?></h2>
                            <p class="category-card-slug"><?php echo e(strtoupper((string) ($card['slug'] ?? 'category'))); ?></p>
                            <a class="category-button category-button--outline" href="<?php echo e($categoryUrl); ?>">Shop Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="concierge">
            <div class="concierge__content">
                <h2 class="concierge__title">The Concierge Service</h2>
                <p class="concierge__description">
                    Not sure what you need? Our digital experts are here to curate a personalized bundle tailored to your specific workstation or entertainment needs.
                </p>
                <button class="concierge__button">Get Started</button>
            </div>
            <div class="concierge__orb"></div>
            <div class="concierge__icon-wrap">
                <span class="material-symbols-outlined concierge__icon">support_agent</span>
            </div>
        </section>
    </main>

    <footer class="categories-footer">
        <div class="categories-footer__inner">
            <div class="categories-footer__brand">
                <div class="categories-footer__logo">MAXHOME</div>
                <p class="categories-footer__copy">© 2026 | Developed by INTBAKU LLC</p>
            </div>
            <div class="categories-footer__links">
                <a href="#">Support</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Shipping</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </footer>
    <script>
        (function () {
            'use strict';

            document.addEventListener('DOMContentLoaded', function () {
                var root = document.documentElement;
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (!root.classList.contains('dark') && prefersDark) {
                    root.classList.remove('light');
                    root.classList.add('dark');
                }

                var categoryImages = document.querySelectorAll('.category-image[data-alt]');
                categoryImages.forEach(function (img) {
                    var altText = img.getAttribute('data-alt');
                    if (!img.getAttribute('alt') && altText) {
                        img.setAttribute('alt', altText);
                    }
                });

                var mosaicItems = document.querySelectorAll('.mosaic-item');
                mosaicItems.forEach(function (item) {
                    var link = item.querySelector('a[href]');
                    if (!link) {
                        return;
                    }

                    if (!item.hasAttribute('tabindex')) {
                        item.setAttribute('tabindex', '0');
                    }
                    item.setAttribute('role', 'link');
                    item.setAttribute('aria-label', link.textContent.trim() || 'Open category');

                    item.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            link.click();
                        }
                    });
                });

                var conciergeButton = document.querySelector('section button');
                if (conciergeButton) {
                    conciergeButton.addEventListener('click', function () {
                        window.location.href = 'shop_page.php';
                    });
                }

                var observer = new IntersectionObserver(
                    function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                observer.unobserve(entry.target);
                            }
                        });
                    },
                    {
                        threshold: 0.12
                    }
                );

                mosaicItems.forEach(function (item) {
                    observer.observe(item);
                });
            });
        })();
    </script>
</body>
</html>
