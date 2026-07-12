<?php
declare(strict_types=1);

function adminSidebar(string $activePage): void
{
    $items = [
        'dashboard' => ['href' => 'admin_panel.php', 'label' => 'Dashboard'],
        'products' => ['href' => 'admin_products.php', 'label' => 'Mehsullar'],
        'workers' => ['href' => 'admin_workers.php', 'label' => 'Isciler'],
        'warehouse_cash' => ['href' => 'admin_warehouse_cash.php', 'label' => 'Ambar ve kassa'],
        'discounts' => ['href' => 'admin_discounts.php', 'label' => 'Endirimler'],
        'ecosystem' => ['href' => 'admin_ecosystem.php', 'label' => 'Ekosistem paketleri'],
        'homepage' => ['href' => 'admin_homepage.php', 'label' => 'Homepage'],
        'sliderpage' => ['href' => 'sliderpage.php', 'label' => 'Homepage slider'],
        'categories' => ['href' => 'admin_categories.php', 'label' => 'Kateqoriyalar'],
        'brands' => ['href' => 'admin_brands.php', 'label' => 'Brendler'],
        'brand_categories' => ['href' => 'admin_brand_categories.php', 'label' => 'Kateqoriya / Brend'],
        'features' => ['href' => 'admin_features.php', 'label' => 'Why Choose Us'],
        'footer' => ['href' => 'admin_footer_links.php', 'label' => 'Footer linkleri'],
        'support_faqs' => ['href' => 'admin_support_faqs.php', 'label' => 'Support suallari'],
        'support_contacts' => ['href' => 'admin_support_contacts.php', 'label' => 'Support contact kartlari'],
        'whatsapp_settings' => ['href' => 'admin_whatsapp_settings.php', 'label' => 'WhatsApp ayarlari'],
        'mail_settings' => ['href' => 'admin_mail_settings.php', 'label' => 'Email ayarlari'],
        'orders' => ['href' => 'admin_orders.php', 'label' => 'Sifarisler'],
        'shop' => ['href' => '../shop_page.php', 'label' => 'Magazani ac'],
    ];
    ?>
    <button
        class="admin-sidebar-toggle"
        type="button"
        aria-expanded="true"
        aria-controls="admin-sidebar"
        title="Sidebar ac/bagla"
    >
        ☰ Menu
    </button>
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="brand">MAXHOME</div>
        <div class="brand-sub">Admin panel</div>
        <nav class="admin-nav">
            <?php foreach ($items as $key => $item): ?>
                <a class="<?php echo $key === $activePage ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>">
                    <?php echo e($item['label']); ?>
                </a>
            <?php endforeach; ?>
            <a href="admin_logout.php">Cixis et</a>
        </nav>
    </aside>
    <div class="admin-sidebar-backdrop" hidden></div>
    <script>
        (function () {
            const layout = document.querySelector('.admin-layout');
            const sidebar = document.getElementById('admin-sidebar');
            const toggleBtn = document.querySelector('.admin-sidebar-toggle');
            const backdrop = document.querySelector('.admin-sidebar-backdrop');
            if (!layout || !sidebar || !toggleBtn || !backdrop) {
                return;
            }

            const storageKey = 'adminSidebarCollapsed';
            const mq = window.matchMedia('(max-width: 1024px)');

            const applyState = (collapsed) => {
                if (!mq.matches) {
                    layout.classList.remove('sidebar-collapsed');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    backdrop.hidden = true;
                    return;
                }
                layout.classList.toggle('sidebar-collapsed', collapsed);
                toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                backdrop.hidden = !mq.matches || collapsed;
            };

            const initialCollapsed = localStorage.getItem(storageKey) === '1';
            applyState(initialCollapsed);

            toggleBtn.addEventListener('click', function () {
                const collapsed = !layout.classList.contains('sidebar-collapsed');
                applyState(collapsed);
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
            });

            backdrop.addEventListener('click', function () {
                applyState(true);
                localStorage.setItem(storageKey, '1');
            });

            mq.addEventListener('change', function () {
                const collapsed = layout.classList.contains('sidebar-collapsed');
                applyState(collapsed);
            });
        })();
    </script>
    <?php
}
