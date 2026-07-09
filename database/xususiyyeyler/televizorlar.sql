USE maxhome_db;

-- Televizorlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tv_brand', 'Brend', 'text', NULL, NULL, 1, 2901),
('tv_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2902),
('tv_panel', 'Matris tipi', 'text', NULL, NULL, 1, 2903),
('tv_cpu', 'Prosessor tipi', 'text', NULL, NULL, 1, 2904),
('tv_os', 'Əməliyyat sistemi', 'text', NULL, NULL, 1, 2905),
('tv_refresh_rate', 'Tezlik diapazonu, Hz', 'text', NULL, NULL, 1, 2906),
('tv_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2907),
('tv_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2908),
('tv_wifi', 'Wi-Fi', 'text', NULL, NULL, 1, 2909),
('tv_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 2910),
('tv_usb', 'USB', 'text', NULL, NULL, 1, 2911),
('tv_hdmi', 'HDMI', 'text', NULL, NULL, 1, 2912),
('tv_optical', 'Optik çıxış', 'text', NULL, NULL, 1, 2913),
('tv_sound', 'Həcmli səslənmə', 'text', NULL, NULL, 1, 2914),
('tv_color', 'Rəng', 'text', NULL, NULL, 1, 2915),
('tv_weight', 'Çəki altlıqsız (kq)', 'text', NULL, NULL, 1, 2916),
('tv_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2917),
('tv_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2918)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Televizor kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tv_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tv_country', 2
  UNION ALL SELECT 'tv_panel', 3
  UNION ALL SELECT 'tv_cpu', 4
  UNION ALL SELECT 'tv_os', 5
  UNION ALL SELECT 'tv_refresh_rate', 6
  UNION ALL SELECT 'tv_resolution', 7
  UNION ALL SELECT 'tv_size', 8
  UNION ALL SELECT 'tv_wifi', 9
  UNION ALL SELECT 'tv_bluetooth', 10
  UNION ALL SELECT 'tv_usb', 11
  UNION ALL SELECT 'tv_hdmi', 12
  UNION ALL SELECT 'tv_optical', 13
  UNION ALL SELECT 'tv_sound', 14
  UNION ALL SELECT 'tv_color', 15
  UNION ALL SELECT 'tv_weight', 16
  UNION ALL SELECT 'tv_warranty', 17
  UNION ALL SELECT 'tv_included', 18
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'tv-audio-video-b-01-televizorlar',
  'tv-led',
  'tv-oled-qled',
  'tv-butun'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Televizor mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tv-audio-video-b-01-televizorlar', 'tv-led', 'tv-oled-qled', 'tv-butun');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Matris tipi', 'VA', 3
  UNION ALL SELECT 'Prosessor tipi', 'NQ8 AI Gen2 Processor', 4
  UNION ALL SELECT 'Əməliyyat sistemi', 'Tizen', 5
  UNION ALL SELECT 'Tezlik diapazonu, Hz', '120 Hz', 6
  UNION ALL SELECT 'Ekran icazəsi', '8K (7680x4320)', 7
  UNION ALL SELECT 'Ekranın ölçüsü', '85"(215sm)', 8
  UNION ALL SELECT 'Wi-Fi', 'var', 9
  UNION ALL SELECT 'Bluetooth', 'var', 10
  UNION ALL SELECT 'USB', '2', 11
  UNION ALL SELECT 'HDMI', '4', 12
  UNION ALL SELECT 'Optik çıxış', 'var', 13
  UNION ALL SELECT 'Həcmli səslənmə', 'OTS +', 14
  UNION ALL SELECT 'Rəng', 'Qara', 15
  UNION ALL SELECT 'Çəki altlıqsız (kq)', '45.5 kq', 16
  UNION ALL SELECT 'Zəmanət', '1 il', 17
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Pult', 18
) x
WHERE c.slug IN ('tv-audio-video-b-01-televizorlar', 'tv-led', 'tv-oled-qled', 'tv-butun')
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
      AND c.slug IN ('tv-audio-video-b-01-televizorlar', 'tv-led', 'tv-oled-qled', 'tv-butun')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'samsung' THEN 'Misir'
  WHEN 'sony' THEN 'Malayziya'
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'tcl' THEN 'Çin'
  WHEN 'hisense' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('tv-audio-video-b-01-televizorlar', 'tv-led', 'tv-oled-qled', 'tv-butun')
  );
