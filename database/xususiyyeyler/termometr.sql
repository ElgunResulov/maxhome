USE maxhome_db;

-- Termometr ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('th_brand', 'Brend', 'text', NULL, NULL, 1, 5901),
('th_manufacturer', 'Marka', 'text', NULL, NULL, 1, 5902),
('th_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5903),
('th_size', 'Ölçü', 'text', NULL, NULL, 1, 5904),
('th_weight', 'Çəki', 'text', NULL, NULL, 1, 5905),
('th_temperature_range', 'Temperatur', 'text', NULL, NULL, 1, 5906),
('th_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5907)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Termometr kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'th_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'th_manufacturer', 2
  UNION ALL SELECT 'th_product_type', 3
  UNION ALL SELECT 'th_size', 4
  UNION ALL SELECT 'th_weight', 5
  UNION ALL SELECT 'th_temperature_range', 6
  UNION ALL SELECT 'th_warranty', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('tb-termometr')
  OR c.name IN ('Termometrlər', 'Termometr')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Termometr mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tb-termometr')
   OR c.name IN ('Termometrlər', 'Termometr');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Marka', '{BRAND}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Termometr', 3
  UNION ALL SELECT 'Ölçü', '156.7/43/47', 4
  UNION ALL SELECT 'Çəki', '91.5g', 5
  UNION ALL SELECT 'Temperatur', '35.0 - 42.0°C', 6
  UNION ALL SELECT 'Zəmanət', '1 il', 7
) x
WHERE c.slug IN ('tb-termometr')
   OR c.name IN ('Termometrlər', 'Termometr')
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
        c.slug IN ('tb-termometr')
        OR c.name IN ('Termometrlər', 'Termometr')
      )
  );
USE maxhome_db;

-- Termometr ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tm_brand', 'Brend', 'text', NULL, NULL, 1, 5901),
('tm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5902),
('tm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5903),
('tm_measurement_type', 'Ölçmə növü', 'text', NULL, NULL, 1, 5904),
('tm_range', 'Ölçmə aralığı', 'text', NULL, NULL, 1, 5905),
('tm_accuracy', 'Dəqiqlik', 'text', NULL, NULL, 1, 5906),
('tm_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5907),
('tm_display', 'Display', 'text', NULL, NULL, 1, 5908),
('tm_memory', 'Yaddaş funksiyası', 'text', NULL, NULL, 1, 5909),
('tm_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5910)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Termometr kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tm_country', 2
  UNION ALL SELECT 'tm_product_type', 3
  UNION ALL SELECT 'tm_measurement_type', 4
  UNION ALL SELECT 'tm_range', 5
  UNION ALL SELECT 'tm_accuracy', 6
  UNION ALL SELECT 'tm_power_type', 7
  UNION ALL SELECT 'tm_display', 8
  UNION ALL SELECT 'tm_memory', 9
  UNION ALL SELECT 'tm_warranty', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('tb-termometr')
  OR c.name IN ('Termometrlər', 'Termometr')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Termometr mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tb-termometr')
   OR c.name IN ('Termometrlər', 'Termometr');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Termometr', 3
  UNION ALL SELECT 'Ölçmə növü', 'İnfraqırmızı', 4
  UNION ALL SELECT 'Ölçmə aralığı', '32.0°C - 42.9°C', 5
  UNION ALL SELECT 'Dəqiqlik', '±0.2°C', 6
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Batareya', 7
  UNION ALL SELECT 'Display', 'var', 8
  UNION ALL SELECT 'Yaddaş funksiyası', 'var', 9
  UNION ALL SELECT 'Zəmanət', '1 il', 10
) x
WHERE c.slug IN ('tb-termometr')
   OR c.name IN ('Termometrlər', 'Termometr')
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
      AND (
        c.slug IN ('tb-termometr')
        OR c.name IN ('Termometrlər', 'Termometr')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'microlife' THEN 'İsveçrə'
  WHEN 'beurer' THEN 'Almaniya'
  WHEN 'omron' THEN 'Yaponiya'
  WHEN 'b-well' THEN 'İsveçrə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('tb-termometr')
        OR c.name IN ('Termometrlər', 'Termometr')
      )
  );
