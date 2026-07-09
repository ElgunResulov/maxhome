USE maxhome_db;

-- Irriqatorlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('or_brand', 'Brend', 'text', NULL, NULL, 1, 5701),
('or_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5702),
('or_capacity', 'Tutum', 'text', NULL, NULL, 1, 5703),
('or_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5704),
('or_full_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 5705),
('or_runtime', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 5706),
('or_color', 'Rəng', 'text', NULL, NULL, 1, 5707)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Irriqator kateqoriyalarina map et (slug + ad fallback)
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'or_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'or_country', 2
  UNION ALL SELECT 'or_capacity', 3
  UNION ALL SELECT 'or_warranty', 4
  UNION ALL SELECT 'or_full_charge_time', 5
  UNION ALL SELECT 'or_runtime', 6
  UNION ALL SELECT 'or_color', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
  OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'İrriqatorlar')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Irriqator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
   OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'İrriqatorlar');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Tutum', '250 ml', 3
  UNION ALL SELECT 'Zəmanət', '2 il', 4
  UNION ALL SELECT 'Tam dolma müddəti, saat', '1 saat', 5
  UNION ALL SELECT 'Avtonom iş müddəti', '60 dəqiqə', 6
  UNION ALL SELECT 'Rəng', 'Ağ', 7
) x
WHERE c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
   OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'İrriqatorlar')
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
        c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
        OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'İrriqatorlar')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'oral-b' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'waterpik' THEN 'ABŞ'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
        OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'İrriqatorlar')
      )
  );
