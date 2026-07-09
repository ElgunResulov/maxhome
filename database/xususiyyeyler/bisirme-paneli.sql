USE maxhome_db;

-- Bisirme paneli ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hp_brand', 'Brend', 'text', NULL, NULL, 1, 3401),
('hp_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3402),
('hp_material', 'Material', 'text', NULL, NULL, 1, 3403),
('hp_color', 'Rəng', 'text', NULL, NULL, 1, 3404),
('hp_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3405),
('hp_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3406),
('hp_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3407),
('hp_electric_burner', 'Elektrik konfor', 'text', NULL, NULL, 1, 3408),
('hp_gas_burner', 'Qaz konforu', 'text', NULL, NULL, 1, 3409),
('hp_total_burner', 'Konforun ümumi sayı', 'text', NULL, NULL, 1, 3410),
('hp_gas_control', 'Konforun qaz kontrolu', 'text', NULL, NULL, 1, 3411),
('hp_electro_ignition', 'Elektroalışma', 'text', NULL, NULL, 1, 3412)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Bisirme paneli kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hp_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hp_product_type', 2
  UNION ALL SELECT 'hp_material', 3
  UNION ALL SELECT 'hp_color', 4
  UNION ALL SELECT 'hp_dimensions', 5
  UNION ALL SELECT 'hp_included', 6
  UNION ALL SELECT 'hp_warranty', 7
  UNION ALL SELECT 'hp_electric_burner', 8
  UNION ALL SELECT 'hp_gas_burner', 9
  UNION ALL SELECT 'hp_total_burner', 10
  UNION ALL SELECT 'hp_gas_control', 11
  UNION ALL SELECT 'hp_electro_ignition', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-panel')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Bisirme paneli mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-panel');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Bişirmə Paneli', 2
  UNION ALL SELECT 'Material', 'Keramika', 3
  UNION ALL SELECT 'Rəng', 'Qara', 4
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '57x306x527 sm', 5
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Bişirmə paneli və məlumat kitabı', 6
  UNION ALL SELECT 'Zəmanət', '3 il', 7
  UNION ALL SELECT 'Elektrik konfor', 'yox', 8
  UNION ALL SELECT 'Qaz konforu', '1', 9
  UNION ALL SELECT 'Konforun ümumi sayı', '1', 10
  UNION ALL SELECT 'Konforun qaz kontrolu', 'var', 11
  UNION ALL SELECT 'Elektroalışma', 'yox', 12
) x
WHERE c.slug IN ('bk-panel')
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
      AND c.slug IN ('bk-panel')
  );
