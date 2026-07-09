USE maxhome_db;

-- Tosterler ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ts_brand', 'Brend', 'text', NULL, NULL, 1, 4001),
('ts_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4002),
('ts_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4003),
('ts_power', 'Güc', 'text', NULL, NULL, 1, 4004),
('ts_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 4005),
('ts_roast_levels', 'Qızartma dərəcələri sayı', 'text', NULL, NULL, 1, 4006),
('ts_slot_count', 'Bölmələrin sayı', 'text', NULL, NULL, 1, 4007),
('ts_auto_off', 'Avtomatik sönmə', 'text', NULL, NULL, 1, 4008),
('ts_color', 'Rəng', 'text', NULL, NULL, 1, 4009),
('ts_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4010),
('ts_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4011)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Toster kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ts_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ts_country', 2
  UNION ALL SELECT 'ts_product_type', 3
  UNION ALL SELECT 'ts_power', 4
  UNION ALL SELECT 'ts_control_type', 5
  UNION ALL SELECT 'ts_roast_levels', 6
  UNION ALL SELECT 'ts_slot_count', 7
  UNION ALL SELECT 'ts_auto_off', 8
  UNION ALL SELECT 'ts_color', 9
  UNION ALL SELECT 'ts_warranty', 10
  UNION ALL SELECT 'ts_included', 11
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-toster')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Toster mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-toster');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Toster', 3
  UNION ALL SELECT 'Güc', '650 W', 4
  UNION ALL SELECT 'İdarəetmə növü', 'Mexaniki', 5
  UNION ALL SELECT 'Qızartma dərəcələri sayı', '6', 6
  UNION ALL SELECT 'Bölmələrin sayı', '2', 7
  UNION ALL SELECT 'Avtomatik sönmə', 'var', 8
  UNION ALL SELECT 'Rəng', 'Qara', 9
  UNION ALL SELECT 'Zəmanət', '2 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Toster və təlimat', 11
) x
WHERE c.slug IN ('km-toster')
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
      AND c.slug IN ('km-toster')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'bosch' THEN 'Sloveniya'
  WHEN 'midea' THEN 'Çin'
  WHEN 'kenwood' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-toster')
  );
