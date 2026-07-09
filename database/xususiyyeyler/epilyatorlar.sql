USE maxhome_db;

-- Epilyatorlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ep_brand', 'Brend', 'text', NULL, NULL, 1, 5901),
('ep_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5902),
('ep_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5903),
('ep_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 5904),
('ep_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5905),
('ep_battery_life', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 5906),
('ep_color', 'Rəng', 'text', NULL, NULL, 1, 5907),
('ep_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5908),
('ep_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 5909),
('ep_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5910),
('ep_epilation_type', 'Epilyasiya növü', 'text', NULL, NULL, 1, 5911),
('ep_tweezer_count', 'Pinsetlərin sayı', 'text', NULL, NULL, 1, 5912)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Epilyator kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
  OR c.name IN ('Epilyatorlar', 'Epilyator')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Sürət sayı',
  'Zəmanət',
  'Avtonom iş müddəti',
  'Rəng',
  'Qablaşdırmaya daxildir',
  'Tam dolma müddəti, saat',
  'Enerji təchizatının tipi',
  'Epilyasiya növü',
  'Pinsetlərin sayı'
)
AND s.spec_key NOT LIKE 'ep\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ep_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ep_country', 2
  UNION ALL SELECT 'ep_product_type', 3
  UNION ALL SELECT 'ep_speed_count', 4
  UNION ALL SELECT 'ep_warranty', 5
  UNION ALL SELECT 'ep_battery_life', 6
  UNION ALL SELECT 'ep_color', 7
  UNION ALL SELECT 'ep_included', 8
  UNION ALL SELECT 'ep_charge_time', 9
  UNION ALL SELECT 'ep_power_type', 10
  UNION ALL SELECT 'ep_epilation_type', 11
  UNION ALL SELECT 'ep_tweezer_count', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
  OR c.name IN ('Epilyatorlar', 'Epilyator')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Epilyator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
   OR c.name IN ('Epilyatorlar', 'Epilyator');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Epilyator', 3
  UNION ALL SELECT 'Sürət sayı', '2', 4
  UNION ALL SELECT 'Zəmanət', '2 il', 5
  UNION ALL SELECT 'Avtonom iş müddəti', '40 dəqiqə', 6
  UNION ALL SELECT 'Rəng', 'Ağ', 7
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Epilyator, başlıq və adapter', 8
  UNION ALL SELECT 'Tam dolma müddəti, saat', '2 saat', 9
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Li-Ion', 10
  UNION ALL SELECT 'Epilyasiya növü', 'Nəm və quru', 11
  UNION ALL SELECT 'Pinsetlərin sayı', '32', 12
) x
WHERE c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
   OR c.name IN ('Epilyatorlar', 'Epilyator')
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
        c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
        OR c.name IN ('Epilyatorlar', 'Epilyator')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'braun' THEN 'Almaniya'
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'rowenta' THEN 'Fransa'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-epilyator', 'gz-epilyatorlar')
        OR c.name IN ('Epilyatorlar', 'Epilyator')
      )
  );
