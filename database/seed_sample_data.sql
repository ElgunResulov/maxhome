USE maxhome_db;

-- Əsas kateqoriya ağacı bu faylda deyil. Əvvəlcə database/seed_az_catalog.sql import edin
-- (və ya: php database/build_az_categories.php > database/seed_az_catalog.sql, sonra həmin SQL-i işlədin).

-- Yalnız MAXHOME (noutbuk nümunəsi). Apple/Samsung seed_az_catalog.sql-dəki brendlərdir.
INSERT INTO brands (name, slug)
VALUES ('MAXHOME', 'maxhome')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (
    category_id, brand_id, name, slug, short_description, description, sku,
    base_price, compare_at_price, rating_avg, rating_count, stock_qty, status
)
SELECT c.id, b.id, 'Apple Demo Smartfon', 'apple-demo-smartfon', 'Flagship sinfi demo smartfon.', 'Kataloq və filtr sınağı üçün Apple brendi ilə nümunə.', 'DEMO-APL-001',
       1199.00, 1299.00, 4.8, 428, 20, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'apple'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'apple-demo-smartfon');

INSERT INTO products (
    category_id, brand_id, name, slug, short_description, description, sku,
    base_price, compare_at_price, rating_avg, rating_count, stock_qty, status
)
SELECT c.id, b.id, 'Samsung Demo Smartfon', 'samsung-demo-smartfon', 'Güclü performanslı demo smartfon.', 'Kataloq və filtr sınağı üçün Samsung brendi ilə nümunə.', 'DEMO-SSG-001',
       899.00, 949.00, 4.2, 152, 35, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'samsung'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'samsung-demo-smartfon');

INSERT INTO products (
    category_id, brand_id, name, slug, short_description, description, sku,
    base_price, compare_at_price, rating_avg, rating_count, stock_qty, status
)
SELECT c.id, b.id, 'MAX Book Pro Ultra 16', 'max-book-pro-ultra-16', 'Top-tier laptop for creators and developers.', 'Powerful laptop with premium display.', 'MBP-016',
       2499.00, 2699.00, 4.9, 128, 12, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'maxhome'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'max-book-pro-ultra-16');

-- 20 əlavə məhsul (smartfon, noutbuk, TV, planşet)
INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Apple iPhone 15 Pro 256GB', 'apple-iphone-15-pro-256gb', 'A17 Pro, premium kamera sistemi.', 'Titan korpus, yüksək performans və peşəkar səviyyədə çəkiliş üçün ideal model.', 'APL-IP15P-256',
       2599.00, 2799.00, 4.9, 512, 18, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'apple'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'apple-iphone-15-pro-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Samsung Galaxy S24 Ultra 512GB', 'samsung-galaxy-s24-ultra-512gb', 'AI dəstəkli flaqman smartfon.', 'Yüksək yenilənmə tezlikli ekran və güclü kamera ilə premium Android təcrübəsi.', 'SMS-S24U-512',
       2499.00, 2699.00, 4.8, 401, 22, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'samsung'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'samsung-galaxy-s24-ultra-512gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Xiaomi 14 256GB', 'xiaomi-14-256gb', 'Leica kamera ilə güclü gündəlik istifadə.', 'Kompakt dizayn, sürətli çipset və uzun batareya ömrü ilə balanslı seçim.', 'XMI-14-256',
       1499.00, 1649.00, 4.6, 233, 30, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'xiaomi'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'xiaomi-14-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Huawei P60 Pro 256GB', 'huawei-p60-pro-256gb', 'Premium kamera performansı.', 'Parlaq OLED ekran və yüksək keyfiyyətli çəkiliş imkanları ilə seçilən model.', 'HWE-P60P-256',
       1799.00, 1949.00, 4.5, 187, 14, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'huawei'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'huawei-p60-pro-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Honor Magic6 Lite 256GB', 'honor-magic6-lite-256gb', 'Böyük ekranlı orta-yuxarı seqment.', 'Yüngül korpus, yüksək yaddaş həcmi və gündəlik istifadə üçün stabil performans.', 'HNR-M6L-256',
       899.00, 999.00, 4.4, 126, 40, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'honor'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'honor-magic6-lite-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Poco X6 Pro 512GB', 'poco-x6-pro-512gb', 'Qiymətinə görə yüksək performans.', 'Yüksək yaddaş və axıcı istifadə üçün güclü prosessor təqdim edən sərfəli model.', 'POC-X6P-512',
       999.00, 1129.00, 4.6, 210, 36, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'poco'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'poco-x6-pro-512gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Infinix Note 40 Pro 256GB', 'infinix-note-40-pro-256gb', 'AMOLED ekranlı büdcə dostu model.', 'Sürətli şarj və geniş yaddaşla gündəlik multimedia istifadəsi üçün uyğundur.', 'INF-N40P-256',
       749.00, 849.00, 4.3, 98, 44, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'infinix'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'infinix-note-40-pro-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Tecno Camon 30 256GB', 'tecno-camon-30-256gb', 'Portret çəkiliş üçün optimallaşdırılmış kamera.', 'Yaxşı ekran, stabil performans və münasib qiymət bir modeldə birləşir.', 'TEC-C30-256',
       699.00, 799.00, 4.2, 83, 38, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'tecno'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'tecno-camon-30-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Vivo V30 256GB', 'vivo-v30-256gb', 'Portret və vlog üçün uyğun kamera.', 'Zərif dizaynı və rəngli ekranı ilə gündəlik istifadə üçün ideal smartfon.', 'VIV-V30-256',
       899.00, 999.00, 4.4, 111, 31, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'vivo'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'vivo-v30-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Nokia G42 128GB', 'nokia-g42-128gb', 'Sadə və etibarlı Android təcrübəsi.', 'Uzun batareya ömrü və möhkəm korpusu ilə funksional gündəlik model.', 'NOK-G42-128',
       499.00, 579.00, 4.1, 69, 52, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'nokia'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'nokia-g42-128gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Lenovo IdeaPad Slim 5 15', 'lenovo-ideapad-slim-5-15', 'İş və təhsil üçün universal noutbuk.', 'Rahat klaviatura və güclü batareya ilə ofis işi üçün balanslı seçim.', 'LNV-SLIM5-15',
       1599.00, 1749.00, 4.5, 142, 19, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'lenovo'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'lenovo-ideapad-slim-5-15');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Apple MacBook Air M3 13', 'apple-macbook-air-m3-13', 'Səssiz və yüngül premium noutbuk.', 'M3 çipi sayəsində yüksək sürət və uzun şarjla peşəkar mobil iş üçün ideal.', 'APL-MBA-M3-13',
       2699.00, 2899.00, 4.9, 276, 11, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'apple'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'apple-macbook-air-m3-13');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Samsung Galaxy Book4 15', 'samsung-galaxy-book4-15', 'Nazik korpuslu iş noutbuku.', 'Yüngül dizayn və sürətli açılış ilə ofis və gündəlik istifadə üçün əlverişlidir.', 'SMS-GB4-15',
       1899.00, 2049.00, 4.4, 96, 17, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'samsung'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'samsung-galaxy-book4-15');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Huawei MateBook D16', 'huawei-matebook-d16', 'Geniş ekranlı məhsuldar noutbuk.', 'Böyük ekran və rahat çoxlu tapşırıq performansı ilə iş üçün effektiv seçimdir.', 'HWE-MBD16',
       1699.00, 1849.00, 4.5, 121, 15, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'huawei'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'huawei-matebook-d16');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Xiaomi RedmiBook Pro 14', 'xiaomi-redmibook-pro-14', 'Yüngül və sürətli noutbuk.', 'Yüksək keyfiyyətli ekran və portativ korpus ilə tələbələr üçün uyğun model.', 'XMI-RBP14',
       1799.00, 1949.00, 4.4, 88, 13, 'active'
FROM categories c, brands b
WHERE c.slug = 'nb-butun' AND b.slug = 'xiaomi'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'xiaomi-redmibook-pro-14');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Motorola Edge 50 Fusion', 'motorola-edge-50-fusion', 'P-OLED ekranlı zərif smartfon.', 'Sürətli gündəlik performans və təmiz Android təcrübəsi təqdim edir.', 'MTR-E50F-256',
       929.00, 1049.00, 4.3, 74, 27, 'active'
FROM categories c, brands b
WHERE c.slug = 'sf-smartfonlar' AND b.slug = 'motorola'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'motorola-edge-50-fusion');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Samsung Neo QLED 55 QN85', 'samsung-neo-qled-55-qn85', '55 düym 4K Neo QLED TV.', 'Parlaq panel və yüksək kontrast ilə film və oyun üçün premium TV təcrübəsi.', 'SMS-TV-QN85-55',
       2399.00, 2599.00, 4.7, 133, 9, 'active'
FROM categories c, brands b
WHERE c.slug = 'tv-butun' AND b.slug = 'samsung'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'samsung-neo-qled-55-qn85');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Apple iPad Air 11 M2 128GB', 'apple-ipad-air-11-m2-128gb', 'Yeni nəsil planşet performansı.', 'M2 çipi, yüngül korpus və uzun şarj müddəti ilə yaradıcı işlər üçün ideal.', 'APL-IPADAIR11-128',
       1699.00, 1849.00, 4.8, 205, 16, 'active'
FROM categories c, brands b
WHERE c.slug = 'pl-planshetler' AND b.slug = 'apple'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'apple-ipad-air-11-m2-128gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Xiaomi Pad 6 256GB', 'xiaomi-pad-6-256gb', '2.8K ekranlı multimedia planşeti.', 'Yüksək yenilənmə tezliyi və keyfiyyətli səs sistemi ilə kontent istifadəsi üçün uyğundur.', 'XMI-PAD6-256',
       999.00, 1149.00, 4.5, 97, 24, 'active'
FROM categories c, brands b
WHERE c.slug = 'pl-planshetler' AND b.slug = 'xiaomi'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'xiaomi-pad-6-256gb');

INSERT INTO products (category_id, brand_id, name, slug, short_description, description, sku, base_price, compare_at_price, rating_avg, rating_count, stock_qty, status)
SELECT c.id, b.id, 'Lenovo Tab P12 128GB', 'lenovo-tab-p12-128gb', 'Böyük ekranlı təhsil planşeti.', 'Qələm dəstəyi və çoxlu tapşırıq üçün uyğun ekran formatı ilə rahat istifadə.', 'LNV-TABP12-128',
       899.00, 1029.00, 4.3, 64, 21, 'active'
FROM categories c, brands b
WHERE c.slug = 'pl-planshetler' AND b.slug = 'lenovo'
AND NOT EXISTS (SELECT 1 FROM products WHERE slug = 'lenovo-tab-p12-128gb');

INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, '/uploads/products/smartphone.svg', 'Apple Demo Smartfon', 1, 1
FROM products p
WHERE p.slug = 'apple-demo-smartfon'
AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id);

INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, '/uploads/products/smartphone.svg', 'Samsung Demo Smartfon', 1, 1
FROM products p
WHERE p.slug = 'samsung-demo-smartfon'
AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id);

INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, '/uploads/products/laptop.svg', 'MAX Book Pro Ultra 16', 1, 1
FROM products p
WHERE p.slug = 'max-book-pro-ultra-16'
AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id);

INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, x.image_url, x.alt_text, 1, 1
FROM (
    SELECT 'apple-iphone-15-pro-256gb' AS slug, '/uploads/products/smartphone.svg' AS image_url, 'Apple iPhone 15 Pro 256GB' AS alt_text
    UNION ALL SELECT 'samsung-galaxy-s24-ultra-512gb', '/uploads/products/smartphone.svg', 'Samsung Galaxy S24 Ultra 512GB'
    UNION ALL SELECT 'xiaomi-14-256gb', '/uploads/products/smartphone.svg', 'Xiaomi 14 256GB'
    UNION ALL SELECT 'huawei-p60-pro-256gb', '/uploads/products/smartphone.svg', 'Huawei P60 Pro 256GB'
    UNION ALL SELECT 'honor-magic6-lite-256gb', '/uploads/products/smartphone.svg', 'Honor Magic6 Lite 256GB'
    UNION ALL SELECT 'poco-x6-pro-512gb', '/uploads/products/smartphone.svg', 'Poco X6 Pro 512GB'
    UNION ALL SELECT 'infinix-note-40-pro-256gb', '/uploads/products/smartphone.svg', 'Infinix Note 40 Pro 256GB'
    UNION ALL SELECT 'tecno-camon-30-256gb', '/uploads/products/smartphone.svg', 'Tecno Camon 30 256GB'
    UNION ALL SELECT 'vivo-v30-256gb', '/uploads/products/smartphone.svg', 'Vivo V30 256GB'
    UNION ALL SELECT 'nokia-g42-128gb', '/uploads/products/smartphone.svg', 'Nokia G42 128GB'
    UNION ALL SELECT 'lenovo-ideapad-slim-5-15', '/uploads/products/laptop.svg', 'Lenovo IdeaPad Slim 5 15'
    UNION ALL SELECT 'apple-macbook-air-m3-13', '/uploads/products/laptop.svg', 'Apple MacBook Air M3 13'
    UNION ALL SELECT 'samsung-galaxy-book4-15', '/uploads/products/laptop.svg', 'Samsung Galaxy Book4 15'
    UNION ALL SELECT 'huawei-matebook-d16', '/uploads/products/laptop.svg', 'Huawei MateBook D16'
    UNION ALL SELECT 'xiaomi-redmibook-pro-14', '/uploads/products/laptop.svg', 'Xiaomi RedmiBook Pro 14'
    UNION ALL SELECT 'motorola-edge-50-fusion', '/uploads/products/smartphone.svg', 'Motorola Edge 50 Fusion'
    UNION ALL SELECT 'samsung-neo-qled-55-qn85', '/uploads/products/tv.svg', 'Samsung Neo QLED 55 QN85'
    UNION ALL SELECT 'apple-ipad-air-11-m2-128gb', '/uploads/products/tablet.svg', 'Apple iPad Air 11 M2 128GB'
    UNION ALL SELECT 'xiaomi-pad-6-256gb', '/uploads/products/tablet.svg', 'Xiaomi Pad 6 256GB'
    UNION ALL SELECT 'lenovo-tab-p12-128gb', '/uploads/products/tablet.svg', 'Lenovo Tab P12 128GB'
) AS x
INNER JOIN products p ON p.slug = x.slug
LEFT JOIN product_images pi ON pi.product_id = p.id
WHERE pi.id IS NULL;

-- Mövcud məhsullarda da şəkilləri unikal URL-lərlə yenilə
UPDATE product_images pi
INNER JOIN products p ON p.id = pi.product_id
INNER JOIN (
    SELECT 'apple-demo-smartfon' AS slug, '/uploads/products/smartphone.svg' AS image_url
    UNION ALL SELECT 'samsung-demo-smartfon', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'max-book-pro-ultra-16', '/uploads/products/laptop.svg'
    UNION ALL
    SELECT 'apple-iphone-15-pro-256gb' AS slug, '/uploads/products/smartphone.svg' AS image_url
    UNION ALL SELECT 'samsung-galaxy-s24-ultra-512gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'xiaomi-14-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'huawei-p60-pro-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'honor-magic6-lite-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'poco-x6-pro-512gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'infinix-note-40-pro-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'tecno-camon-30-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'vivo-v30-256gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'nokia-g42-128gb', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'lenovo-ideapad-slim-5-15', '/uploads/products/laptop.svg'
    UNION ALL SELECT 'apple-macbook-air-m3-13', '/uploads/products/laptop.svg'
    UNION ALL SELECT 'samsung-galaxy-book4-15', '/uploads/products/laptop.svg'
    UNION ALL SELECT 'huawei-matebook-d16', '/uploads/products/laptop.svg'
    UNION ALL SELECT 'xiaomi-redmibook-pro-14', '/uploads/products/laptop.svg'
    UNION ALL SELECT 'motorola-edge-50-fusion', '/uploads/products/smartphone.svg'
    UNION ALL SELECT 'samsung-neo-qled-55-qn85', '/uploads/products/tv.svg'
    UNION ALL SELECT 'apple-ipad-air-11-m2-128gb', '/uploads/products/tablet.svg'
    UNION ALL SELECT 'xiaomi-pad-6-256gb', '/uploads/products/tablet.svg'
    UNION ALL SELECT 'lenovo-tab-p12-128gb', '/uploads/products/tablet.svg'
) src ON src.slug = p.slug
SET pi.image_url = src.image_url
WHERE pi.is_primary = 1;

-- Homepage marketing content
INSERT INTO site_features (icon, title, body, sort_order, is_active)
VALUES
('verified', 'Authenticity Guaranteed', 'Direct-from-manufacturer sourcing ensures you receive only genuine electronics.', 1, 1),
('support_agent', 'Concierge Support', 'Elite 24/7 technical assistance for setup and seamless integration.', 2, 1),
('local_shipping', 'Priority Logistics', 'Ultra-fast, secure shipping with real-time white-glove tracking updates.', 3, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO footer_links (section, label, url, sort_order, is_active)
VALUES
('explore', 'Smartfonlar', 'shop_page.php?category_slug=sf-smartfonlar', 1, 1),
('explore', 'Noutbuklar', 'shop_page.php?category_slug=nb-butun', 2, 1),
('explore', 'TV', 'shop_page.php?category_slug=tv-butun', 3, 1),
('explore', 'Newsletter', 'support_center.php', 4, 1),
('assistance', 'Track Order', 'support_center.php', 1, 1),
('assistance', 'Shipping', 'support_center.php', 2, 1),
('assistance', 'Returns', 'support_center.php', 3, 1),
('assistance', 'Support', 'support_center.php', 4, 1),
('legal', 'Privacy Policy', 'support_center.php', 1, 1),
('legal', 'Trust Center', 'support_center.php', 2, 1)
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO support_faqs (question, answer, sort_order, is_active)
VALUES
('Sifarisimi nece izleyebilirem?', 'Profil bolmesindeki sifarisler sehifesinden order nomresi ile statusu izleyebilirsiniz.', 1, 1),
('Mehsulu nece geri qaytara bilerem?', 'Catdirilmadan sonra 30 gun erzinde support merkezinden geri qaytarma telebi yarada bilersiniz.', 2, 1),
('Zemanet muddeti ne qederdir?', 'Bir cox mehsul ucun standart 24 ay zemanet tetbiq olunur. Etrafli melumat mehsul sehifesinde gosterilir.', 3, 1),
('Support cavabi ne qeder vaxta gelir?', 'Email sorqularina adeten 4 saat erzinde, chat sorqularina ise daha qisa vaxtda cavab verilir.', 4, 1)
ON DUPLICATE KEY UPDATE
  answer = VALUES(answer),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

INSERT INTO support_contact_cards (icon, title, description, value, action_url, sort_order, is_active)
VALUES
('call', 'Call Us', 'Immediate help for urgent issues.', '1-800-MAX-HOME', 'tel:1800MAXHOME', 1, 1),
('mail', 'Email Support', 'Expect a response within 4 hours.', 'concierge@maxhome.tech', 'mailto:concierge@maxhome.tech', 2, 1),
('forum', 'Twitter Help', 'Tweet us @MaxHomeSupport.', 'Average response: 15m', 'https://x.com/MaxHomeSupport', 3, 1),
('location_on', 'Tech Bar', 'Visit a physical showroom bar.', 'Find a location', 'https://maps.google.com', 4, 1);

-- Homepage settings (Flash Sale countdown)
INSERT INTO site_settings (setting_key, setting_value)
VALUES
('flash_start_at', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')),
('flash_end_at', DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 12 HOUR), '%Y-%m-%d %H:%i:%s')),
('flash_product_id', NULL),
('flash_hide_on_expire', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('screen_size_inch', 'Ekran Olcusu', 'number', 'inch', NULL, 1, 1),
('ram_gb', 'RAM', 'number', 'GB', NULL, 1, 2),
('storage_gb', 'Yaddas', 'number', 'GB', NULL, 1, 3),
('battery_mah', 'Batareya', 'number', 'mAh', NULL, 1, 4),
('resolution', 'Cozunurluk', 'text', NULL, NULL, 1, 5),
('panel_type', 'Panel Novu', 'select', NULL, JSON_ARRAY('LED', 'QLED', 'OLED', 'Mini LED'), 1, 6),
('smart_tv', 'Smart TV', 'boolean', NULL, NULL, 1, 7)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, x.is_required, x.sort_order
FROM (
  SELECT 'sf-smartfonlar' AS category_slug, 'screen_size_inch' AS spec_key, 1 AS is_required, 1 AS sort_order
  UNION ALL SELECT 'sf-smartfonlar', 'ram_gb', 1, 2
  UNION ALL SELECT 'sf-smartfonlar', 'storage_gb', 1, 3
  UNION ALL SELECT 'sf-smartfonlar', 'battery_mah', 0, 4
  UNION ALL SELECT 'tv-butun', 'screen_size_inch', 1, 1
  UNION ALL SELECT 'tv-butun', 'resolution', 1, 2
  UNION ALL SELECT 'tv-butun', 'panel_type', 1, 3
  UNION ALL SELECT 'tv-butun', 'smart_tv', 0, 4
) AS x
INNER JOIN categories c ON c.slug = x.category_slug
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Duymeli telefonlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('fp_brand', 'Brend', 'text', NULL, NULL, 1, 2301),
('fp_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2302),
('fp_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2303),
('fp_camera', 'Kamera', 'text', NULL, NULL, 1, 2304),
('fp_sd_support', 'Yaddaş kartı dəstəyi', 'text', NULL, NULL, 1, 2305),
('fp_display_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2306),
('fp_display_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2307),
('fp_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 2308),
('fp_memory', 'Yaddaş', 'text', NULL, NULL, 1, 2309),
('fp_led_flash', 'Led flaș', 'text', NULL, NULL, 1, 2310),
('fp_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 2311),
('fp_headphone_jack', 'Qulaqlıq girişinin ölçüsü', 'text', NULL, NULL, 1, 2312),
('fp_color', 'Rəng', 'text', NULL, NULL, 1, 2313),
('fp_weight', 'Çəki', 'text', NULL, NULL, 1, 2314),
('fp_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2315),
('fp_battery_capacity', 'Akkumulyatorun həcmi', 'text', NULL, NULL, 1, 2316),
('fp_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2317),
('fp_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2318)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'fp_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'fp_country', 2
  UNION ALL SELECT 'fp_product_type', 3
  UNION ALL SELECT 'fp_camera', 4
  UNION ALL SELECT 'fp_sd_support', 5
  UNION ALL SELECT 'fp_display_size', 6
  UNION ALL SELECT 'fp_display_resolution', 7
  UNION ALL SELECT 'fp_bluetooth', 8
  UNION ALL SELECT 'fp_memory', 9
  UNION ALL SELECT 'fp_led_flash', 10
  UNION ALL SELECT 'fp_body_material', 11
  UNION ALL SELECT 'fp_headphone_jack', 12
  UNION ALL SELECT 'fp_color', 13
  UNION ALL SELECT 'fp_weight', 14
  UNION ALL SELECT 'fp_dimensions', 15
  UNION ALL SELECT 'fp_battery_capacity', 16
  UNION ALL SELECT 'fp_warranty', 17
  UNION ALL SELECT 'fp_included', 18
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('dt-duymeli-telefonlar', 'dt-duymeli-energizer', 'dt-duymeli-nokia', 'dt-duymeli-panasonic', 'dt-duymeli-butun-brendler')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Qulaqliq ucun konkret xususiyyet seti (istifadeci telebi)
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('qh_brand', 'Brend', 'text', NULL, NULL, 1, 2201),
('qh_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2202),
('qh_usage', 'İstifadə', 'text', NULL, NULL, 1, 2203),
('qh_freq_range_hz', 'Tezlik diapazonu, Hz', 'text', NULL, NULL, 1, 2204),
('qh_size', 'Ölçü', 'text', NULL, NULL, 1, 2205),
('qh_color', 'Rəng', 'text', NULL, NULL, 1, 2206),
('qh_connection', 'Qoşulma', 'text', NULL, NULL, 1, 2207),
('qh_battery_type', 'Batareya tipi', 'text', NULL, NULL, 1, 2208),
('qh_weight', 'Çəki', 'text', NULL, NULL, 1, 2209),
('qh_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2210)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'qh_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'qh_country', 2
  UNION ALL SELECT 'qh_usage', 3
  UNION ALL SELECT 'qh_freq_range_hz', 4
  UNION ALL SELECT 'qh_size', 5
  UNION ALL SELECT 'qh_color', 6
  UNION ALL SELECT 'qh_connection', 7
  UNION ALL SELECT 'qh_battery_type', 8
  UNION ALL SELECT 'qh_weight', 9
  UNION ALL SELECT 'qh_warranty', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('ql-butun', 'ql-simsiz', 'ql-simli', 'ql-usaq', 'ql-oyun', 'ga-qulaqliq')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Feature phone products: default technical specs for product details page
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug LIKE 'dt-%';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Telefon', 3
  UNION ALL SELECT 'Kamera', '0.3 MP', 4
  UNION ALL SELECT 'Yaddaş kartı dəstəyi', 'Micro SD', 5
  UNION ALL SELECT 'Ekranın ölçüsü', '2.4"', 6
  UNION ALL SELECT 'Ekran icazəsi', '320 x 240', 7
  UNION ALL SELECT 'Bluetooth', 'var', 8
  UNION ALL SELECT 'Yaddaş', '32 MB', 9
  UNION ALL SELECT 'Led flaș', 'var', 10
  UNION ALL SELECT 'Korpus Materialı', 'Plastik', 11
  UNION ALL SELECT 'Qulaqlıq girişinin ölçüsü', '3.5mm', 12
  UNION ALL SELECT 'Rəng', 'Qara', 13
  UNION ALL SELECT 'Çəki', '118.6 qr', 14
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '132.5 x 55 x 14.6 mm', 15
  UNION ALL SELECT 'Akkumulyatorun həcmi', '1500 mAh', 16
  UNION ALL SELECT 'Zəmanət', '1 il', 17
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Adapter, Qulaqlıq', 18
) x
WHERE c.slug LIKE 'dt-%'
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug LIKE 'dt-%'
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'nokia' THEN 'Finlandiya'
  WHEN 'energizer' THEN 'ABŞ'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug LIKE 'dt-%'
  );

-- Headphone products: default technical specs for product details page
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug LIKE 'ql-%' OR c.slug = 'ga-qulaqliq';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'İstifadə', '9 saataadək', 3
  UNION ALL SELECT 'Tezlik diapazonu, Hz', '10 Hz - 48 kHz', 4
  UNION ALL SELECT 'Ölçü', '66.11 x 46.98 x 24.06 mm', 5
  UNION ALL SELECT 'Rəng', 'Ağ', 6
  UNION ALL SELECT 'Qoşulma', 'Simsiz', 7
  UNION ALL SELECT 'Batareya tipi', '537 mAh', 8
  UNION ALL SELECT 'Çəki', '46 qr', 9
  UNION ALL SELECT 'Zəmanət', '1 il', 10
) x
WHERE c.slug LIKE 'ql-%' OR c.slug = 'ga-qulaqliq'
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug LIKE 'ql-%' OR c.slug = 'ga-qulaqliq')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'huawei' THEN 'Çin'
  WHEN 'apple' THEN 'ABŞ'
  WHEN 'samsung' THEN 'Cənubi Koreya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'lenovo' THEN 'Çin'
  WHEN 'sony' THEN 'Yaponiya'
  WHEN 'jbl' THEN 'ABŞ'
  WHEN 'anker' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug LIKE 'ql-%' OR c.slug = 'ga-qulaqliq')
  );

-- ============================================================================
-- Extended technical specification definitions (mobile, IT, home, gaming, etc.)
-- ============================================================================
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
-- Smartphone (PRO)
('sp_display_size', 'Ekran ölçüsü', 'text', NULL, NULL, 1, 1001),
('sp_display_type', 'Ekran tipi', 'text', NULL, NULL, 1, 1002),
('sp_refresh_rate_hz', 'Yenilənmə tezliyi', 'number', 'Hz', NULL, 1, 1003),
('sp_touch_sampling_hz', 'Touch sampling rate', 'number', 'Hz', NULL, 1, 1004),
('sp_brightness_nits', 'Parlaqlıq', 'number', 'nits', NULL, 1, 1005),
('sp_gorilla_glass', 'Gorilla Glass', 'text', NULL, NULL, 1, 1006),
('sp_cpu_model', 'Prosessor (CPU modeli)', 'text', NULL, NULL, 1, 1007),
('sp_gpu', 'GPU', 'text', NULL, NULL, 1, 1008),
('sp_ram_type', 'RAM (tipi LPDDR4/5)', 'text', NULL, NULL, 1, 1009),
('sp_storage_type', 'Yaddaş (UFS 2.2/3.1)', 'text', NULL, NULL, 1, 1010),
('sp_sd_support', 'SD kart dəstəyi', 'boolean', NULL, NULL, 1, 1011),
('sp_camera_sensor', 'Kamera sensoru', 'text', NULL, NULL, 1, 1012),
('sp_aperture', 'Aperture', 'text', NULL, NULL, 1, 1013),
('sp_stabilization', 'OIS/EIS', 'text', NULL, NULL, 1, 1014),
('sp_zoom', 'Zoom (optical/digital)', 'text', NULL, NULL, 1, 1015),
('sp_video_spec', 'Video (4K, 8K, FPS)', 'text', NULL, NULL, 1, 1016),
('sp_battery_mah', 'Batareya', 'number', 'mAh', NULL, 1, 1017),
('sp_fast_charge_w', 'Fast charge', 'number', 'W', NULL, 1, 1018),
('sp_wireless_charge', 'Wireless charging', 'boolean', NULL, NULL, 1, 1019),
('sp_reverse_charge', 'Reverse charging', 'boolean', NULL, NULL, 1, 1020),
('sp_os_version', 'OS versiyası', 'text', NULL, NULL, 1, 1021),
('sp_ui_skin', 'UI (MIUI, OneUI)', 'text', NULL, NULL, 1, 1022),
('sp_5g_bands', '5G bandları', 'text', NULL, NULL, 1, 1023),
('sp_sar_value', 'SAR dəyəri', 'text', NULL, NULL, 1, 1024),
('sp_ip_rating', 'IP sertifikatı (IP67/68)', 'text', NULL, NULL, 1, 1025),
-- Audio
('au_device_type', 'Qulaqlıq / Speaker', 'text', NULL, NULL, 1, 1101),
('au_driver_mm', 'Driver ölçüsü', 'number', 'mm', NULL, 1, 1102),
('au_impedance_ohm', 'Impedance', 'number', 'Ohm', NULL, 1, 1103),
('au_sensitivity_db', 'Sensitivity', 'number', 'dB', NULL, 1, 1104),
('au_frequency_response', 'Frequency response', 'text', NULL, NULL, 1, 1105),
('au_codec', 'Codec (AAC, aptX, LDAC)', 'text', NULL, NULL, 1, 1106),
('au_latency_ms', 'Latency', 'number', 'ms', NULL, 1, 1107),
('au_anc_level', 'ANC səviyyəsi', 'text', NULL, NULL, 1, 1108),
('au_transparency_mode', 'Transparency mode', 'boolean', NULL, NULL, 1, 1109),
('au_multi_device', 'Multi-device support', 'boolean', NULL, NULL, 1, 1110),
-- Laptop
('lp_cpu_cores', 'CPU core sayı', 'number', NULL, NULL, 1, 1201),
('lp_cpu_threads', 'Thread sayı', 'number', NULL, NULL, 1, 1202),
('lp_cpu_base_boost_ghz', 'CPU base/boost', 'text', 'GHz', NULL, 1, 1203),
('lp_gpu_vram', 'GPU VRAM', 'text', NULL, NULL, 1, 1204),
('lp_ram_slots', 'RAM slot sayı', 'number', NULL, NULL, 1, 1205),
('lp_ram_max_support', 'RAM max dəstəyi', 'text', NULL, NULL, 1, 1206),
('lp_ssd_type', 'SSD tipi (NVMe/SATA)', 'text', NULL, NULL, 1, 1207),
('lp_ssd_slots', 'SSD slot sayı', 'number', NULL, NULL, 1, 1208),
('lp_display_gamut', 'Display color gamut (sRGB, DCI-P3)', 'text', NULL, NULL, 1, 1209),
('lp_refresh_rate_hz', 'Refresh rate', 'number', 'Hz', NULL, 1, 1210),
('lp_touchscreen', 'Touchscreen', 'boolean', NULL, NULL, 1, 1211),
('lp_webcam_mp', 'Webcam', 'text', 'MP', NULL, 1, 1212),
('lp_fingerprint', 'Fingerprint', 'boolean', NULL, NULL, 1, 1213),
('lp_keyboard_backlight', 'Keyboard backlight', 'boolean', NULL, NULL, 1, 1214),
('lp_battery_wh', 'Battery', 'number', 'Wh', NULL, 1, 1215),
('lp_adapter_w', 'Adapter gücü', 'number', 'W', NULL, 1, 1216),
('lp_material', 'Material (aluminum/plastic)', 'text', NULL, NULL, 1, 1217),
-- Monitor
('mn_panel_type', 'Panel tipi (IPS/VA/TN)', 'text', NULL, NULL, 1, 1301),
('mn_refresh_rate_hz', 'Refresh rate', 'number', 'Hz', NULL, 1, 1302),
('mn_response_time_ms', 'Response time', 'number', 'ms', NULL, 1, 1303),
('mn_color_accuracy', 'Color accuracy (% sRGB)', 'text', NULL, NULL, 1, 1304),
('mn_hdr_level', 'HDR level', 'text', NULL, NULL, 1, 1305),
('mn_sync_tech', 'G-Sync / FreeSync', 'text', NULL, NULL, 1, 1306),
('mn_viewing_angle', 'Viewing angle', 'text', NULL, NULL, 1, 1307),
('mn_ports', 'Ports', 'text', NULL, NULL, 1, 1308),
-- PC components
('pc_cpu', 'CPU', 'text', NULL, NULL, 1, 1401),
('pc_socket', 'Socket', 'text', NULL, NULL, 1, 1402),
('pc_core_thread', 'Core/Thread', 'text', NULL, NULL, 1, 1403),
('pc_cache', 'Cache', 'text', NULL, NULL, 1, 1404),
('pc_tdp_w', 'TDP', 'number', 'W', NULL, 1, 1405),
('pc_gpu', 'GPU', 'text', NULL, NULL, 1, 1406),
('pc_vram', 'VRAM', 'text', NULL, NULL, 1, 1407),
('pc_memory_type', 'Memory type', 'text', NULL, NULL, 1, 1408),
('pc_clock_speed', 'Clock speed', 'text', NULL, NULL, 1, 1409),
('pc_ram_frequency_mhz', 'RAM Frequency', 'number', 'MHz', NULL, 1, 1410),
('pc_cl_latency', 'CL latency', 'text', NULL, NULL, 1, 1411),
('pc_ssd_rw_speed', 'SSD Read/Write speed', 'text', 'MB/s', NULL, 1, 1412),
-- Smart home
('rv_suction_pa', 'Sorma gücü', 'number', 'Pa', NULL, 1, 1501),
('rv_navigation', 'Navigasiya (Lidar / Camera)', 'text', NULL, NULL, 1, 1502),
('rv_battery_mah', 'Batareya', 'number', 'mAh', NULL, 1, 1503),
('rv_runtime', 'İşləmə müddəti', 'text', NULL, NULL, 1, 1504),
('rv_water_tank_ml', 'Su tankı', 'number', 'ml', NULL, 1, 1505),
('rv_mapping', 'Xəritələmə', 'boolean', NULL, NULL, 1, 1506),
('rv_app_support', 'App dəstəyi', 'boolean', NULL, NULL, 1, 1507),
('rv_voice_control', 'Səsli idarə (Alexa/Google)', 'text', NULL, NULL, 1, 1508),
('ac_btu', 'BTU', 'number', NULL, NULL, 1, 1511),
('ac_inverter', 'İnverter', 'boolean', NULL, NULL, 1, 1512),
('ac_energy_class', 'Enerji sinfi', 'text', NULL, NULL, 1, 1513),
('ac_noise_level_db', 'Səs səviyyəsi', 'number', 'dB', NULL, 1, 1514),
('ac_coverage_m2', 'Soyutma sahəsi', 'number', 'm²', NULL, 1, 1515),
('ac_wifi', 'Wi-Fi', 'boolean', NULL, NULL, 1, 1516),
('cf_pressure_bar', 'Təzyiq', 'number', 'bar', NULL, 1, 1521),
('cf_water_tank', 'Su tankı', 'text', NULL, NULL, 1, 1522),
('cf_bean_grinder', 'Bean grinder', 'boolean', NULL, NULL, 1, 1523),
('cf_auto_programs', 'Avtomatik proqramlar', 'text', NULL, NULL, 1, 1524),
-- Auto electronics
('dc_video_quality', 'Video keyfiyyəti', 'text', NULL, NULL, 1, 1601),
('dc_fps', 'FPS', 'number', NULL, NULL, 1, 1602),
('dc_night_vision', 'Night vision', 'boolean', NULL, NULL, 1, 1603),
('dc_g_sensor', 'G-sensor', 'boolean', NULL, NULL, 1, 1604),
('dc_loop_recording', 'Loop recording', 'boolean', NULL, NULL, 1, 1605),
('dc_gps', 'GPS', 'boolean', NULL, NULL, 1, 1606),
('pb_capacity_mah', 'Tutum', 'number', 'mAh', NULL, 1, 1611),
('pb_output_w', 'Output', 'number', 'W', NULL, 1, 1612),
('pb_port_count', 'Port sayı', 'number', NULL, NULL, 1, 1613),
('pb_fast_charge', 'Fast charge', 'boolean', NULL, NULL, 1, 1614),
-- Sport & health
('ft_sensors', 'Sensorlar', 'text', NULL, NULL, 1, 1701),
('ft_step_counter', 'Addım sayğacı', 'boolean', NULL, NULL, 1, 1702),
('ft_calorie_tracking', 'Kalori ölçümü', 'boolean', NULL, NULL, 1, 1703),
('ft_sleep_tracking', 'Sleep tracking', 'boolean', NULL, NULL, 1, 1704),
('ft_stress_monitor', 'Stress monitor', 'boolean', NULL, NULL, 1, 1705),
('sc_max_weight_kg', 'Maksimum çəki', 'number', 'kg', NULL, 1, 1711),
('sc_body_fat_percent', 'Body fat %', 'number', '%', NULL, 1, 1712),
('sc_bmi', 'BMI', 'boolean', NULL, NULL, 1, 1713),
('sc_bluetooth', 'Bluetooth', 'boolean', NULL, NULL, 1, 1714),
-- Gaming
('gm_dpi_max', 'DPI (max)', 'number', NULL, NULL, 1, 1801),
('gm_polling_rate_hz', 'Polling rate', 'number', 'Hz', NULL, 1, 1802),
('gm_switch_type', 'Switch type', 'text', NULL, NULL, 1, 1803),
('gm_rgb', 'RGB', 'boolean', NULL, NULL, 1, 1804),
('gh_surround', 'Surround (7.1)', 'text', NULL, NULL, 1, 1811),
('gh_mic_sensitivity', 'Mic sensitivity', 'text', NULL, NULL, 1, 1812),
('gh_rgb', 'RGB', 'boolean', NULL, NULL, 1, 1813),
-- Network
('rt_wifi_standard', 'Wi-Fi standartı (Wi-Fi 5/6/7)', 'text', NULL, NULL, 1, 1901),
('rt_speed_mbps', 'Speed', 'number', 'Mbps', NULL, 1, 1902),
('rt_band', 'Band (2.4/5 GHz)', 'text', NULL, NULL, 1, 1903),
('rt_antenna_count', 'Antenna sayı', 'number', NULL, NULL, 1, 1904),
('rt_coverage_m2', 'Coverage', 'number', 'm²', NULL, 1, 1905),
('rt_mu_mimo', 'MU-MIMO', 'boolean', NULL, NULL, 1, 1906),
('rt_ports', 'Ports', 'text', NULL, NULL, 1, 1907),
-- Power
('ups_power_va_w', 'Güc (VA/W)', 'text', NULL, NULL, 1, 2001),
('ups_backup_time', 'Backup time', 'text', NULL, NULL, 1, 2002),
('ups_output_type', 'Output tipi', 'text', NULL, NULL, 1, 2003),
-- Professional
('pr_print_type', 'Print tipi (Laser/Inkjet)', 'text', NULL, NULL, 1, 2101),
('pr_dpi', 'DPI', 'number', NULL, NULL, 1, 2102),
('pr_speed_ppm', 'Print speed', 'number', 'ppm', NULL, 1, 2103),
('pr_duplex', 'Duplex', 'boolean', NULL, NULL, 1, 2104),
('sv_cpu', 'Server CPU', 'text', NULL, NULL, 1, 2111),
('sv_ram', 'Server RAM', 'text', NULL, NULL, 1, 2112),
('sv_storage', 'Server Storage', 'text', NULL, NULL, 1, 2113),
('sv_raid', 'RAID', 'text', NULL, NULL, 1, 2114)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, x.is_required, x.sort_order
FROM (
  -- Smartphone
  SELECT 'sf-smartfonlar' AS category_slug, 'sp_display_size' AS spec_key, 1 AS is_required, 1 AS sort_order
  UNION ALL SELECT 'sf-smartfonlar', 'sp_display_type', 1, 2
  UNION ALL SELECT 'sf-smartfonlar', 'sp_refresh_rate_hz', 0, 3
  UNION ALL SELECT 'sf-smartfonlar', 'sp_touch_sampling_hz', 0, 4
  UNION ALL SELECT 'sf-smartfonlar', 'sp_brightness_nits', 0, 5
  UNION ALL SELECT 'sf-smartfonlar', 'sp_gorilla_glass', 0, 6
  UNION ALL SELECT 'sf-smartfonlar', 'sp_cpu_model', 1, 7
  UNION ALL SELECT 'sf-smartfonlar', 'sp_gpu', 0, 8
  UNION ALL SELECT 'sf-smartfonlar', 'sp_ram_type', 1, 9
  UNION ALL SELECT 'sf-smartfonlar', 'sp_storage_type', 1, 10
  UNION ALL SELECT 'sf-smartfonlar', 'sp_sd_support', 0, 11
  UNION ALL SELECT 'sf-smartfonlar', 'sp_camera_sensor', 0, 12
  UNION ALL SELECT 'sf-smartfonlar', 'sp_aperture', 0, 13
  UNION ALL SELECT 'sf-smartfonlar', 'sp_stabilization', 0, 14
  UNION ALL SELECT 'sf-smartfonlar', 'sp_zoom', 0, 15
  UNION ALL SELECT 'sf-smartfonlar', 'sp_video_spec', 0, 16
  UNION ALL SELECT 'sf-smartfonlar', 'sp_battery_mah', 1, 17
  UNION ALL SELECT 'sf-smartfonlar', 'sp_fast_charge_w', 0, 18
  UNION ALL SELECT 'sf-smartfonlar', 'sp_wireless_charge', 0, 19
  UNION ALL SELECT 'sf-smartfonlar', 'sp_reverse_charge', 0, 20
  UNION ALL SELECT 'sf-smartfonlar', 'sp_os_version', 0, 21
  UNION ALL SELECT 'sf-smartfonlar', 'sp_ui_skin', 0, 22
  UNION ALL SELECT 'sf-smartfonlar', 'sp_5g_bands', 0, 23
  UNION ALL SELECT 'sf-smartfonlar', 'sp_sar_value', 0, 24
  UNION ALL SELECT 'sf-smartfonlar', 'sp_ip_rating', 0, 25
  -- Audio
  UNION ALL SELECT 'ql-butun', 'au_device_type', 1, 1
  UNION ALL SELECT 'ql-butun', 'au_driver_mm', 0, 2
  UNION ALL SELECT 'ql-butun', 'au_impedance_ohm', 0, 3
  UNION ALL SELECT 'ql-butun', 'au_sensitivity_db', 0, 4
  UNION ALL SELECT 'ql-butun', 'au_frequency_response', 0, 5
  UNION ALL SELECT 'ql-butun', 'au_codec', 0, 6
  UNION ALL SELECT 'ql-butun', 'au_latency_ms', 0, 7
  UNION ALL SELECT 'ql-butun', 'au_anc_level', 0, 8
  UNION ALL SELECT 'ql-butun', 'au_transparency_mode', 0, 9
  UNION ALL SELECT 'ql-butun', 'au_multi_device', 0, 10
  UNION ALL SELECT 'ql-simsiz', 'au_codec', 0, 1
  UNION ALL SELECT 'ql-simsiz', 'au_latency_ms', 0, 2
  UNION ALL SELECT 'ql-simsiz', 'au_anc_level', 0, 3
  UNION ALL SELECT 'ql-simsiz', 'au_multi_device', 0, 4
  UNION ALL SELECT 'ga-qulaqliq', 'gh_surround', 0, 1
  UNION ALL SELECT 'ga-qulaqliq', 'gh_mic_sensitivity', 0, 2
  UNION ALL SELECT 'ga-qulaqliq', 'gh_rgb', 0, 3
  UNION ALL SELECT 'au-kalonka', 'au_device_type', 1, 1
  UNION ALL SELECT 'au-kalonka', 'au_frequency_response', 0, 2
  UNION ALL SELECT 'au-kalonka', 'au_latency_ms', 0, 3
  -- Laptop
  UNION ALL SELECT 'nb-butun', 'lp_cpu_cores', 1, 1
  UNION ALL SELECT 'nb-butun', 'lp_cpu_threads', 1, 2
  UNION ALL SELECT 'nb-butun', 'lp_cpu_base_boost_ghz', 0, 3
  UNION ALL SELECT 'nb-butun', 'lp_gpu_vram', 0, 4
  UNION ALL SELECT 'nb-butun', 'lp_ram_slots', 0, 5
  UNION ALL SELECT 'nb-butun', 'lp_ram_max_support', 0, 6
  UNION ALL SELECT 'nb-butun', 'lp_ssd_type', 1, 7
  UNION ALL SELECT 'nb-butun', 'lp_ssd_slots', 0, 8
  UNION ALL SELECT 'nb-butun', 'lp_display_gamut', 0, 9
  UNION ALL SELECT 'nb-butun', 'lp_refresh_rate_hz', 0, 10
  UNION ALL SELECT 'nb-butun', 'lp_touchscreen', 0, 11
  UNION ALL SELECT 'nb-butun', 'lp_webcam_mp', 0, 12
  UNION ALL SELECT 'nb-butun', 'lp_fingerprint', 0, 13
  UNION ALL SELECT 'nb-butun', 'lp_keyboard_backlight', 0, 14
  UNION ALL SELECT 'nb-butun', 'lp_battery_wh', 1, 15
  UNION ALL SELECT 'nb-butun', 'lp_adapter_w', 0, 16
  UNION ALL SELECT 'nb-butun', 'lp_material', 0, 17
  UNION ALL SELECT 'nb-oyun', 'lp_refresh_rate_hz', 1, 1
  UNION ALL SELECT 'nb-oyun', 'lp_gpu_vram', 1, 2
  UNION ALL SELECT 'nb-is', 'lp_battery_wh', 1, 1
  UNION ALL SELECT 'nb-ultra-yungul', 'lp_material', 0, 1
  -- Monitor
  UNION ALL SELECT 'mn-butun', 'mn_panel_type', 1, 1
  UNION ALL SELECT 'mn-butun', 'mn_refresh_rate_hz', 1, 2
  UNION ALL SELECT 'mn-butun', 'mn_response_time_ms', 0, 3
  UNION ALL SELECT 'mn-butun', 'mn_color_accuracy', 0, 4
  UNION ALL SELECT 'mn-butun', 'mn_hdr_level', 0, 5
  UNION ALL SELECT 'mn-butun', 'mn_sync_tech', 0, 6
  UNION ALL SELECT 'mn-butun', 'mn_viewing_angle', 0, 7
  UNION ALL SELECT 'mn-butun', 'mn_ports', 0, 8
  UNION ALL SELECT 'mn-oyun', 'mn_refresh_rate_hz', 1, 1
  UNION ALL SELECT 'mn-oyun', 'mn_response_time_ms', 1, 2
  UNION ALL SELECT 'mn-oyun', 'mn_sync_tech', 0, 3
  UNION ALL SELECT 'mn-ofis', 'mn_color_accuracy', 0, 1
  -- PC + Server
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_cpu', 1, 1
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_core_thread', 1, 2
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_gpu', 0, 3
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_vram', 0, 4
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_ram_frequency_mhz', 0, 5
  UNION ALL SELECT 'pc-hazir-sistem', 'pc_ssd_rw_speed', 0, 6
  UNION ALL SELECT 'pc-hazir-sistem', 'sv_cpu', 0, 7
  UNION ALL SELECT 'pc-hazir-sistem', 'sv_ram', 0, 8
  UNION ALL SELECT 'pc-hazir-sistem', 'sv_storage', 0, 9
  UNION ALL SELECT 'pc-hazir-sistem', 'sv_raid', 0, 10
  -- Smart home
  UNION ALL SELECT 'km-tozsoran', 'rv_suction_pa', 1, 1
  UNION ALL SELECT 'km-tozsoran', 'rv_navigation', 0, 2
  UNION ALL SELECT 'km-tozsoran', 'rv_battery_mah', 0, 3
  UNION ALL SELECT 'km-tozsoran', 'rv_runtime', 0, 4
  UNION ALL SELECT 'km-tozsoran', 'rv_water_tank_ml', 0, 5
  UNION ALL SELECT 'km-tozsoran', 'rv_mapping', 0, 6
  UNION ALL SELECT 'km-tozsoran', 'rv_app_support', 0, 7
  UNION ALL SELECT 'km-tozsoran', 'rv_voice_control', 0, 8
  UNION ALL SELECT 'iq-kondisioner', 'ac_btu', 1, 1
  UNION ALL SELECT 'iq-kondisioner', 'ac_inverter', 0, 2
  UNION ALL SELECT 'iq-kondisioner', 'ac_energy_class', 0, 3
  UNION ALL SELECT 'iq-kondisioner', 'ac_noise_level_db', 0, 4
  UNION ALL SELECT 'iq-kondisioner', 'ac_coverage_m2', 0, 5
  UNION ALL SELECT 'iq-kondisioner', 'ac_wifi', 0, 6
  UNION ALL SELECT 'km-qehve', 'cf_pressure_bar', 1, 1
  UNION ALL SELECT 'km-qehve', 'cf_water_tank', 0, 2
  UNION ALL SELECT 'km-qehve', 'cf_bean_grinder', 0, 3
  UNION ALL SELECT 'km-qehve', 'cf_auto_programs', 0, 4
  -- Health + Gaming + Network + Professional
  UNION ALL SELECT 'km-terezi', 'sc_max_weight_kg', 1, 1
  UNION ALL SELECT 'km-terezi', 'sc_body_fat_percent', 0, 2
  UNION ALL SELECT 'km-terezi', 'sc_bmi', 0, 3
  UNION ALL SELECT 'km-terezi', 'sc_bluetooth', 0, 4
  UNION ALL SELECT 'ss-smart-saatlar', 'ft_sensors', 0, 1
  UNION ALL SELECT 'ss-smart-saatlar', 'ft_step_counter', 0, 2
  UNION ALL SELECT 'ss-smart-saatlar', 'ft_calorie_tracking', 0, 3
  UNION ALL SELECT 'ss-smart-saatlar', 'ft_sleep_tracking', 0, 4
  UNION ALL SELECT 'ss-smart-saatlar', 'ft_stress_monitor', 0, 5
  UNION ALL SELECT 'ak-sican-klaviatura', 'gm_dpi_max', 0, 1
  UNION ALL SELECT 'ak-sican-klaviatura', 'gm_polling_rate_hz', 0, 2
  UNION ALL SELECT 'ak-sican-klaviatura', 'gm_switch_type', 0, 3
  UNION ALL SELECT 'ak-sican-klaviatura', 'gm_rgb', 0, 4
  UNION ALL SELECT 'nw-router', 'rt_wifi_standard', 1, 1
  UNION ALL SELECT 'nw-router', 'rt_speed_mbps', 1, 2
  UNION ALL SELECT 'nw-router', 'rt_band', 1, 3
  UNION ALL SELECT 'nw-router', 'rt_antenna_count', 0, 4
  UNION ALL SELECT 'nw-router', 'rt_coverage_m2', 0, 5
  UNION ALL SELECT 'nw-router', 'rt_mu_mimo', 0, 6
  UNION ALL SELECT 'nw-router', 'rt_ports', 0, 7
  UNION ALL SELECT 'pr-printer', 'pr_print_type', 1, 1
  UNION ALL SELECT 'pr-printer', 'pr_dpi', 0, 2
  UNION ALL SELECT 'pr-printer', 'pr_speed_ppm', 0, 3
  UNION ALL SELECT 'pr-printer', 'pr_duplex', 0, 4
) AS x
INNER JOIN categories c ON c.slug = x.category_slug
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);