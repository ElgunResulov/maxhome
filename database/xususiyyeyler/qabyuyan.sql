USE maxhome_db;

-- Qabyuyan ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('dw_brand', 'Brend', 'text', NULL, NULL, 1, 3301),
('dw_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3302),
('dw_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3303),
('dw_motor', 'Mühərrik tipi', 'text', NULL, NULL, 1, 3304),
('dw_noise', 'Səs səviyyəsi', 'text', NULL, NULL, 1, 3305),
('dw_control', 'İdarəetmə növü', 'text', NULL, NULL, 1, 3306),
('dw_water', 'Su sərfiyyatı', 'text', NULL, NULL, 1, 3307),
('dw_weight', 'Çəki', 'text', NULL, NULL, 1, 3308),
('dw_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3309),
('dw_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3310),
('dw_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3311),
('dw_display', 'Display', 'text', NULL, NULL, 1, 3312),
('dw_program_count', 'Proqram sayı', 'text', NULL, NULL, 1, 3313),
('dw_capacity', 'Tutum (komplekt)', 'text', NULL, NULL, 1, 3314),
('dw_leak_protection', 'Sızmadan müdafiə', 'text', NULL, NULL, 1, 3315),
('dw_cutlery_basket', 'Çəngəl bıçaq üçün səbət', 'text', NULL, NULL, 1, 3316)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Qabyuyan kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'dw_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'dw_country', 2
  UNION ALL SELECT 'dw_product_type', 3
  UNION ALL SELECT 'dw_motor', 4
  UNION ALL SELECT 'dw_noise', 5
  UNION ALL SELECT 'dw_control', 6
  UNION ALL SELECT 'dw_water', 7
  UNION ALL SELECT 'dw_weight', 8
  UNION ALL SELECT 'dw_dimensions', 9
  UNION ALL SELECT 'dw_warranty', 10
  UNION ALL SELECT 'dw_included', 11
  UNION ALL SELECT 'dw_display', 12
  UNION ALL SELECT 'dw_program_count', 13
  UNION ALL SELECT 'dw_capacity', 14
  UNION ALL SELECT 'dw_leak_protection', 15
  UNION ALL SELECT 'dw_cutlery_basket', 16
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-qabyuyan')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Qabyuyan mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-qabyuyan');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Qabyuyan', 3
  UNION ALL SELECT 'Mühərrik tipi', 'INVERTER DIRECT DRIVE', 4
  UNION ALL SELECT 'Səs səviyyəsi', '43 db', 5
  UNION ALL SELECT 'İdarəetmə növü', 'Elektron', 6
  UNION ALL SELECT 'Su sərfiyyatı', '9.5 lt', 7
  UNION ALL SELECT 'Çəki', '51 kq', 8
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '85x60x60 sm', 9
  UNION ALL SELECT 'Zəmanət', '1 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Şlanqlar', 11
  UNION ALL SELECT 'Display', 'var', 12
  UNION ALL SELECT 'Proqram sayı', '10', 13
  UNION ALL SELECT 'Tutum (komplekt)', '14 nəfərlik', 14
  UNION ALL SELECT 'Sızmadan müdafiə', 'var', 15
  UNION ALL SELECT 'Çəngəl bıçaq üçün səbət', 'var', 16
) x
WHERE c.slug IN ('bk-qabyuyan')
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
      AND c.slug IN ('bk-qabyuyan')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'bosch' THEN 'Almaniya'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'samsung' THEN 'Cənubi Koreya'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-qabyuyan')
  );
