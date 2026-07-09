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
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&amp;family=Manrope:wght@400;500;600&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/categories.css">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "inverse-on-surface": "#f1f1f1",
                        "secondary": "#525f75",
                        "on-background": "#1a1c1c",
                        "tertiary": "#381300",
                        "secondary-fixed": "#d6e3fe",
                        "on-primary-fixed": "#001b3c",
                        "on-secondary-fixed-variant": "#3b475d",
                        "surface-container-highest": "#e2e2e2",
                        "primary-container": "#003366",
                        "inverse-primary": "#a7c8ff",
                        "secondary-fixed-dim": "#bac7e1",
                        "primary-fixed": "#d5e3ff",
                        "on-error-container": "#93000a",
                        "surface-variant": "#e2e2e2",
                        "primary": "#001e40",
                        "on-secondary-container": "#58657c",
                        "on-primary-container": "#799dd6",
                        "on-error": "#ffffff",
                        "primary-fixed-dim": "#a7c8ff",
                        "error": "#ba1a1a",
                        "surface-dim": "#dadada",
                        "inverse-surface": "#2f3131",
                        "on-tertiary": "#ffffff",
                        "tertiary-container": "#592300",
                        "tertiary-fixed": "#ffdbca",
                        "on-tertiary-fixed-variant": "#723610",
                        "on-surface-variant": "#43474f",
                        "outline": "#737780",
                        "on-tertiary-container": "#d8885c",
                        "surface-container": "#eeeeee",
                        "surface": "#f9f9f9",
                        "on-tertiary-fixed": "#341100",
                        "surface-tint": "#3a5f94",
                        "outline-variant": "#c3c6d1",
                        "tertiary-fixed-dim": "#ffb690",
                        "on-surface": "#1a1c1c",
                        "on-secondary": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "on-secondary-fixed": "#0f1c2f",
                        "surface-container-low": "#f4f3f3",
                        "surface-bright": "#f9f9f9",
                        "background": "#f9f9f9",
                        "surface-container-high": "#e8e8e8",
                        "error-container": "#ffdad6",
                        "on-primary": "#ffffff",
                        "secondary-container": "#d6e3fe",
                        "on-primary-fixed-variant": "#1f477b"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "1rem",
                        "xl": "1.5rem",
                        "full": "9999px"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Manrope"],
                        "label": ["Inter"]
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-surface font-body text-on-surface">
    <?php $currentPage = 'categories'; ?>
    <?php include 'navbar.php'; ?>

    <main class="pt-28 sm:pt-32 pb-16 sm:pb-20 px-4 sm:px-6 md:px-8 max-w-screen-2xl mx-auto">
        <div class="mb-16">
            <h1 class="text-display-lg font-headline font-bold text-primary mb-4 leading-tight tracking-tighter">
                Explore Our <br /><span class="text-on-primary-container">Ecosystem</span>
            </h1>
            <p class="text-title-lg text-secondary max-w-2xl font-body">
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
                        <div class="absolute inset-0 flex items-center justify-between px-12 bg-surface-container-high overflow-hidden">
                            <div class="relative z-10 py-10">
                                <h2 class="text-4xl font-headline font-extrabold text-primary-container"><?php echo e($card['name']); ?></h2>
                                <p class="text-secondary font-body mt-2"><?php echo e($card['description'] ?? 'Explore this category.'); ?></p>
                            </div>
                            <img alt="" class="h-[120%] w-auto object-contain transform group-hover:rotate-6 transition-transform duration-700" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        </div>
                        <a class="absolute inset-0 flex items-center justify-end px-12 opacity-0 group-hover:opacity-100 transition-opacity" href="<?php echo e($categoryUrl); ?>">
                            <span class="p-4 bg-primary text-white rounded-full material-symbols-outlined">chevron_right</span>
                        </a>
                    <?php elseif ($index === 0): ?>
                        <img alt="" class="category-image" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="absolute inset-0 p-10 flex flex-col justify-end">
                            <div class="glass-tag self-start px-6 py-2 rounded-full mb-4">
                                <span class="text-label-md font-bold text-primary tracking-widest"><?php echo e(strtoupper((string) ($card['slug'] ?? 'category'))); ?></span>
                            </div>
                            <h2 class="text-4xl font-headline font-bold text-white mb-6"><?php echo e($card['name']); ?></h2>
                            <div class="flex gap-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">
                                <a class="px-6 py-3 bg-white text-primary rounded-full font-semibold text-sm transition-all hover:bg-primary hover:text-white" href="<?php echo e($categoryUrl); ?>">Explore</a>
                                <a class="px-6 py-3 bg-white/20 backdrop-blur text-white rounded-full font-semibold text-sm hover:bg-white/40" href="<?php echo e($categoryUrl); ?>">Shop</a>
                            </div>
                        </div>
                    <?php elseif ($index === 1): ?>
                        <img alt="" class="category-image" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="absolute inset-0 p-8 flex flex-col justify-end">
                            <h2 class="text-3xl font-headline font-bold text-white mb-4"><?php echo e($card['name']); ?></h2>
                            <p class="text-white/80 font-body mb-6 text-sm opacity-0 group-hover:opacity-100 transition-opacity"><?php echo e($card['description'] ?? 'Explore this collection.'); ?></p>
                            <a class="self-start px-5 py-2 bg-primary-container text-white rounded-full text-xs font-bold uppercase tracking-widest hover:brightness-110" href="<?php echo e($categoryUrl); ?>">Explore</a>
                        </div>
                    <?php elseif ($index === 2): ?>
                        <img alt="" class="category-image" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/5 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="absolute inset-0 p-8 flex flex-col justify-end">
                            <h2 class="text-3xl font-headline font-bold text-white mb-4"><?php echo e($card['name']); ?></h2>
                            <ul class="text-white/70 space-y-2 mb-8 opacity-0 group-hover:opacity-100 transition-all duration-500">
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> Featured collection</li>
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> Best sellers</li>
                                <li class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-xs">arrow_forward</span> New arrivals</li>
                            </ul>
                            <a class="px-8 py-3 bg-white text-primary rounded-full font-bold shadow-lg" href="<?php echo e($categoryUrl); ?>">View All</a>
                        </div>
                    <?php elseif ($index === 4): ?>
                        <img alt="" class="category-image" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="absolute inset-0 p-8 flex flex-col">
                            <span class="text-white font-headline text-lg font-bold"><?php echo e(strtoupper((string) $card['name'])); ?></span>
                            <div class="mt-auto">
                                <h3 class="text-white text-xl font-body mb-4"><?php echo e($card['description'] ?? 'Discover more products.'); ?></h3>
                                <div class="h-1 w-0 bg-white group-hover:w-full transition-all duration-700"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <img alt="" class="category-image" data-alt="<?php echo e($layout['image_alt']); ?>" src="<?php echo e($layout['image_src']); ?>" />
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-primary/40 transition-colors duration-500 category-overlay"></div>
                        <div class="absolute inset-0 p-8 flex flex-col justify-end items-center text-center">
                            <h2 class="text-3xl font-headline font-bold text-white mb-2"><?php echo e($card['name']); ?></h2>
                            <p class="text-white/60 text-xs tracking-widest mb-4"><?php echo e(strtoupper((string) ($card['slug'] ?? 'category'))); ?></p>
                            <a class="px-6 py-2 border border-white/40 text-white rounded-full hover:bg-white hover:text-primary transition-colors" href="<?php echo e($categoryUrl); ?>">Shop Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="mt-32 p-16 rounded-[2rem] bg-primary-container text-white overflow-hidden relative">
            <div class="relative z-10 max-w-xl">
                <h2 class="text-4xl font-headline font-bold mb-6">The Concierge Service</h2>
                <p class="text-on-primary-container text-lg font-body mb-8">
                    Not sure what you need? Our digital experts are here to curate a personalized bundle tailored to your specific workstation or entertainment needs.
                </p>
                <button class="bg-white text-primary px-10 py-4 rounded-full font-bold transition-transform hover:scale-105">Get Started</button>
            </div>
            <div class="absolute -right-20 -bottom-20 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute right-20 top-0 opacity-20">
                <span class="material-symbols-outlined text-[300px] font-thin">support_agent</span>
            </div>
        </section>
    </main>

    <footer class="w-full rounded-t-[24px] bg-slate-100 dark:bg-slate-950">
        <div class="flex flex-col md:flex-row justify-between items-center py-12 px-10 mt-20 max-w-screen-2xl mx-auto">
            <div class="mb-8 md:mb-0">
                <div class="text-lg font-bold text-blue-950 dark:text-white mb-2">MAXHOME</div>
                <p class="text-slate-500 dark:text-slate-400 font-['Manrope'] text-sm">© 2024 MAXHOME Digital Concierge. All rights reserved.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-8 font-['Manrope'] text-sm">
                <a class="text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 hover:underline underline-offset-4" href="#">Support</a>
                <a class="text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 hover:underline underline-offset-4" href="#">Privacy Policy</a>
                <a class="text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 hover:underline underline-offset-4" href="#">Shipping</a>
                <a class="text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 hover:underline underline-offset-4" href="#">Terms of Service</a>
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
