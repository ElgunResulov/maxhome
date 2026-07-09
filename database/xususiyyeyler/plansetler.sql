USE maxhome_db;

-- Plansetler ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tb_brand', 'Brend', 'text', NULL, NULL, 1, 2501),
('tb_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2502),
('tb_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2503),
('tb_os', 'Əməliyyat sistemi', 'text', NULL, NULL, 1, 2504),
('tb_chipset', 'Prosessor tipi', 'text', NULL, NULL, 1, 2505),
('tb_cpu_speed', 'Prosessor tezliyi', 'text', NULL, NULL, 1, 2506),
('tb_cpu_cores', 'Nüvələrin sayı', 'number', NULL, NULL, 1, 2507),
('tb_gpu', 'Qrafik prosessor', 'text', NULL, NULL, 1, 2508),
('tb_ram', 'Operativ yaddaş', 'text', NULL, NULL, 1, 2509),
('tb_storage', 'Quraşdırılmış yaddaş', 'text', NULL, NULL, 1, 2510),
('tb_display_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2511),
('tb_display_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2512),
('tb_main_camera', 'Əsas kamera', 'text', NULL, NULL, 1, 2513),
('tb_rear_camera', 'Kamera', 'text', NULL, NULL, 1, 2514),
('tb_front_camera', 'Frontal kamera', 'text', NULL, NULL, 1, 2515),
('tb_video_recording', 'Video çəkiliş', 'text', NULL, NULL, 1, 2516),
('tb_autofocus', 'Avtofokus', 'text', NULL, NULL, 1, 2517),
('tb_led_flash', 'Led flaș', 'text', NULL, NULL, 1, 2518),
('tb_wifi', 'Wi-Fi', 'text', NULL, NULL, 1, 2519),
('tb_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 2520),
('tb_gnss', 'Naviqasiya sistemi', 'text', NULL, NULL, 1, 2521),
('tb_ip_rating', 'Tozdan və sudan qorunma', 'text', NULL, NULL, 1, 2522),
('tb_usb', 'Şarj bağlayıcısı', 'text', NULL, NULL, 1, 2523),
('tb_extra_features', 'Əlavə xüsusiyyətlər', 'text', NULL, NULL, 1, 2524),
('tb_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 2525),
('tb_color', 'Rəng', 'text', NULL, NULL, 1, 2526),
('tb_weight', 'Çəki', 'text', NULL, NULL, 1, 2527),
('tb_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2528),
('tb_battery_capacity', 'Akkumulyatorun həcmi', 'text', NULL, NULL, 1, 2529),
('tb_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2530),
('tb_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2531)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Planset kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tb_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tb_country', 2
  UNION ALL SELECT 'tb_product_type', 3
  UNION ALL SELECT 'tb_os', 4
  UNION ALL SELECT 'tb_chipset', 5
  UNION ALL SELECT 'tb_cpu_speed', 6
  UNION ALL SELECT 'tb_cpu_cores', 7
  UNION ALL SELECT 'tb_gpu', 8
  UNION ALL SELECT 'tb_ram', 9
  UNION ALL SELECT 'tb_storage', 10
  UNION ALL SELECT 'tb_display_size', 11
  UNION ALL SELECT 'tb_display_resolution', 12
  UNION ALL SELECT 'tb_main_camera', 13
  UNION ALL SELECT 'tb_rear_camera', 14
  UNION ALL SELECT 'tb_front_camera', 15
  UNION ALL SELECT 'tb_video_recording', 16
  UNION ALL SELECT 'tb_autofocus', 17
  UNION ALL SELECT 'tb_led_flash', 18
  UNION ALL SELECT 'tb_wifi', 19
  UNION ALL SELECT 'tb_bluetooth', 20
  UNION ALL SELECT 'tb_gnss', 21
  UNION ALL SELECT 'tb_ip_rating', 22
  UNION ALL SELECT 'tb_usb', 23
  UNION ALL SELECT 'tb_extra_features', 24
  UNION ALL SELECT 'tb_body_material', 25
  UNION ALL SELECT 'tb_color', 26
  UNION ALL SELECT 'tb_weight', 27
  UNION ALL SELECT 'tb_dimensions', 28
  UNION ALL SELECT 'tb_battery_capacity', 29
  UNION ALL SELECT 'tb_warranty', 30
  UNION ALL SELECT 'tb_included', 31
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'pl-planshetler',
  'pl-planshetler-apple',
  'pl-planshetler-samsung',
  'pl-planshetler-xiaomi',
  'pl-planshetler-lenovo',
  'pl-planshetler-huawei',
  'pl-planshetler-poco',
  'pl-planshetler-butun-brendler'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Planset mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug LIKE 'pl-planshetler%';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Planşet', 3
  UNION ALL SELECT 'Əməliyyat sistemi', 'Android 15', 4
  UNION ALL SELECT 'Prosessor tipi', 'Mali-G57 MC2', 5
  UNION ALL SELECT 'Prosessor tezliyi', '2x2.5 GHz Cortex-A76', 6
  UNION ALL SELECT 'Nüvələrin sayı', '8', 7
  UNION ALL SELECT 'Qrafik prosessor', 'Mali-G57 MC2', 8
  UNION ALL SELECT 'Operativ yaddaş', '12 GB', 9
  UNION ALL SELECT 'Quraşdırılmış yaddaş', '256 GB', 10
  UNION ALL SELECT 'Ekranın ölçüsü', '12.1"', 11
  UNION ALL SELECT 'Ekran icazəsi', '2560 x 1600', 12
  UNION ALL SELECT 'Əsas kamera', '13 MP', 13
  UNION ALL SELECT 'Kamera', '13 MP', 14
  UNION ALL SELECT 'Frontal kamera', '8 MP', 15
  UNION ALL SELECT 'Video çəkiliş', '1080p@30fps', 16
  UNION ALL SELECT 'Avtofokus', 'var', 17
  UNION ALL SELECT 'Led flaș', 'var', 18
  UNION ALL SELECT 'Wi-Fi', 'Wi-Fi 802.11 a/b/g/n/ac', 19
  UNION ALL SELECT 'Bluetooth', '5.2, A2DP, LE', 20
  UNION ALL SELECT 'Naviqasiya sistemi', 'GPS, GALILEO, GLONASS', 21
  UNION ALL SELECT 'Tozdan və sudan qorunma', 'IP52', 22
  UNION ALL SELECT 'Şarj bağlayıcısı', 'USB Type-C 2.0', 23
  UNION ALL SELECT 'Əlavə xüsusiyyətlər', 'WiFi-li', 24
  UNION ALL SELECT 'Korpus Materialı', 'Üst şüşə / Alüminium,plastik', 25
  UNION ALL SELECT 'Rəng', 'Boz', 26
  UNION ALL SELECT 'Çəki', '530 qr', 27
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '278.8 x 181.1 x 6.3 mm', 28
  UNION ALL SELECT 'Akkumulyatorun həcmi', 'Li-Ion 10200 mAh', 29
  UNION ALL SELECT 'Zəmanət', '1 il', 30
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Pad, Adapter, USB Type-C Cable', 31
) x
WHERE c.slug LIKE 'pl-planshetler%'
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
      AND c.slug LIKE 'pl-planshetler%'
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'apple' THEN 'ABŞ'
  WHEN 'samsung' THEN 'Cənubi Koreya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'lenovo' THEN 'Çin'
  WHEN 'huawei' THEN 'Çin'
  WHEN 'poco' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug LIKE 'pl-planshetler%'
  );
