USE maxhome_db;

-- Buxarli bisirici ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('st_brand', 'Brend', 'text', NULL, NULL, 1, 4801),
('st_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4802),
('st_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4803),
('st_power', 'Güc', 'text', NULL, NULL, 1, 4804),
('st_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 4805),
('st_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 4806),
('st_color', 'Rəng', 'text', NULL, NULL, 1, 4807),
('st_weight', 'Çəki', 'text', NULL, NULL, 1, 4808),
('st_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 4809),
('st_bowl_material', 'Kasa materialı', 'text', NULL, NULL, 1, 4810),
('st_bowl_volume_l', 'Kasa həcmi, L', 'text', NULL, NULL, 1, 4811),
('st_tray_count', 'Bölmələrin sayı', 'text', NULL, NULL, 1, 4812),
('st_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4813),
('st_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4814),
('st_rice_bowl', 'Düyü üçün kasa', 'text', NULL, NULL, 1, 4815),
('st_timer', 'Taymer', 'text', NULL, NULL, 1, 4816),
('st_auto_off_no_water', 'Su olmadıqda sönmə', 'text', NULL, NULL, 1, 4817),
('st_temp_keep', 'Temperaturun saxlanması', 'text', NULL, NULL, 1, 4818),
('st_sterilization', 'Sterilizasiya', 'text', NULL, NULL, 1, 4819)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Buxarli bisirici kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'st_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'st_country', 2
  UNION ALL SELECT 'st_product_type', 3
  UNION ALL SELECT 'st_power', 4
  UNION ALL SELECT 'st_control_type', 5
  UNION ALL SELECT 'st_body_material', 6
  UNION ALL SELECT 'st_color', 7
  UNION ALL SELECT 'st_weight', 8
  UNION ALL SELECT 'st_dimensions', 9
  UNION ALL SELECT 'st_bowl_material', 10
  UNION ALL SELECT 'st_bowl_volume_l', 11
  UNION ALL SELECT 'st_tray_count', 12
  UNION ALL SELECT 'st_warranty', 13
  UNION ALL SELECT 'st_included', 14
  UNION ALL SELECT 'st_rice_bowl', 15
  UNION ALL SELECT 'st_timer', 16
  UNION ALL SELECT 'st_auto_off_no_water', 17
  UNION ALL SELECT 'st_temp_keep', 18
  UNION ALL SELECT 'st_sterilization', 19
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-buxarli-bisirici')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Buxarli bisirici mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-buxarli-bisirici');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Buxarlı bişirici', 3
  UNION ALL SELECT 'Güc', '850 W', 4
  UNION ALL SELECT 'İdarəetmə növü', 'Mexaniki', 5
  UNION ALL SELECT 'Korpus Materialı', 'Plastik', 6
  UNION ALL SELECT 'Rəng', 'Qara', 7
  UNION ALL SELECT 'Çəki', '3 kq', 8
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '27 x 37 x 26 sm', 9
  UNION ALL SELECT 'Kasa materialı', 'Plastik', 10
  UNION ALL SELECT 'Kasa həcmi, L', '2 lt', 11
  UNION ALL SELECT 'Bölmələrin sayı', '2', 12
  UNION ALL SELECT 'Zəmanət', '2 il', 13
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Düyü qabı, Yumurta qabı, təlimat', 14
  UNION ALL SELECT 'Düyü üçün kasa', 'var', 15
  UNION ALL SELECT 'Taymer', 'var', 16
  UNION ALL SELECT 'Su olmadıqda sönmə', 'var', 17
  UNION ALL SELECT 'Temperaturun saxlanması', 'var', 18
  UNION ALL SELECT 'Sterilizasiya', 'yox', 19
) x
WHERE c.slug IN ('km-buxarli-bisirici')
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
      AND c.slug IN ('km-buxarli-bisirici')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'braun' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'philips' THEN 'Çin'
  WHEN 'midea' THEN 'Çin'
  WHEN 'ardesto' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-buxarli-bisirici')
  );
