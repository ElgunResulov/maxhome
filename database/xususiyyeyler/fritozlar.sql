USE maxhome_db;

-- Fritozlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('fr_brand', 'Brend', 'text', NULL, NULL, 1, 4401),
('fr_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4402),
('fr_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4403),
('fr_power', 'Güc', 'text', NULL, NULL, 1, 4404),
('fr_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 4405),
('fr_capacity', 'Tutum', 'text', NULL, NULL, 1, 4406),
('fr_max_temp', 'Maksimal temperatur, °C', 'text', NULL, NULL, 1, 4407),
('fr_color', 'Rəng', 'text', NULL, NULL, 1, 4408),
('fr_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 4409),
('fr_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4410),
('fr_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4411)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Fritoz kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'fr_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'fr_country', 2
  UNION ALL SELECT 'fr_product_type', 3
  UNION ALL SELECT 'fr_power', 4
  UNION ALL SELECT 'fr_program_count', 5
  UNION ALL SELECT 'fr_capacity', 6
  UNION ALL SELECT 'fr_max_temp', 7
  UNION ALL SELECT 'fr_color', 8
  UNION ALL SELECT 'fr_dimensions', 9
  UNION ALL SELECT 'fr_warranty', 10
  UNION ALL SELECT 'fr_included', 11
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-fritoz')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Fritoz mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-fritoz');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Fritöz', 3
  UNION ALL SELECT 'Güc', '2050 W', 4
  UNION ALL SELECT 'Proqramların sayı', '9', 5
  UNION ALL SELECT 'Tutum', '6.1 lt', 6
  UNION ALL SELECT 'Maksimal temperatur, °C', '40-200°C', 7
  UNION ALL SELECT 'Rəng', 'Qara', 8
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '33.2x27.6x38.5 sm', 9
  UNION ALL SELECT 'Zəmanət', '2 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Fritöz və təlimat kitabçası', 11
) x
WHERE c.slug IN ('km-fritoz')
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
      AND c.slug IN ('km-fritoz')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Çin'
  WHEN 'philips' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'midea' THEN 'Çin'
  WHEN 'kenwood' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-fritoz')
  );
