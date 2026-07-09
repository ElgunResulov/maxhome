USE maxhome_db;

-- Tonometr ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('bp_brand', 'Brend', 'text', NULL, NULL, 1, 5801),
('bp_manufacturer', 'Marka', 'text', NULL, NULL, 1, 5802),
('bp_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5803),
('bp_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5804),
('bp_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 5805),
('bp_measurement_range', 'Təzyiq ölçülməsi', 'text', NULL, NULL, 1, 5806),
('bp_size', 'Ölçü', 'text', NULL, NULL, 1, 5807),
('bp_weight', 'Çəki', 'text', NULL, NULL, 1, 5808),
('bp_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5809),
('bp_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5810)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Tonometr kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'bp_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'bp_manufacturer', 2
  UNION ALL SELECT 'bp_country', 3
  UNION ALL SELECT 'bp_product_type', 4
  UNION ALL SELECT 'bp_control_type', 5
  UNION ALL SELECT 'bp_measurement_range', 6
  UNION ALL SELECT 'bp_size', 7
  UNION ALL SELECT 'bp_weight', 8
  UNION ALL SELECT 'bp_power_type', 9
  UNION ALL SELECT 'bp_warranty', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('tb-tonometr')
  OR c.name IN ('Tonometrlər', 'Tonometr')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Tonometr mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tb-tonometr')
   OR c.name IN ('Tonometrlər', 'Tonometr');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Marka', '{BRAND}', 2
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 3
  UNION ALL SELECT 'Məhsul tipi', 'Tonometr', 4
  UNION ALL SELECT 'İdarəetmə növü', 'Avtomatik', 5
  UNION ALL SELECT 'Təzyiq ölçülməsi', '30-280 mmGh', 6
  UNION ALL SELECT 'Ölçü', '200/125/90', 7
  UNION ALL SELECT 'Çəki', '1100 qr', 8
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Adapter', 9
  UNION ALL SELECT 'Zəmanət', '1 il', 10
) x
WHERE c.slug IN ('tb-tonometr')
   OR c.name IN ('Tonometrlər', 'Tonometr')
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key IN ('Brend', 'Marka')
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('tb-tonometr')
        OR c.name IN ('Tonometrlər', 'Tonometr')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'microlife' THEN 'İsveçrə'
  WHEN 'beurer' THEN 'Almaniya'
  WHEN 'omron' THEN 'Yaponiya'
  WHEN 'little-doctor' THEN 'Sinqapur'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('tb-tonometr')
        OR c.name IN ('Tonometrlər', 'Tonometr')
      )
  );
