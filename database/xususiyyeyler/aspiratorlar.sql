USE maxhome_db;

-- Aspiratorlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hp2_brand', 'Brend', 'text', NULL, NULL, 1, 3901),
('hp2_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3902),
('hp2_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3903),
('hp2_control_type', 'İdarəetmə tipi', 'text', NULL, NULL, 1, 3904),
('hp2_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 3905),
('hp2_max_airflow', 'Maksimal istehsal, kub.m/s', 'text', NULL, NULL, 1, 3906),
('hp2_max_noise_db', 'Maksimal səs səviyyəsi, dB', 'text', NULL, NULL, 1, 3907),
('hp2_color', 'Rəng', 'text', NULL, NULL, 1, 3908),
('hp2_weight', 'Çəki', 'text', NULL, NULL, 1, 3909),
('hp2_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3910),
('hp2_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3911),
('hp2_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3912)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Aspirator kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hp2_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hp2_country', 2
  UNION ALL SELECT 'hp2_product_type', 3
  UNION ALL SELECT 'hp2_control_type', 4
  UNION ALL SELECT 'hp2_speed_count', 5
  UNION ALL SELECT 'hp2_max_airflow', 6
  UNION ALL SELECT 'hp2_max_noise_db', 7
  UNION ALL SELECT 'hp2_color', 8
  UNION ALL SELECT 'hp2_weight', 9
  UNION ALL SELECT 'hp2_dimensions', 10
  UNION ALL SELECT 'hp2_warranty', 11
  UNION ALL SELECT 'hp2_included', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-aspirator')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Aspirator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-aspirator');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Aspirator', 3
  UNION ALL SELECT 'İdarəetmə tipi', 'Sensor', 4
  UNION ALL SELECT 'Sürət sayı', '3', 5
  UNION ALL SELECT 'Maksimal istehsal, kub.m/s', '700 m3/saat', 6
  UNION ALL SELECT 'Maksimal səs səviyyəsi, dB', '59 db', 7
  UNION ALL SELECT 'Rəng', 'Qara', 8
  UNION ALL SELECT 'Çəki', '12.4 kq', 9
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '39x40x59.6 sm', 10
  UNION ALL SELECT 'Zəmanət', '3 il', 11
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Aspirator və məlumat kitabçası', 12
) x
WHERE c.slug IN ('bk-aspirator')
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
      AND c.slug IN ('bk-aspirator')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Türkiyə'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'midea' THEN 'Çin'
  WHEN 'samsung' THEN 'Polşa'
  WHEN 'lg' THEN 'Cənubi Koreya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-aspirator')
  );
