USE maxhome_db;

-- Mikrodalgali soba ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('mw_brand', 'Brend', 'text', NULL, NULL, 1, 3801),
('mw_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3802),
('mw_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3803),
('mw_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 3804),
('mw_color', 'Rəng', 'text', NULL, NULL, 1, 3805),
('mw_weight', 'Çəki', 'text', NULL, NULL, 1, 3806),
('mw_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3807),
('mw_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3808),
('mw_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3809),
('mw_inner_coating', 'Kameranın daxili örtüyü', 'text', NULL, NULL, 1, 3810),
('mw_volume_l', 'Daxili həcm, l', 'text', NULL, NULL, 1, 3811),
('mw_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 3812),
('mw_door_opening', 'Qapının açılması', 'text', NULL, NULL, 1, 3813),
('mw_light', 'İşıqlandırma', 'text', NULL, NULL, 1, 3814),
('mw_plate_diameter_mm', 'Döndərici hissənin diametri, sm', 'text', NULL, NULL, 1, 3815),
('mw_auto_cook', 'Avtomatik hazırlanma', 'text', NULL, NULL, 1, 3816),
('mw_microwave_power', 'Mikrodalğa gücü, Vt', 'text', NULL, NULL, 1, 3817),
('mw_programmable_cooking', 'Hazırlama prosesin proqramlaşdırılması', 'text', NULL, NULL, 1, 3818)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Mikrodalgali soba kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'mw_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'mw_country', 2
  UNION ALL SELECT 'mw_product_type', 3
  UNION ALL SELECT 'mw_control_type', 4
  UNION ALL SELECT 'mw_color', 5
  UNION ALL SELECT 'mw_weight', 6
  UNION ALL SELECT 'mw_dimensions', 7
  UNION ALL SELECT 'mw_warranty', 8
  UNION ALL SELECT 'mw_included', 9
  UNION ALL SELECT 'mw_inner_coating', 10
  UNION ALL SELECT 'mw_volume_l', 11
  UNION ALL SELECT 'mw_program_count', 12
  UNION ALL SELECT 'mw_door_opening', 13
  UNION ALL SELECT 'mw_light', 14
  UNION ALL SELECT 'mw_plate_diameter_mm', 15
  UNION ALL SELECT 'mw_auto_cook', 16
  UNION ALL SELECT 'mw_microwave_power', 17
  UNION ALL SELECT 'mw_programmable_cooking', 18
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-mikro')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Mikrodalgali soba mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-mikro');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Mikrodalğalı soba', 3
  UNION ALL SELECT 'İdarəetmə növü', 'Elektron', 4
  UNION ALL SELECT 'Rəng', 'Qara', 5
  UNION ALL SELECT 'Çəki', '9.5 kq', 6
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '476 × 272 × 368 mm', 7
  UNION ALL SELECT 'Zəmanət', '1 il', 8
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Şüşə qab', 9
  UNION ALL SELECT 'Kameranın daxili örtüyü', 'Emal', 10
  UNION ALL SELECT 'Daxili həcm, l', '25 lt', 11
  UNION ALL SELECT 'Proqramların sayı', '8', 12
  UNION ALL SELECT 'Qapının açılması', 'Tutacaq', 13
  UNION ALL SELECT 'İşıqlandırma', 'var', 14
  UNION ALL SELECT 'Döndərici hissənin diametri, sm', '292 mm', 15
  UNION ALL SELECT 'Avtomatik hazırlanma', 'var', 16
  UNION ALL SELECT 'Mikrodalğa gücü, Vt', '1000 Vt', 17
  UNION ALL SELECT 'Hazırlama prosesin proqramlaşdırılması', 'var', 18
) x
WHERE c.slug IN ('bk-mikro')
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
      AND c.slug IN ('bk-mikro')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Almaniya'
  WHEN 'samsung' THEN 'Malayziya'
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'midea' THEN 'Çin'
  WHEN 'beko' THEN 'Türkiyə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-mikro')
  );
