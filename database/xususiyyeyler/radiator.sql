USE maxhome_db;

-- Radiator ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('rd_brand', 'Brend', 'text', NULL, NULL, 1, 5001),
('rd_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5002),
('rd_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5003),
('rd_color', 'Rəng', 'text', NULL, NULL, 1, 5004),
('rd_weight', 'Çəki', 'text', NULL, NULL, 1, 5005),
('rd_max_temp', 'Maksimal temperatur, °C', 'text', NULL, NULL, 1, 5006)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Radiator kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'rd_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'rd_country', 2
  UNION ALL SELECT 'rd_product_type', 3
  UNION ALL SELECT 'rd_color', 4
  UNION ALL SELECT 'rd_weight', 5
  UNION ALL SELECT 'rd_max_temp', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('iq-isitme', 'iq-qizdirici')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Radiator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('iq-isitme', 'iq-qizdirici');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Kombi radiatoru', 3
  UNION ALL SELECT 'Rəng', 'Ağ', 4
  UNION ALL SELECT 'Çəki', '10 kq', 5
  UNION ALL SELECT 'Maksimal temperatur, °C', '100 °C', 6
) x
WHERE c.slug IN ('iq-isitme', 'iq-qizdirici')
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
      AND c.slug IN ('iq-isitme', 'iq-qizdirici')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'oasis' THEN 'Rusiya'
  WHEN 'midea' THEN 'Çin'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'bosch' THEN 'Almaniya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('iq-isitme', 'iq-qizdirici')
  );
