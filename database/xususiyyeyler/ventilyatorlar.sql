USE maxhome_db;

-- Ventilyatorlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('fn_brand', 'Brend', 'text', NULL, NULL, 1, 4901),
('fn_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4902),
('fn_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4903),
('fn_power', 'Güc', 'text', NULL, NULL, 1, 4904),
('fn_noise', 'Səs səviyyəsi', 'text', NULL, NULL, 1, 4905),
('fn_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 4906),
('fn_color', 'Rəng', 'text', NULL, NULL, 1, 4907),
('fn_weight', 'Çəki', 'text', NULL, NULL, 1, 4908),
('fn_blade_material', 'Qanadların materialı', 'text', NULL, NULL, 1, 4909),
('fn_blade_count', 'Qanadların sayı', 'text', NULL, NULL, 1, 4910)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Ventilyator kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'fn_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'fn_country', 2
  UNION ALL SELECT 'fn_product_type', 3
  UNION ALL SELECT 'fn_power', 4
  UNION ALL SELECT 'fn_noise', 5
  UNION ALL SELECT 'fn_speed_count', 6
  UNION ALL SELECT 'fn_color', 7
  UNION ALL SELECT 'fn_weight', 8
  UNION ALL SELECT 'fn_blade_material', 9
  UNION ALL SELECT 'fn_blade_count', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('iq-ventilyator')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Ventilyator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('iq-ventilyator');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Ventilyator', 3
  UNION ALL SELECT 'Güc', '15 W', 4
  UNION ALL SELECT 'Səs səviyyəsi', '26.6 db', 5
  UNION ALL SELECT 'Sürət sayı', '3', 6
  UNION ALL SELECT 'Rəng', 'Ağ', 7
  UNION ALL SELECT 'Çəki', '2.8 kq', 8
  UNION ALL SELECT 'Qanadların materialı', 'Plastik', 9
  UNION ALL SELECT 'Qanadların sayı', '7', 10
) x
WHERE c.slug IN ('iq-ventilyator')
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
      AND c.slug IN ('iq-ventilyator')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'midea' THEN 'Çin'
  WHEN 'samsung' THEN 'Çin'
  WHEN 'lg' THEN 'Cənubi Koreya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('iq-ventilyator')
  );
