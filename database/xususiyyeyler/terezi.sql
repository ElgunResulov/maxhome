USE maxhome_db;

-- Terezi ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('sc2_brand', 'Brend', 'text', NULL, NULL, 1, 4301),
('sc2_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4302),
('sc2_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4303),
('sc2_color', 'Rəng', 'text', NULL, NULL, 1, 4304),
('sc2_weight', 'Çəki', 'text', NULL, NULL, 1, 4305),
('sc2_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4306),
('sc2_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4307),
('sc2_battery_type', 'Enerji elementlərinin növü', 'text', NULL, NULL, 1, 4308),
('sc2_max_weight', 'Maksimal çəki, kq', 'text', NULL, NULL, 1, 4309),
('sc2_bowl_material', 'Qabın materialı', 'text', NULL, NULL, 1, 4310),
('sc2_units', 'Ölçü vahidləri', 'text', NULL, NULL, 1, 4311),
('sc2_bowl_present', 'Qabın mövcudluğu', 'text', NULL, NULL, 1, 4312)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Terezi kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'sc2_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'sc2_country', 2
  UNION ALL SELECT 'sc2_product_type', 3
  UNION ALL SELECT 'sc2_color', 4
  UNION ALL SELECT 'sc2_weight', 5
  UNION ALL SELECT 'sc2_warranty', 6
  UNION ALL SELECT 'sc2_included', 7
  UNION ALL SELECT 'sc2_battery_type', 8
  UNION ALL SELECT 'sc2_max_weight', 9
  UNION ALL SELECT 'sc2_bowl_material', 10
  UNION ALL SELECT 'sc2_units', 11
  UNION ALL SELECT 'sc2_bowl_present', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-terezi')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Terezi mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-terezi');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Mətbəx Tərəzisi', 3
  UNION ALL SELECT 'Rəng', 'Qara', 4
  UNION ALL SELECT 'Çəki', '510 qr', 5
  UNION ALL SELECT 'Zəmanət', '2 il', 6
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Tərəzi, CR2032 enerji elementi', 7
  UNION ALL SELECT 'Enerji elementlərinin növü', 'CR2032', 8
  UNION ALL SELECT 'Maksimal çəki, kq', '8 kq', 9
  UNION ALL SELECT 'Qabın materialı', 'yox', 10
  UNION ALL SELECT 'Ölçü vahidləri', 'Kiloqram, qram', 11
  UNION ALL SELECT 'Qabın mövcudluğu', 'yox', 12
) x
WHERE c.slug IN ('km-terezi')
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
      AND c.slug IN ('km-terezi')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'kenwood' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'bosch' THEN 'Almaniya'
  WHEN 'beurer' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-terezi')
  );
