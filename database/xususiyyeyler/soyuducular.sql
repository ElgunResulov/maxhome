USE maxhome_db;

-- Soyuducular ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('rf_brand', 'Brend', 'text', NULL, NULL, 1, 3201),
('rf_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3202),
('rf_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3203),
('rf_energy_class', 'Enerji sərfiyyatı sinfi', 'text', NULL, NULL, 1, 3204),
('rf_cooler_sections', 'Soyuducu kameranın bölmələrinin sayı', 'text', NULL, NULL, 1, 3205),
('rf_compressor_count', 'Kompressor sayı', 'text', NULL, NULL, 1, 3206),
('rf_color', 'Rəng', 'text', NULL, NULL, 1, 3207),
('rf_weight', 'Çəki', 'text', NULL, NULL, 1, 3208),
('rf_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3209),
('rf_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3210),
('rf_cooler_type', 'Soyuducu növü', 'text', NULL, NULL, 1, 3211),
('rf_cooler_net', 'Soyuducu kameranın yararlı həcmi', 'text', NULL, NULL, 1, 3212),
('rf_freezer_position', 'Dondurucu kameranın yerləşdirilməsi', 'text', NULL, NULL, 1, 3213),
('rf_freezer_sections', 'Dondurucu kameranın bölmələrinin sayı', 'text', NULL, NULL, 1, 3214),
('rf_freezer_net', 'Dondurucu kameranın yararlı həcmi', 'text', NULL, NULL, 1, 3215),
('rf_freezer_defrost', 'Dondurucu kameranın donun açılması', 'text', NULL, NULL, 1, 3216),
('rf_cooler_defrost', 'Soyuducu kameranın donunun açılması', 'text', NULL, NULL, 1, 3217),
('rf_door_count', 'Qapı sayı', 'text', NULL, NULL, 1, 3218),
('rf_total_net', 'Toplam həcm', 'text', NULL, NULL, 1, 3219),
('rf_light', 'Daxili işıqlandırma soyuducu kamera', 'text', NULL, NULL, 1, 3220)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Soyuducu kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'rf_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'rf_country', 2
  UNION ALL SELECT 'rf_type', 3
  UNION ALL SELECT 'rf_energy_class', 4
  UNION ALL SELECT 'rf_cooler_sections', 5
  UNION ALL SELECT 'rf_compressor_count', 6
  UNION ALL SELECT 'rf_color', 7
  UNION ALL SELECT 'rf_weight', 8
  UNION ALL SELECT 'rf_dimensions', 9
  UNION ALL SELECT 'rf_warranty', 10
  UNION ALL SELECT 'rf_cooler_type', 11
  UNION ALL SELECT 'rf_cooler_net', 12
  UNION ALL SELECT 'rf_freezer_position', 13
  UNION ALL SELECT 'rf_freezer_sections', 14
  UNION ALL SELECT 'rf_freezer_net', 15
  UNION ALL SELECT 'rf_freezer_defrost', 16
  UNION ALL SELECT 'rf_cooler_defrost', 17
  UNION ALL SELECT 'rf_door_count', 18
  UNION ALL SELECT 'rf_total_net', 19
  UNION ALL SELECT 'rf_light', 20
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-soyuducu')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Soyuducu mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-soyuducu');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Soyuducu', 3
  UNION ALL SELECT 'Enerji sərfiyyatı sinfi', 'A+', 4
  UNION ALL SELECT 'Soyuducu kameranın bölmələrinin sayı', '3+2', 5
  UNION ALL SELECT 'Kompressor sayı', '1', 6
  UNION ALL SELECT 'Rəng', 'Gümüşü', 7
  UNION ALL SELECT 'Çəki', '65 kq', 8
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '185x60x66 sm', 9
  UNION ALL SELECT 'Zəmanət', '2 il', 10
  UNION ALL SELECT 'Soyuducu növü', 'Dondurucu aşağıda', 11
  UNION ALL SELECT 'Soyuducu kameranın yararlı həcmi', '223 lt', 12
  UNION ALL SELECT 'Dondurucu kameranın yerləşdirilməsi', 'Aşağıda', 13
  UNION ALL SELECT 'Dondurucu kameranın bölmələrinin sayı', '3', 14
  UNION ALL SELECT 'Dondurucu kameranın yararlı həcmi', '123 lt', 15
  UNION ALL SELECT 'Dondurucu kameranın donun açılması', 'No Frost', 16
  UNION ALL SELECT 'Soyuducu kameranın donunun açılması', 'No Frost', 17
  UNION ALL SELECT 'Qapı sayı', '4', 18
  UNION ALL SELECT 'Toplam həcm', '346 lt', 19
  UNION ALL SELECT 'Daxili işıqlandırma soyuducu kamera', 'var', 20
) x
WHERE c.slug IN ('bk-soyuducu')
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
      AND c.slug IN ('bk-soyuducu')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'midea' THEN 'Çin'
  WHEN 'samsung' THEN 'Çin'
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'bosch' THEN 'Almaniya'
  WHEN 'beko' THEN 'Türkiyə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-soyuducu')
  );
