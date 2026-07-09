USE maxhome_db;

-- Quruducu masinlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('dr_brand', 'Brend', 'text', NULL, NULL, 1, 3601),
('dr_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3602),
('dr_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3603),
('dr_max_load', 'Maksimal yükləmə', 'text', NULL, NULL, 1, 3604),
('dr_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 3605),
('dr_noise', 'Səs səviyyəsi', 'text', NULL, NULL, 1, 3606),
('dr_energy_class', 'Enerji sərfiyyatı sinfi', 'text', NULL, NULL, 1, 3607),
('dr_child_lock', 'Uşaq kilidi', 'text', NULL, NULL, 1, 3608),
('dr_load_type', 'Yükləmə tipi', 'text', NULL, NULL, 1, 3609),
('dr_wrinkle_guard', 'Qırışdan qorunma', 'text', NULL, NULL, 1, 3610),
('dr_color', 'Rəng', 'text', NULL, NULL, 1, 3611),
('dr_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3612),
('dr_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3613)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Quruducu kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'dr_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'dr_country', 2
  UNION ALL SELECT 'dr_product_type', 3
  UNION ALL SELECT 'dr_max_load', 4
  UNION ALL SELECT 'dr_program_count', 5
  UNION ALL SELECT 'dr_noise', 6
  UNION ALL SELECT 'dr_energy_class', 7
  UNION ALL SELECT 'dr_child_lock', 8
  UNION ALL SELECT 'dr_load_type', 9
  UNION ALL SELECT 'dr_wrinkle_guard', 10
  UNION ALL SELECT 'dr_color', 11
  UNION ALL SELECT 'dr_dimensions', 12
  UNION ALL SELECT 'dr_warranty', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-quruducu')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Quruducu mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-quruducu');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Quruducu', 3
  UNION ALL SELECT 'Maksimal yükləmə', '8 kq', 4
  UNION ALL SELECT 'Proqramların sayı', '13', 5
  UNION ALL SELECT 'Səs səviyyəsi', '64 db', 6
  UNION ALL SELECT 'Enerji sərfiyyatı sinfi', 'B', 7
  UNION ALL SELECT 'Uşaq kilidi', 'var', 8
  UNION ALL SELECT 'Yükləmə tipi', 'Öndən', 9
  UNION ALL SELECT 'Qırışdan qorunma', 'var', 10
  UNION ALL SELECT 'Rəng', 'Ağ', 11
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '84.2x59.8x61.3 sm', 12
  UNION ALL SELECT 'Zəmanət', '3 il', 13
) x
WHERE c.slug IN ('bk-quruducu')
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
      AND c.slug IN ('bk-quruducu')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Polşa'
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'samsung' THEN 'Polşa'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-quruducu')
  );
