<?php
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/admin/admin_auth.php';
require_once __DIR__ . '/includes/mega_menu.php';
require_once __DIR__ . '/includes/i18n.php';
$currentPage = $currentPage ?? '';
$lang = maxhome_current_lang();
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$canSeeAddProductLink = isUserLoggedIn() && userHasAnyRole(['admin']);
?>
<header class="maxhome-topbar">
    <nav class="maxhome-navbar">
        <a href="index.php"><img src="./assets/img/maxhome(arxafonsuz).png" alt="MAXHOME Logo" class="maxhome-navbar__logo"></a>
        <div class="maxhome-navbar__links">
            <a class="maxhome-navbar__link <?php echo $currentPage === 'home' ? 'maxhome-navbar__link--active' : ''; ?>" href="index.php"><?php echo e(t('nav.home')); ?></a>
            <a class="maxhome-navbar__link <?php echo $currentPage === 'shop page' ? 'maxhome-navbar__link--active' : ''; ?>" href="shop_page.php"><?php echo e(t('nav.shop')); ?></a>
            <div class="maxhome-navbar__mega">
                <a class="maxhome-navbar__link <?php echo $currentPage === 'categories' ? 'maxhome-navbar__link--active' : ''; ?>" href="categories.php"><?php echo e(t('nav.categories')); ?></a>

                <?php maxhome_render_mega_menu(); ?>
            </div>
            <a class="maxhome-navbar__link <?php echo $currentPage === 'support' ? 'maxhome-navbar__link--active' : ''; ?>" href="support_center.php"><?php echo e(t('nav.support')); ?></a>
            <?php if ($canSeeAddProductLink): ?>
                <a class="maxhome-navbar__link" href="admin/admin_products.php" title="Admin"><?php echo e(t('nav.add_product')); ?></a>
            <?php endif; ?>
            <?php if (isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin'])): ?>
                <a class="maxhome-navbar__link <?php echo $currentPage === 'warehouse sales' ? 'maxhome-navbar__link--active' : ''; ?>" href="warehouse_sales.php">Anbar Satış</a>
            <?php endif; ?>
        </div>

        <div class="maxhome-navbar__actions">
            <div class="maxhome-lang-switch" role="group" aria-label="<?php echo e(t('nav.language')); ?>">
                <a class="maxhome-lang-switch__item <?php echo $lang === 'az' ? 'maxhome-lang-switch__item--active' : ''; ?>" href="<?php echo e(maxhome_lang_url('az')); ?>"><?php echo e(t('lang.az')); ?></a>
                <a class="maxhome-lang-switch__item <?php echo $lang === 'en' ? 'maxhome-lang-switch__item--active' : ''; ?>" href="<?php echo e(maxhome_lang_url('en')); ?>"><?php echo e(t('lang.en')); ?></a>
            </div>
            <button class="maxhome-icon-btn maxhome-search-toggle" type="button" aria-label="<?php echo e(t('nav.search')); ?>" aria-expanded="false" aria-controls="maxhome-search-panel">
                <span class="material-symbols-outlined">search</span>
            </button>
            <a class="maxhome-icon-btn" href="shopping_cart.php" aria-label="<?php echo e(t('nav.cart')); ?>">
                <span class="material-symbols-outlined">shopping_cart</span>
            </a>
            <?php if (isUserLoggedIn()): ?>
                <a class="maxhome-icon-btn" href="user_logout.php" aria-label="<?php echo e(t('nav.user_logout')); ?>" title="<?php echo e(currentUserName() !== '' ? currentUserName() : t('nav.user_logout')); ?>">
                    <span class="material-symbols-outlined">logout</span>
                </a>
            <?php else: ?>
                <a class="maxhome-icon-btn" href="user_login.php" aria-label="<?php echo e(t('nav.user_login')); ?>">
                    <span class="material-symbols-outlined">person</span>
                </a>
            <?php endif; ?>
            <button class="maxhome-icon-btn maxhome-menu-toggle" type="button" aria-label="Menyu" aria-expanded="false" aria-controls="maxhome-mobile-drawer">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>
    </nav>
    <div class="maxhome-mobile-backdrop" id="maxhome-mobile-backdrop" hidden></div>
    <aside class="maxhome-mobile-drawer" id="maxhome-mobile-drawer" aria-hidden="true">
        <div class="maxhome-mobile-drawer__head">
            <strong>MAXHOME</strong>
            <button class="maxhome-icon-btn maxhome-menu-close" type="button" aria-label="Menyunu bağla">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <nav class="maxhome-mobile-drawer__links" aria-label="Mobil naviqasiya">
            <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'home' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="index.php"><?php echo e(t('nav.home')); ?></a>
            <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'shop page' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="shop_page.php"><?php echo e(t('nav.shop')); ?></a>
            <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'categories' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="categories.php"><?php echo e(t('nav.categories')); ?></a>
            <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'support' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="support_center.php"><?php echo e(t('nav.support')); ?></a>
            <?php if ($canSeeAddProductLink): ?>
                <a class="maxhome-mobile-drawer__link" href="admin/admin_products.php"><?php echo e(t('nav.add_product')); ?></a>
            <?php endif; ?>
            <?php if (isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin'])): ?>
                <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'warehouse sales' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="warehouse_sales.php">Anbar Satış</a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="maxhome-search-panel" id="maxhome-search-panel" hidden>
        <form class="maxhome-search-panel__form" action="shop_page.php" method="get" role="search">
            <input
                class="maxhome-search-panel__input"
                type="search"
                name="q"
                value="<?php echo e($searchQuery); ?>"
                placeholder="<?php echo e(t('nav.search')); ?>..."
                autocomplete="off" />
            <button class="maxhome-search-panel__submit" type="submit"><?php echo e(t('nav.search')); ?></button>
        </form>
        <div class="maxhome-search-suggest" id="maxhome-search-suggest" hidden></div>
    </div>
</header>
<script>
(function () {
    var mega = document.querySelector('.maxhome-navbar__mega');
    if (mega) {
        var items = mega.querySelectorAll('[data-mega-target]');
        var panels = mega.querySelectorAll('.maxhome-mega-menu__panel');
        function activate(id) {
            if (!id) {
                return;
            }
            items.forEach(function (el) {
                el.classList.toggle('maxhome-mega-menu__left-item--active', el.getAttribute('data-mega-target') === id);
            });
            panels.forEach(function (el) {
                el.classList.toggle('maxhome-mega-menu__panel--active', el.id === id);
            });
        }
        items.forEach(function (el) {
            var id = el.getAttribute('data-mega-target');
            el.addEventListener('mouseenter', function () {
                activate(id);
            });
            el.addEventListener('focus', function () {
                activate(id);
            });
        });
    }

    var menuToggle = document.querySelector('.maxhome-menu-toggle');
    var menuClose = document.querySelector('.maxhome-menu-close');
    var menuDrawer = document.getElementById('maxhome-mobile-drawer');
    var menuBackdrop = document.getElementById('maxhome-mobile-backdrop');

    function setMenuOpen(isOpen) {
        if (!menuToggle || !menuDrawer || !menuBackdrop) {
            return;
        }
        document.body.classList.toggle('maxhome-mobile-menu-open', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menuDrawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        menuBackdrop.hidden = !isOpen;
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            var willOpen = !document.body.classList.contains('maxhome-mobile-menu-open');
            setMenuOpen(willOpen);
        });
    }
    if (menuClose) {
        menuClose.addEventListener('click', function () {
            setMenuOpen(false);
        });
    }
    if (menuBackdrop) {
        menuBackdrop.addEventListener('click', function () {
            setMenuOpen(false);
        });
    }
    if (menuDrawer) {
        menuDrawer.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setMenuOpen(false);
            }
        });
    }

    var searchToggle = document.querySelector('.maxhome-search-toggle');
    var searchPanel = document.getElementById('maxhome-search-panel');
    var suggestBox = document.getElementById('maxhome-search-suggest');
    if (!searchToggle || !searchPanel) {
        return;
    }
    var searchInput = searchPanel.querySelector('.maxhome-search-panel__input');
    var debounceTimer = null;
    var currentController = null;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatPrice(value) {
        var num = Number(value || 0);
        return num.toFixed(2) + '\u202f' + '₼';
    }

    function renderSuggestions(data) {
        if (!suggestBox) {
            return;
        }
        var popular = Array.isArray(data.popular) ? data.popular : [];
        var categories = Array.isArray(data.categories) ? data.categories : [];
        var products = Array.isArray(data.products) ? data.products : [];
        var empty = popular.length === 0 && categories.length === 0 && products.length === 0;
        if (empty) {
            suggestBox.hidden = true;
            suggestBox.innerHTML = '';
            return;
        }

        var popularHtml = popular.map(function (item) {
            var name = escapeHtml(item.name || '');
            var slug = encodeURIComponent(item.slug || '');
            return '<a class="maxhome-search-suggest__simple-link" href="product_details.php?slug=' + slug + '">' +
                '<span class="material-symbols-outlined" aria-hidden="true">search</span>' +
                '<span>' + name + '</span>' +
            '</a>';
        }).join('');

        var categoriesHtml = categories.map(function (item) {
            var name = escapeHtml(item.name || '');
            var slug = encodeURIComponent(item.slug || '');
            return '<a class="maxhome-search-suggest__simple-link" href="shop_page.php?category_slug=' + slug + '">' +
                '<span class="material-symbols-outlined" aria-hidden="true">search</span>' +
                '<span>' + name + '</span>' +
            '</a>';
        }).join('');

        var productsHtml = products.map(function (item) {
            var name = escapeHtml(item.name || '');
            var category = escapeHtml(item.category || 'Ümumi');
            var slug = encodeURIComponent(item.slug || '');
            var image = item.image_url ? '<img class="maxhome-search-suggest__product-img" src="' + escapeHtml(item.image_url) + '" alt="' + name + '">' : '<div class="maxhome-search-suggest__product-img maxhome-search-suggest__product-img--placeholder"><span class="material-symbols-outlined" aria-hidden="true">image</span></div>';
            var oldPrice = item.old_price && Number(item.old_price) > Number(item.price) ? '<span class="maxhome-search-suggest__old-price">' + formatPrice(item.old_price) + '</span>' : '';
            return '<a class="maxhome-search-suggest__product-row" href="product_details.php?slug=' + slug + '">' +
                image +
                '<div class="maxhome-search-suggest__product-meta">' +
                    '<span class="maxhome-search-suggest__product-cat">' + category + '</span>' +
                    '<strong class="maxhome-search-suggest__product-name">' + name + '</strong>' +
                '</div>' +
                '<div class="maxhome-search-suggest__product-prices">' +
                    oldPrice +
                    '<strong class="maxhome-search-suggest__price">' + formatPrice(item.price) + '</strong>' +
                '</div>' +
            '</a>';
        }).join('');

        suggestBox.innerHTML =
            '<div class="maxhome-search-suggest__grid">' +
                '<section class="maxhome-search-suggest__card">' +
                    '<h4 class="maxhome-search-suggest__title">Ən çox axtarılan</h4>' +
                    (popularHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                    '<h4 class="maxhome-search-suggest__title maxhome-search-suggest__title--spaced">Kateqoriyalar</h4>' +
                    (categoriesHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                '</section>' +
                '<section class="maxhome-search-suggest__card">' +
                    '<h4 class="maxhome-search-suggest__title">Məhsullar</h4>' +
                    (productsHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                '</section>' +
            '</div>' +
            '<a class="maxhome-search-suggest__banner" href="shop_page.php?q=' + encodeURIComponent(data.query || '') + '">' +
                '<span class="maxhome-search-suggest__banner-top">Onlayn xüsusi təkliflər</span>' +
                '<strong class="maxhome-search-suggest__banner-main">Axtardığın məhsulu indi daha sərfəli tap</strong>' +
                '<span class="maxhome-search-suggest__banner-sub">Bütün nəticələrə keçid et</span>' +
            '</a>';
        suggestBox.hidden = false;
    }

    function loadSuggestions(query) {
        if (!suggestBox) {
            return;
        }
        var cleanQuery = String(query || '').trim();
        if (cleanQuery.length === 0) {
            suggestBox.hidden = true;
            suggestBox.innerHTML = '';
            if (currentController) {
                currentController.abort();
            }
            return;
        }
        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();
        var url = 'ajax/navbar_search.php?q=' + encodeURIComponent(cleanQuery);
        fetch(url, { signal: currentController.signal })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    suggestBox.hidden = true;
                    return;
                }
                renderSuggestions(data);
            })
            .catch(function () {
                suggestBox.hidden = true;
            });
    }

    function setSearchOpen(isOpen) {
        searchPanel.hidden = !isOpen;
        searchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen && searchInput) {
            searchInput.focus();
            loadSuggestions(searchInput.value || '');
        } else if (suggestBox) {
            suggestBox.hidden = true;
            suggestBox.innerHTML = '';
        }
    }

    searchToggle.addEventListener('click', function () {
        var willOpen = searchPanel.hidden;
        setSearchOpen(willOpen);
    });

    document.addEventListener('click', function (event) {
        if (searchPanel.hidden) {
            return;
        }
        if (searchPanel.contains(event.target) || searchToggle.contains(event.target)) {
            return;
        }
        setSearchOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('maxhome-mobile-menu-open')) {
            setMenuOpen(false);
        }
        if (event.key === 'Escape' && !searchPanel.hidden) {
            setSearchOpen(false);
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadSuggestions(searchInput.value || '');
            }, 220);
        });
    }
})();
</script>
