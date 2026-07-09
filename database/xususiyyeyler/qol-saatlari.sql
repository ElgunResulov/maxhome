USE maxhome_db;

-- Qol saatları üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('qw_brand', 'Brend', 'text', NULL, NULL, 1, 2501),
('qw_type', 'Növü', 'text', NULL, NULL, 1, 2502),
('qw_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2503),
('qw_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2504),
('qw_gender', 'Cins', 'text', NULL, NULL, 1, 2505),
('qw_shape', 'Forma', 'text', NULL, NULL, 1, 2506),
('qw_size_mm', 'Ölçü', 'text', 'mm', NULL, 1, 2507),
('qw_color', 'Rəng', 'text', NULL, NULL, 1, 2508),
('qw_case_color', 'Korpusun rəngi', 'text', NULL, NULL, 1, 2509),
('qw_strap_material', 'Bilərzik materialı', 'text', NULL, NULL, 1, 2510),
('qw_water_resistance', 'Su keçirməyən korpus', 'text', NULL, NULL, 1, 2511),
('qw_movement', 'Mexanizm', 'text', NULL, NULL, 1, 2512),
('qw_feature', 'Xüsusiyyət', 'text', NULL, NULL, 1, 2513),
('qw_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2514)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Qol saatları kateqoriyalarına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'qw_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'qw_type', 2
  UNION ALL SELECT 'qw_product_type', 3
  UNION ALL SELECT 'qw_country', 4
  UNION ALL SELECT 'qw_gender', 5
  UNION ALL SELECT 'qw_shape', 6
  UNION ALL SELECT 'qw_size_mm', 7
  UNION ALL SELECT 'qw_color', 8
  UNION ALL SELECT 'qw_case_color', 9
  UNION ALL SELECT 'qw_strap_material', 10
  UNION ALL SELECT 'qw_water_resistance', 11
  UNION ALL SELECT 'qw_movement', 12
  UNION ALL SELECT 'qw_feature', 13
  UNION ALL SELECT 'qw_warranty', 14
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'qs-qol-saatlari',
  'qs-kisi',
  'qs-qadin',
  'qs-usaq',
  'qs-butun'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Qol saatları məhsulları üçün default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug LIKE 'qs-%';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'qw_brand' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'qw_type', 'Mineral', 2
  UNION ALL SELECT 'qw_product_type', 'Qol saatı', 3
  UNION ALL SELECT 'qw_country', 'Azərbaycan', 4
  UNION ALL SELECT 'qw_gender', '{GENDER}', 5
  UNION ALL SELECT 'qw_shape', 'dairəvi', 6
  UNION ALL SELECT 'qw_size_mm', '47.5', 7
  UNION ALL SELECT 'qw_color', 'Gümüşü', 8
  UNION ALL SELECT 'qw_case_color', 'Gümüşü', 9
  UNION ALL SELECT 'qw_strap_material', 'Polad', 10
  UNION ALL SELECT 'qw_water_resistance', '5 ATM', 11
  UNION ALL SELECT 'qw_movement', 'Kvars', 12
  UNION ALL SELECT 'qw_feature', 'Sport', 13
  UNION ALL SELECT 'qw_warranty', '1 il', 14
) x
WHERE c.slug LIKE 'qs-%'
ORDER BY p.id, x.sort_order;

-- Brendi products.brand_id-dan doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
INNER JOIN categories c ON c.id = p.category_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'qw_brand'
  AND c.slug LIKE 'qs-%';

-- Cinsi kateqoriya slug-una görə doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
SET ps.spec_value = CASE
  WHEN c.slug = 'qs-kisi' THEN 'Kişi'
  WHEN c.slug = 'qs-qadin' THEN 'Qadın'
  WHEN c.slug = 'qs-usaq' THEN 'Uşaq'
  ELSE 'Ümumi'
END
WHERE ps.spec_key = 'qw_gender'
  AND c.slug LIKE 'qs-%';

