<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const MAXHOME_SUPPORTED_LANGS = ['az', 'en'];
const MAXHOME_DEFAULT_LANG = 'az';

/**
 * @return array<string, array<string, string>>
 */
function maxhome_translations(): array
{
    static $translations = null;
    if ($translations !== null) {
        return $translations;
    }

    $translations = [
        'az' => [
            'nav.home' => 'Ana səhifə',
            'nav.shop' => 'Mağaza',
            'nav.categories' => 'Kateqoriyalar',
            'nav.products' => 'Məhsullar',
            'nav.support' => 'Dəstək',
            'nav.add_product' => 'Məhsul əlavə et',
            'nav.search' => 'Axtar',
            'nav.search_placeholder' => 'Məhsul axtar...',
            'nav.cart' => 'Səbət',
            'nav.cart_short' => 'Səbət',
            'nav.catalog' => 'Kataloq',
            'nav.wishlist' => 'Seçilmişlər',
            'nav.profile' => 'Profil',
            'nav.user_login' => 'Giriş',
            'nav.user_logout' => 'Çıxış',
            'nav.language' => 'Dil',
            'lang.az' => 'AZ',
            'lang.en' => 'EN',
            'hero.badge' => 'Önə çıxan',
            'hero.fallback_title' => 'Hüdudlardan <br /><span>kənarda.</span>',
            'hero.fallback_description' => 'Seçilmiş flaqman cihazlar və premium xidmət ilə texnologiyanın zirvəsini yaşayın.',
            'hero.default_short_description' => 'MAXHOME ilə seçilmiş premium texnologiyanı kəşf edin.',
            'hero.shop_now' => 'İndi al',
            'hero.view_deals' => 'Endirimlərə bax',
            'categories.title' => 'Seçilmiş texnologiya ekosistemi',
            'categories.subtitle' => 'Kolleksiyamızdakı hər cihaz performans və estetik uyğunluğa görə seçilir.',
            'categories.view_all' => 'Bütün kateqoriyalar',
            'categories.default_desc' => 'Diqqətlə seçilmiş kolleksiya.',
            'categories.empty' => 'Kateqoriya tapılmadı.',
            'flash.title' => 'Xüsusi təkliflər. <br />Məhdud vaxt.',
            'flash.hours' => 'Saat',
            'flash.minutes' => 'Dəqiqə',
            'flash.seconds' => 'Saniyə',
            'flash.explore' => 'Flash sale-ə bax',
            'flash.expired' => 'Bitdi',
            'flash.save' => 'QƏNAƏT',
            'flash.rating' => 'Reytinq',
            'flash.left' => 'Qalıq',
            'flash.empty' => 'Aktiv flash təklif tapılmadı.',
            'trending.label' => 'Ən çox satılanlar',
            'trending.title' => 'Bu məhsullar hər kəsin sevimlisidir!',
            'home.rail_robot' => 'Robot Tozsoranlar',
            'home.rail_bestsellers' => 'Ən çox satılanlar',
            'home.rail_ac' => 'Evinizə uyğun sərinlik',
            'product.compare' => 'Müqayisə et',
            'product.reviews' => 'rəy',
            'product.months' => 'ay',
            'product.add_to_cart' => 'Səbətə əlavə et',
            'product.out_of_stock' => 'Stokda yoxdur',
            'product.add_wishlist' => 'Seçilmişlərə əlavə et',
            'trending.empty' => 'Trend məhsul tapılmadı.',
            'features.fallback_title' => 'MAXHOME təcrübəsi',
            'features.fallback_text' => 'Seçilmiş texnologiya, etibarlı logistika və daimi dəstək.',
            'footer.brand_text' => 'Seçilmiş dəqiqlik və premium dəstək ilə texnologiya satışının gələcəyini qururuq.',
            'footer.social' => 'Sosial şəbəkələr',
            'footer.explore' => 'Kəşf et',
            'footer.assistance' => 'Yardım',
            'footer.legal' => 'Hüquqi',
            'footer.copy' => 'MAXHOME Electronics. Rəqəmsal Concierge təcrübəsi.',
            'footer.instagram' => 'Instagram',
        ],
        'en' => [
            'nav.home' => 'Home',
            'nav.shop' => 'Shop',
            'nav.categories' => 'Categories',
            'nav.products' => 'Products',
            'nav.support' => 'Support',
            'nav.add_product' => 'Add product',
            'nav.search' => 'Search',
            'nav.search_placeholder' => 'Search products...',
            'nav.cart' => 'Shopping cart',
            'nav.cart_short' => 'Cart',
            'nav.catalog' => 'Catalog',
            'nav.wishlist' => 'Favorites',
            'nav.profile' => 'Profile',
            'nav.user_login' => 'User login',
            'nav.user_logout' => 'User logout',
            'nav.language' => 'Language',
            'lang.az' => 'AZ',
            'lang.en' => 'EN',
            'hero.badge' => 'Featured now',
            'hero.fallback_title' => 'Beyond <br /><span>Limitless.</span>',
            'hero.fallback_description' => 'Experience premium flagship devices with concierge-level service.',
            'hero.default_short_description' => 'Experience curated premium technology with MAXHOME.',
            'hero.shop_now' => 'Shop now',
            'hero.view_deals' => 'View deals',
            'categories.title' => 'Curated Tech Ecosystem',
            'categories.subtitle' => 'Every device in our collection is handpicked for uncompromising performance and aesthetic harmony.',
            'categories.view_all' => 'View all categories',
            'categories.default_desc' => 'Carefully curated selection.',
            'categories.empty' => 'No categories found.',
            'flash.title' => 'Elevated Deals. <br />Limited Time.',
            'flash.hours' => 'Hours',
            'flash.minutes' => 'Minutes',
            'flash.seconds' => 'Seconds',
            'flash.explore' => 'Explore flash sale',
            'flash.expired' => 'Expired',
            'flash.save' => 'SAVE',
            'flash.rating' => 'Rating',
            'flash.left' => 'Left',
            'flash.empty' => 'No active flash deal found.',
            'trending.label' => 'Best sellers',
            'trending.title' => 'These are everyone\'s favorites!',
            'home.rail_robot' => 'Robot Vacuums',
            'home.rail_bestsellers' => 'Best sellers',
            'home.rail_ac' => 'Coolness for your home',
            'product.compare' => 'Compare',
            'product.reviews' => 'reviews',
            'product.months' => 'months',
            'product.add_to_cart' => 'Add to cart',
            'product.out_of_stock' => 'Out of stock',
            'product.add_wishlist' => 'Add to wishlist',
            'trending.empty' => 'No trending products found.',
            'features.fallback_title' => 'MAXHOME Experience',
            'features.fallback_text' => 'Curated technology, trusted logistics, and always-on concierge support.',
            'footer.brand_text' => 'Defining the future of technology retail through curated precision and elite support.',
            'footer.social' => 'Social networks',
            'footer.explore' => 'Explore',
            'footer.assistance' => 'Assistance',
            'footer.legal' => 'Legal',
            'footer.copy' => 'MAXHOME Electronics. Digital Concierge Experience.',
            'footer.instagram' => 'Instagram',
        ],
    ];

    return $translations;
}

function maxhome_set_locale_from_request(): void
{
    $requested = strtolower((string) ($_GET['lang'] ?? ''));
    if ($requested !== '' && in_array($requested, MAXHOME_SUPPORTED_LANGS, true)) {
        $_SESSION['lang'] = $requested;
    }
}

function maxhome_current_lang(): string
{
    $current = strtolower((string) ($_SESSION['lang'] ?? MAXHOME_DEFAULT_LANG));
    if (!in_array($current, MAXHOME_SUPPORTED_LANGS, true)) {
        return MAXHOME_DEFAULT_LANG;
    }
    return $current;
}

function t(string $key, ?string $fallback = null): string
{
    $lang = maxhome_current_lang();
    $translations = maxhome_translations();
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    if ($fallback !== null) {
        return $fallback;
    }
    return $key;
}

function maxhome_lang_url(string $lang): string
{
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    if ($path === '') {
        $path = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
    }

    $params = $_GET;
    $params['lang'] = $lang;
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

/**
 * Sayfa çıktısı sonrası metin dönüşümleri.
 * Bu yaklaşım mevcut projede tüm sayfalara tek tek müdahale etmeden
 * dil değişimini global hale getirmek için kullanılır.
 *
 * @return array<string, string>
 */
function maxhome_phrase_map_az_to_en(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        'Ana səhifə' => 'Home',
        'Mağaza' => 'Shop',
        'Kateqoriyalar' => 'Categories',
        'Məhsullar' => 'Products',
        'Dəstək' => 'Support',
        'Məhsul əlavə et' => 'Add product',
        'Səbət' => 'Cart',
        'Çıxış' => 'Logout',
        'Giriş' => 'Login',
        'Ən çox satılanlar' => 'Best sellers',
        'Bu məhsullar hər kəsin sevimlisidir!' => 'These products are everyone\'s favorite!',
        'Müqayisə et' => 'Compare',
        'rəy' => 'reviews',
        'Səbətə əlavə et' => 'Add to cart',
        'Seçilmişlərə əlavə et' => 'Add to wishlist',
        'Kateqoriya tapılmadı.' => 'No category found.',
        'Aktiv flash təklif tapılmadı.' => 'No active flash deal found.',
        'Saat' => 'Hours',
        'Dəqiqə' => 'Minutes',
        'Saniyə' => 'Seconds',
        'Bitdi' => 'Expired',
        'Qalıq' => 'Left',
        'Reytinq' => 'Rating',
        'Kəşf et' => 'Explore',
        'Yardım' => 'Assistance',
        'Hüquqi' => 'Legal',
        'Axtar' => 'Search',
        'Önə çıxan' => 'Featured now',
        'İndi al' => 'Shop now',
        'Endirimlərə bax' => 'View deals',
        'Seçilmiş texnologiya ekosistemi' => 'Curated tech ecosystem',
        'Bütün kateqoriyalar' => 'All categories',
        'ay' => 'months',
        'Ödənilib' => 'Paid',
        'Ödənilməyib' => 'Unpaid',
        'Gözləmədə' => 'Pending',
        'Ləğv edildi' => 'Cancelled',
        'Çatdırıldı' => 'Delivered',
        'Çatdırılır' => 'Shipped',
        'Sifariş' => 'Order',
        'Sifarişlər' => 'Orders',
        'Qiymət' => 'Price',
        'Miqdar' => 'Quantity',
        'Ümumi' => 'Total',
        'Endirim' => 'Discount',
        'Çatdırılma' => 'Shipping',
        'Müştəri' => 'Customer',
        'Axtarış' => 'Search',
        'Filtrlə' => 'Filter',
        'Təsdiqlə' => 'Confirm',
        'Sil' => 'Delete',
        'Yadda saxla' => 'Save',
        'Yenilə' => 'Update',
        'Ad' => 'Name',
        'Açıqlama' => 'Description',
        'Status' => 'Status',
        'Aktiv' => 'Active',
        'Passiv' => 'Inactive',
        'Sifarişi izlə' => 'Track Order',
        'Çatdırılma məlumatı' => 'Shipping Info',
        'Geri qaytarma' => 'Returns',
        'Məxfilik və Hüquqi' => 'Privacy & Legal',
        'Etibar mərkəzi' => 'Trust Center',
        'Məxfilik siyasəti' => 'Privacy Policy',
        'Cookie ayarları' => 'Cookie Settings',
        'MAXHOME dairəsinə qoşulun və texnologiya yeniliklərinə erkən çıxış əldə edin.' => 'Join the MAXHOME circle for early access to technology launches.',
        'Şəxsi hesablamanın gələcəyini MAXHOME-un rəqəmsal concierge xidməti ilə yaşayın. Müasir vizyon üçün premium alətlər.' => 'Experience the future of personal computing through our Digital Concierge service. Crafting premium tools for the modern visionary.',
        'Şəxsi hesablamada və inteqrə olunmuş rəqəmsal həyat tərzi təcrübələrində yeni dövrü formalaşdırırıq.' => 'Defining the next era of personal computing and integrated digital lifestyle experiences.',
        'Yeni gələnlər' => 'New Arrivals',
        'Eksklüziv seriyalar' => 'Exclusive Series',
        'Yenilənmiş məhsullar' => 'Refurbished',
        'Rəqəmsal konsyerj' => 'Digital Concierge',
        'Erkən çıxış və texnoloji yeniliklər üçün daxili icmamıza qoşulun.' => 'Join our inner circle for early access and tech insights.',
        'E-poçt ünvanı' => 'Email Address',
        'Brendləri filtr et' => 'Filter brands',
        'XƏBƏR BÜLLETENİ' => 'NEWSLETTER',
    ];

    return $map;
}

/**
 * @return array<string, string>
 */
function maxhome_phrase_map_en_to_az(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        'Home' => 'Ana səhifə',
        'Shop' => 'Mağaza',
        'Categories' => 'Kateqoriyalar',
        'Products' => 'Məhsullar',
        'Support' => 'Dəstək',
        'Add product' => 'Məhsul əlavə et',
        'Shopping cart' => 'Səbət',
        'Cart' => 'Səbət',
        'Logout' => 'Çıxış',
        'Login' => 'Giriş',
        'Best sellers' => 'Ən çox satılanlar',
        'These products are everyone\'s favorite!' => 'Bu məhsullar hər kəsin sevimlisidir!',
        'Compare' => 'Müqayisə et',
        'reviews' => 'rəy',
        'Add to cart' => 'Səbətə əlavə et',
        'Add to wishlist' => 'Seçilmişlərə əlavə et',
        'No category found.' => 'Kateqoriya tapılmadı.',
        'No active flash deal found.' => 'Aktiv flash təklif tapılmadı.',
        'Hours' => 'Saat',
        'Minutes' => 'Dəqiqə',
        'Seconds' => 'Saniyə',
        'Expired' => 'Bitdi',
        'Left' => 'Qalıq',
        'Rating' => 'Reytinq',
        'Explore' => 'Kəşf et',
        'Assistance' => 'Yardım',
        'Legal' => 'Hüquqi',
        'Search' => 'Axtar',
        'Featured now' => 'Önə çıxan',
        'Shop now' => 'İndi al',
        'View deals' => 'Endirimlərə bax',
        'Curated tech ecosystem' => 'Seçilmiş texnologiya ekosistemi',
        'All categories' => 'Bütün kateqoriyalar',
        'months' => 'ay',
        'Paid' => 'Ödənilib',
        'Unpaid' => 'Ödənilməyib',
        'Pending' => 'Gözləmədə',
        'Cancelled' => 'Ləğv edildi',
        'Delivered' => 'Çatdırıldı',
        'Shipped' => 'Çatdırılır',
        'Order' => 'Sifariş',
        'Orders' => 'Sifarişlər',
        'Price' => 'Qiymət',
        'Quantity' => 'Miqdar',
        'Total' => 'Ümumi',
        'Discount' => 'Endirim',
        'Shipping' => 'Çatdırılma',
        'Customer' => 'Müştəri',
        'Filter' => 'Filtrlə',
        'Confirm' => 'Təsdiqlə',
        'Delete' => 'Sil',
        'Save' => 'Yadda saxla',
        'Update' => 'Yenilə',
        'Name' => 'Ad',
        'Description' => 'Açıqlama',
        'Status' => 'Status',
        'Active' => 'Aktiv',
        'Inactive' => 'Passiv',
        'Track Order' => 'Sifarişi izlə',
        'Shipping Info' => 'Çatdırılma məlumatı',
        'Returns' => 'Geri qaytarma',
        'Privacy & Legal' => 'Məxfilik və Hüquqi',
        'Trust Center' => 'Etibar mərkəzi',
        'Privacy Policy' => 'Məxfilik siyasəti',
        'Cookie Settings' => 'Cookie ayarları',
        'Join the MAXHOME circle for early access to technology launches.' => 'MAXHOME dairəsinə qoşulun və texnologiya yeniliklərinə erkən çıxış əldə edin.',
        'Experience the future of personal computing through our Digital Concierge service. Crafting premium tools for the modern visionary.' => 'Şəxsi hesablamanın gələcəyini MAXHOME-un rəqəmsal concierge xidməti ilə yaşayın. Müasir vizyon üçün premium alətlər.',
        'Defining the next era of personal computing and integrated digital lifestyle experiences.' => 'Şəxsi hesablamada və inteqrə olunmuş rəqəmsal həyat tərzi təcrübələrində yeni dövrü formalaşdırırıq.',
        'New Arrivals' => 'Yeni gələnlər',
        'Exclusive Series' => 'Eksklüziv seriyalar',
        'Refurbished' => 'Yenilənmiş məhsullar',
        'Digital Concierge' => 'Rəqəmsal konsyerj',
        'Join our inner circle for early access and tech insights.' => 'Erkən çıxış və texnoloji yeniliklər üçün daxili icmamıza qoşulun.',
        'Email Address' => 'E-poçt ünvanı',
        'Filter brands' => 'Brendləri filtr et',
        'NEWSLETTER' => 'XƏBƏR BÜLLETENİ',

        // Feature section exact texts (database seeded English content).
        'Authenticity Guaranteed' => 'Orijinallıq zəmanəti',
        'Direct-from-manufacturer sourcing ensures you receive only genuine electronics.' => 'Birbaşa istehsalçıdan tədarük yalnız orijinal elektronika almanızı təmin edir.',
        'Concierge Support' => 'Texniki dəstək',
        'Elite 24/7 technical assistance for setup and seamless integration.' => 'Quraşdırma və problemsiz inteqrasiya üçün 24/7 premium texniki dəstək.',
        'Priority Logistics' => 'Logistika',
        'Ultra-fast, secure shipping with real-time white-glove tracking updates.' => 'Real-time izləmə ilə ultra-sürətli və təhlükəsiz premium çatdırılma.',
    ];

    return $map;
}

function maxhome_translate_rendered_html(string $html): string
{
    $lang = maxhome_current_lang();
    $map = $lang === 'en' ? maxhome_phrase_map_az_to_en() : maxhome_phrase_map_en_to_az();
    if ($map === []) {
        return $html;
    }

    // Only translate visible text nodes. Do not touch CSS/JS/HTML attributes.
    $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return $html;
    }

    $inScript = false;
    $inStyle = false;

    foreach ($parts as $i => $part) {
        if ($part === '') {
            continue;
        }

        if (preg_match('/^<[^>]+>$/u', $part) === 1) {
            if (preg_match('/^<\s*script\b/i', $part) === 1) {
                $inScript = true;
            } elseif (preg_match('/^<\s*\/\s*script\s*>/i', $part) === 1) {
                $inScript = false;
            } elseif (preg_match('/^<\s*style\b/i', $part) === 1) {
                $inStyle = true;
            } elseif (preg_match('/^<\s*\/\s*style\s*>/i', $part) === 1) {
                $inStyle = false;
            }
            continue;
        }

        if ($inScript || $inStyle) {
            continue;
        }

        $parts[$i] = maxhome_translate_text_fragment($part, $map);
    }

    return implode('', $parts);
}

function maxhome_translate_text_fragment(string $text, array $map): string
{
    if ($text === '' || $map === []) {
        return $text;
    }

    $translated = $text;
    foreach ($map as $source => $target) {
        if ($source === '' || $source === $target) {
            continue;
        }

        if (mb_strlen($source) <= 3) {
            $pattern = '/\b' . preg_quote($source, '/') . '\b/u';
            $translated = (string) preg_replace($pattern, $target, $translated);
            continue;
        }

        $translated = strtr($translated, [$source => $target]);
    }

    // Fallback: tolerate whitespace/newline differences in DB text blocks.
    foreach ($map as $source => $target) {
        if ($source === '' || $source === $target || mb_strlen($source) <= 3) {
            continue;
        }
        $pattern = '/\b' . preg_replace('/\s+/u', '\\s+', preg_quote($source, '/')) . '\b/u';
        $translated = (string) preg_replace($pattern, $target, $translated);
    }

    return $translated;
}

function maxhome_start_i18n_buffer(): void
{
    static $started = false;
    if ($started) {
        return;
    }

    $started = true;
    if (PHP_SAPI === 'cli') {
        return;
    }

    ob_start(static function (string $buffer): string {
        return maxhome_translate_rendered_html($buffer);
    });
}

maxhome_set_locale_from_request();
