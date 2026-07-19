<?php
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/admin/admin_auth.php';
require_once __DIR__ . '/includes/mega_menu.php';
require_once __DIR__ . '/includes/i18n.php';
$currentPage = $currentPage ?? '';
$lang = maxhome_current_lang();
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$canSeeAddProductLink = isUserLoggedIn() && userHasAnyRole(['admin']);
$canSeeWarehouseSalesLink = isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin']);
$navbarCartCount = 0;
try {
    $navbarCartCount = cartQuantityCount(db());
} catch (Throwable $e) {
    $navbarCartCount = 0;
}
$navbarCartBadgeLabel = $navbarCartCount > 99 ? '99+' : (string) $navbarCartCount;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
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
            <?php if ($canSeeWarehouseSalesLink): ?>
                <a class="maxhome-navbar__link <?php echo $currentPage === 'warehouse sales' ? 'maxhome-navbar__link--active' : ''; ?>" href="warehouse_sales.php">Anbar Satış</a>
            <?php endif; ?>
        </div>

        <div class="maxhome-navbar__search" data-mh-search data-mh-search-endpoint="<?php
            $mhScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
            $mhScriptDir = rtrim($mhScriptDir, '/');
            echo e(($mhScriptDir === '' ? '' : $mhScriptDir) . '/ajax/navbar_search.php');
        ?>">
            <button
                class="maxhome-navbar__cats-btn"
                type="button"
                aria-label="<?php echo e(t('nav.catalog', 'Kataloq')); ?>"
                aria-expanded="false"
                aria-controls="maxhome-cats-modal"
                data-mh-cats-toggle>
                <span class="material-symbols-outlined" aria-hidden="true">grid_view</span>
            </button>
            <div class="maxhome-navbar__search-wrap">
                <form class="maxhome-navbar__search-form" action="shop_page.php" method="get" role="search">
                    <span class="maxhome-navbar__search-sparkle" aria-hidden="true">
                        <span class="material-symbols-outlined">auto_awesome</span>
                    </span>
                    <input
                        class="maxhome-navbar__search-input"
                        id="maxhome-search-input"
                        type="search"
                        name="q"
                        value="<?php echo e($searchQuery); ?>"
                        placeholder="<?php echo e(t('nav.search_placeholder', 'Məhsul axtar...')); ?>"
                        autocomplete="off"
                        aria-label="<?php echo e(t('nav.search')); ?>"
                        aria-controls="maxhome-search-suggest"
                        aria-autocomplete="list" />
                    <span class="maxhome-navbar__search-divider" aria-hidden="true"></span>
                    <button class="maxhome-navbar__search-submit" type="submit" aria-label="<?php echo e(t('nav.search')); ?>">
                        <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    </button>
                </form>
                <div class="maxhome-search-suggest" id="maxhome-search-suggest" hidden></div>
            </div>
        </div>

        <div class="maxhome-navbar__actions">
            <div class="maxhome-lang-switch" role="group" aria-label="<?php echo e(t('nav.language')); ?>">
                <a class="maxhome-lang-switch__item <?php echo $lang === 'az' ? 'maxhome-lang-switch__item--active' : ''; ?>" href="<?php echo e(maxhome_lang_url('az')); ?>"><?php echo e(t('lang.az')); ?></a>
                <a class="maxhome-lang-switch__item <?php echo $lang === 'en' ? 'maxhome-lang-switch__item--active' : ''; ?>" href="<?php echo e(maxhome_lang_url('en')); ?>"><?php echo e(t('lang.en')); ?></a>
            </div>
            <a class="maxhome-icon-btn maxhome-icon-btn--cart" href="shopping_cart.php" aria-label="<?php echo e(t('nav.cart')); ?><?php echo $navbarCartCount > 0 ? ' (' . $navbarCartBadgeLabel . ')' : ''; ?>" data-mh-cart-link>
                <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
                <span class="maxhome-cart-badge" data-mh-cart-badge <?php echo $navbarCartCount > 0 ? '' : 'hidden'; ?>><?php echo e($navbarCartBadgeLabel); ?></span>
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
            <?php if ($canSeeWarehouseSalesLink): ?>
                <a class="maxhome-mobile-drawer__link <?php echo $currentPage === 'warehouse sales' ? 'maxhome-mobile-drawer__link--active' : ''; ?>" href="warehouse_sales.php">Anbar Satış</a>
            <?php endif; ?>
        </nav>
    </aside>
    <?php maxhome_render_mobile_cats_modal(); ?>
</header>
<nav class="mh-bottom-nav" aria-label="Mobil naviqasiya" data-mh-bottom-nav>
    <a class="mh-bottom-nav__item <?php echo $currentPage === 'categories' || $currentPage === 'shop page' ? 'is-active' : ''; ?>" href="categories.php">
        <span class="material-symbols-outlined mh-bottom-nav__icon" aria-hidden="true">grid_view</span>
        <span class="mh-bottom-nav__label"><?php echo e(t('nav.catalog', 'Kataloq')); ?></span>
    </a>
    <a class="mh-bottom-nav__item <?php echo $currentPage === 'wishlist' ? 'is-active' : ''; ?>" href="wishlist.php">
        <span class="material-symbols-outlined mh-bottom-nav__icon" aria-hidden="true">favorite</span>
        <span class="mh-bottom-nav__label"><?php echo e(t('nav.wishlist', 'Seçilmişlər')); ?></span>
    </a>
    <a class="mh-bottom-nav__item mh-bottom-nav__item--home <?php echo $currentPage === 'home' ? 'is-active' : ''; ?>" href="index.php" aria-label="<?php echo e(t('nav.home')); ?>">
        <span class="mh-bottom-nav__home">
            <span class="material-symbols-outlined mh-bottom-nav__home-icon" aria-hidden="true">home</span>
        </span>
        <span class="mh-bottom-nav__dot" aria-hidden="true"></span>
    </a>
    <a class="mh-bottom-nav__item mh-bottom-nav__item--cart <?php echo $currentPage === 'cart' ? 'is-active' : ''; ?>" href="shopping_cart.php" data-mh-cart-link>
        <span class="mh-bottom-nav__icon-wrap">
            <span class="material-symbols-outlined mh-bottom-nav__icon" aria-hidden="true">shopping_cart</span>
            <span class="maxhome-cart-badge maxhome-cart-badge--nav" data-mh-cart-badge <?php echo $navbarCartCount > 0 ? '' : 'hidden'; ?>><?php echo e($navbarCartBadgeLabel); ?></span>
        </span>
        <span class="mh-bottom-nav__label"><?php echo e(t('nav.cart_short', 'Səbət')); ?></span>
    </a>
    <a class="mh-bottom-nav__item <?php echo $currentPage === 'profile' ? 'is-active' : ''; ?>" href="<?php echo isUserLoggedIn() ? 'user_logout.php' : 'user_login.php'; ?>">
        <span class="material-symbols-outlined mh-bottom-nav__icon" aria-hidden="true">person</span>
        <span class="mh-bottom-nav__label"><?php echo e(t('nav.profile', 'Profil')); ?></span>
    </a>
</nav>
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

    var catsToggle = document.querySelector('[data-mh-cats-toggle]');
    var catsModal = document.getElementById('maxhome-cats-modal');

    function isMobileCatsViewport() {
        return window.matchMedia('(max-width: 991px)').matches;
    }

    function setCatsModalOpen(isOpen) {
        if (!catsModal || !catsToggle) {
            return;
        }
        catsModal.classList.toggle('is-open', isOpen);
        catsModal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        catsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('maxhome-cats-modal-open', isOpen);
        if (isOpen) {
            catsModal.scrollTop = 0;
        }
    }

    if (catsToggle && catsModal) {
        catsToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            if (!isMobileCatsViewport()) {
                return;
            }
            setCatsModalOpen(!catsModal.classList.contains('is-open'));
        });

        var catsClose = catsModal.querySelector('.maxhome-cats-modal__close');
        if (catsClose) {
            catsClose.addEventListener('click', function () {
                setCatsModalOpen(false);
            });
        }

        catsModal.addEventListener('click', function (event) {
            var rootBtn = event.target.closest('.maxhome-cats-modal__root');
            if (rootBtn) {
                var group = rootBtn.closest('.maxhome-cats-modal__group');
                var panel = group ? group.querySelector('.maxhome-cats-modal__panel') : null;
                if (panel) {
                    var willExpand = panel.hidden;
                    panel.hidden = !willExpand;
                    rootBtn.setAttribute('aria-expanded', willExpand ? 'true' : 'false');
                    group.classList.toggle('is-expanded', willExpand);
                }
                return;
            }
            if (event.target.closest('a')) {
                setCatsModalOpen(false);
            }
        });

        window.addEventListener('resize', function () {
            if (!isMobileCatsViewport() && catsModal.classList.contains('is-open')) {
                setCatsModalOpen(false);
            }
        });

        if (new URLSearchParams(window.location.search).get('cats') === 'open' && isMobileCatsViewport()) {
            setCatsModalOpen(true);
        }
    }

    var searchRoot = document.querySelector('[data-mh-search]');
    var searchInput = document.getElementById('maxhome-search-input');
    var suggestBox = document.getElementById('maxhome-search-suggest');
    if (!searchRoot || !searchInput) {
        return;
    }
    var debounceTimer = null;
    var currentController = null;
    var searchEndpoint = searchRoot.getAttribute('data-mh-search-endpoint') || 'ajax/navbar_search.php';
    var suggestOnBody = false;

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

    function ensureSuggestOnBody() {
        if (!suggestBox || suggestOnBody) {
            return;
        }
        document.body.appendChild(suggestBox);
        suggestOnBody = true;
    }

    function positionSuggestBox() {
        if (!suggestBox || !searchRoot) {
            return;
        }
        ensureSuggestOnBody();
        var wrap = searchRoot.querySelector('.maxhome-navbar__search-wrap') || searchRoot;
        var rect = wrap.getBoundingClientRect();
        var left = Math.max(8, rect.left);
        var width = Math.min(rect.width, window.innerWidth - 16);
        if (left + width > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - width - 8);
        }
        suggestBox.style.position = 'fixed';
        suggestBox.style.left = left + 'px';
        suggestBox.style.top = (rect.bottom + 8) + 'px';
        suggestBox.style.width = width + 'px';
        suggestBox.style.right = 'auto';
        suggestBox.style.minWidth = '0';
        suggestBox.style.maxWidth = 'calc(100vw - 16px)';
        suggestBox.style.zIndex = '520';
    }

    function hideSuggestions() {
        if (!suggestBox) {
            return;
        }
        suggestBox.hidden = true;
        suggestBox.innerHTML = '';
        suggestBox.style.left = '';
        suggestBox.style.top = '';
        suggestBox.style.width = '';
        suggestBox.style.right = '';
        suggestBox.style.minWidth = '';
        suggestBox.style.maxWidth = '';
        suggestBox.style.zIndex = '';
        suggestBox.style.position = '';
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
            hideSuggestions();
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
                '<section class="maxhome-search-suggest__card maxhome-search-suggest__card--products">' +
                    '<h4 class="maxhome-search-suggest__title">Məhsullar</h4>' +
                    (productsHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                '</section>' +
                '<section class="maxhome-search-suggest__card maxhome-search-suggest__card--meta">' +
                    '<h4 class="maxhome-search-suggest__title">Ən çox axtarılan</h4>' +
                    (popularHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                    '<h4 class="maxhome-search-suggest__title maxhome-search-suggest__title--spaced">Kateqoriyalar</h4>' +
                    (categoriesHtml || '<p class="maxhome-search-suggest__empty">Nəticə yoxdur</p>') +
                '</section>' +
            '</div>' +
            '<a class="maxhome-search-suggest__banner" href="shop_page.php?q=' + encodeURIComponent(data.query || '') + '">' +
                '<span class="maxhome-search-suggest__banner-top">Onlayn xüsusi təkliflər</span>' +
                '<strong class="maxhome-search-suggest__banner-main">Axtardığın məhsulu indi daha sərfəli tap</strong>' +
                '<span class="maxhome-search-suggest__banner-sub">Bütün nəticələrə keçid et</span>' +
            '</a>';
        positionSuggestBox();
        suggestBox.hidden = false;
    }

    function loadSuggestions(query) {
        if (!suggestBox) {
            return;
        }
        var cleanQuery = String(query || '').trim();
        if (cleanQuery.length === 0) {
            hideSuggestions();
            if (currentController) {
                currentController.abort();
                currentController = null;
            }
            return;
        }
        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();
        var requestToken = currentController;
        var url = searchEndpoint + (searchEndpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(cleanQuery);
        fetch(url, { signal: requestToken.signal, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (requestToken !== currentController) {
                    return;
                }
                if (!data || data.ok !== true) {
                    hideSuggestions();
                    return;
                }
                renderSuggestions(data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (requestToken !== currentController) {
                    return;
                }
                hideSuggestions();
            });
    }

    document.addEventListener('click', function (event) {
        if (!suggestBox || suggestBox.hidden) {
            return;
        }
        if (searchRoot.contains(event.target) || suggestBox.contains(event.target)) {
            return;
        }
        hideSuggestions();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('maxhome-mobile-menu-open')) {
            setMenuOpen(false);
        }
        if (event.key === 'Escape' && catsModal && catsModal.classList.contains('is-open')) {
            setCatsModalOpen(false);
        }
        if (event.key === 'Escape' && suggestBox && !suggestBox.hidden) {
            hideSuggestions();
        }
    });

    function scheduleSuggestions() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            loadSuggestions(searchInput.value || '');
        }, 140);
    }

    searchInput.addEventListener('input', scheduleSuggestions);
    searchInput.addEventListener('keyup', scheduleSuggestions);
    searchInput.addEventListener('search', scheduleSuggestions);

    searchInput.addEventListener('focus', function () {
        if (String(searchInput.value || '').trim().length > 0) {
            loadSuggestions(searchInput.value || '');
        }
    });

    window.addEventListener('resize', function () {
        if (suggestBox && !suggestBox.hidden) {
            positionSuggestBox();
        }
    });
    window.addEventListener('scroll', function () {
        if (suggestBox && !suggestBox.hidden) {
            positionSuggestBox();
        }
    }, { passive: true });
})();
</script>
<script>
(function () {
    window.maxhomeUpdateCartBadge = function (count) {
        var n = Math.max(0, parseInt(count, 10) || 0);
        var label = n > 99 ? '99+' : String(n);
        document.querySelectorAll('[data-mh-cart-badge]').forEach(function (badge) {
            badge.textContent = label;
            if (n > 0) {
                badge.removeAttribute('hidden');
            } else {
                badge.setAttribute('hidden', '');
            }
        });
        document.querySelectorAll('[data-mh-cart-link]').forEach(function (link) {
            var base = link.getAttribute('aria-label') || '';
            base = base.replace(/\s*\(\d+\+?\)$/, '').trim();
            if (!base) {
                base = 'Səbət';
            }
            link.setAttribute('aria-label', n > 0 ? (base + ' (' + label + ')') : base);
        });
    };
})();
</script>
