USE maxhome_db;

-- Smart saatlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('sw_brand', 'Brend', 'text', NULL, NULL, 1, 2401),
('sw_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2402),
('sw_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2403),
('sw_case_color', 'Korpusun rəngi', 'text', NULL, NULL, 1, 2404),
('sw_strap_color', 'Bilərziyin rəngi', 'text', NULL, NULL, 1, 2405),
('sw_case_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 2406),
('sw_strap_material', 'Bilərzik materialı', 'text', NULL, NULL, 1, 2407),
('sw_display_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2408),
('sw_display_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2409),
('sw_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 2410),
('sw_water_protection', 'Sudan və tozdan qorunma', 'text', NULL, NULL, 1, 2411),
('sw_supported_formats', 'Dəstəklənən formatlar', 'text', NULL, NULL, 1, 2412),
('sw_weight', 'Çəki', 'text', NULL, NULL, 1, 2413),
('sw_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2414),
('sw_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2415),
('sw_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2416)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Smart saatlar kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'sw_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'sw_country', 2
  UNION ALL SELECT 'sw_product_type', 3
  UNION ALL SELECT 'sw_case_color', 4
  UNION ALL SELECT 'sw_strap_color', 5
  UNION ALL SELECT 'sw_case_material', 6
  UNION ALL SELECT 'sw_strap_material', 7
  UNION ALL SELECT 'sw_display_size', 8
  UNION ALL SELECT 'sw_display_resolution', 9
  UNION ALL SELECT 'sw_bluetooth', 10
  UNION ALL SELECT 'sw_water_protection', 11
  UNION ALL SELECT 'sw_supported_formats', 12
  UNION ALL SELECT 'sw_weight', 13
  UNION ALL SELECT 'sw_dimensions', 14
  UNION ALL SELECT 'sw_warranty', 15
  UNION ALL SELECT 'sw_included', 16
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'ss-smart-saatlar',
  'ss-smart-saatlar-apple',
  'ss-smart-saatlar-borofone',
  'ss-smart-saatlar-garmin',
  'ss-smart-saatlar-huawei',
  'ss-smart-saatlar-samsung',
  'ss-smart-saatlar-ttec',
  'ss-smart-saatlar-wonlex',
  'ss-smart-saatlar-xiaomi',
  'ss-smart-saatlar-butun-brendler'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Smart saat mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug LIKE 'ss-smart-saatlar%';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Smart Saat', 3
  UNION ALL SELECT 'Korpusun rəngi', 'Gümüşü', 4
  UNION ALL SELECT 'Bilərziyin rəngi', 'Ağ', 5
  UNION ALL SELECT 'Korpus Materialı', 'Metal', 6
  UNION ALL SELECT 'Bilərzik materialı', 'Silikon', 7
  UNION ALL SELECT 'Ekranın ölçüsü', '1.62"', 8
  UNION ALL SELECT 'Ekran icazəsi', '286 x 482', 9
  UNION ALL SELECT 'Bluetooth', 'var', 10
  UNION ALL SELECT 'Sudan və tozdan qorunma', '5 ATM', 11
  UNION ALL SELECT 'Dəstəklənən formatlar', 'Android, IOS', 12
  UNION ALL SELECT 'Çəki', '17 qr', 13
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '42.6 x 28.2 x 8.99 mm', 14
  UNION ALL SELECT 'Zəmanət', '1 il', 15
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Saat,kəmər,şarj aparatı', 16
) x
WHERE c.slug LIKE 'ss-smart-saatlar%'
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
      AND c.slug LIKE 'ss-smart-saatlar%'
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'huawei' THEN 'Çin'
  WHEN 'apple' THEN 'ABŞ'
  WHEN 'samsung' THEN 'Cənubi Koreya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'garmin' THEN 'ABŞ'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug LIKE 'ss-smart-saatlar%'
  );
