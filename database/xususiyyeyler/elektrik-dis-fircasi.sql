USE maxhome_db;

-- Elektrik dis fircasi ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tb2_brand', 'Brend', 'text', NULL, NULL, 1, 5601),
('tb2_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5602),
('tb2_usage_type', 'İstifadə növü', 'text', NULL, NULL, 1, 5603),
('tb2_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5604)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Elektrik dis fircasi kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
  OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası')
)
AND s.label IN ('Brend', 'İstehsalçı ölkə', 'İstifadə növü', 'Zəmanət')
AND s.spec_key NOT LIKE 'tb2\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tb2_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tb2_country', 2
  UNION ALL SELECT 'tb2_usage_type', 3
  UNION ALL SELECT 'tb2_warranty', 4
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
  OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Elektrik dis fircasi mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
   OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'İstifadə növü', 'Böyüklər üçün', 3
  UNION ALL SELECT 'Zəmanət', '2 il', 4
) x
WHERE c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
   OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası')
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
        OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'oral-b' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'panasonic' THEN 'Yaponiya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-uz-qulluq', 'gz-uze-qulluq')
        OR c.name IN ('Üzə qulluq', 'Üz qulluğu', 'Ağız qulluğu', 'Elektrik diş fırçası')
      )
  );
USE maxhome_db;

-- Elektrik dis fircasi ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tb_brand', 'Brend', 'text', NULL, NULL, 1, 6201),
('tb_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6202),
('tb_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6203),
('tb_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 6204),
('tb_usage_type', 'İstifadə növü', 'text', NULL, NULL, 1, 6205),
('tb_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 6206),
('tb_head_count', 'Başlıq sayı', 'text', NULL, NULL, 1, 6207),
('tb_battery_life', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 6208),
('tb_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 6209),
('tb_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 6210),
('tb_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 6211),
('tb_color', 'Rəng', 'text', NULL, NULL, 1, 6212)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Elektrik dis fircasi kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
  OR c.slug LIKE '%dis-firca%'
  OR c.slug LIKE '%fircasi%'
  OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Zəmanət',
  'İstifadə növü',
  'Sürət sayı',
  'Başlıq sayı',
  'Avtonom iş müddəti',
  'Tam dolma müddəti, saat',
  'Enerji təchizatının tipi',
  'Qablaşdırmaya daxildir',
  'Rəng'
)
AND s.spec_key NOT LIKE 'tb\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tb_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tb_country', 2
  UNION ALL SELECT 'tb_product_type', 3
  UNION ALL SELECT 'tb_warranty', 4
  UNION ALL SELECT 'tb_usage_type', 5
  UNION ALL SELECT 'tb_speed_count', 6
  UNION ALL SELECT 'tb_head_count', 7
  UNION ALL SELECT 'tb_battery_life', 8
  UNION ALL SELECT 'tb_charge_time', 9
  UNION ALL SELECT 'tb_power_type', 10
  UNION ALL SELECT 'tb_included', 11
  UNION ALL SELECT 'tb_color', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
  OR c.slug LIKE '%dis-firca%'
  OR c.slug LIKE '%fircasi%'
  OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Elektrik dis fircasi mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
   OR c.slug LIKE '%dis-firca%'
   OR c.slug LIKE '%fircasi%'
   OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Elektrik diş fırçası', 3
  UNION ALL SELECT 'Zəmanət', '2 il', 4
  UNION ALL SELECT 'İstifadə növü', 'Böyüklər üçün', 5
  UNION ALL SELECT 'Sürət sayı', '1', 6
  UNION ALL SELECT 'Başlıq sayı', '2', 7
  UNION ALL SELECT 'Avtonom iş müddəti', '30 gün', 8
  UNION ALL SELECT 'Tam dolma müddəti, saat', '12 saat', 9
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Batareya', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Diş fırçası gövdəsi, 2 başlıq, şarj cihazı', 11
  UNION ALL SELECT 'Rəng', 'Ağ', 12
) x
WHERE c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
   OR c.slug LIKE '%dis-firca%'
   OR c.slug LIKE '%fircasi%'
   OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları')
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
        c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
        OR c.slug LIKE '%dis-firca%'
        OR c.slug LIKE '%fircasi%'
        OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'oral-b' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'panasonic' THEN 'Yaponiya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-elektrik-dis-fircasi', 'gz-dis-fircasi', 'km-elektrik-dis-fircasi')
        OR c.slug LIKE '%dis-firca%'
        OR c.slug LIKE '%fircasi%'
        OR c.name IN ('Elektrik diş fırçası', 'Elektrik diş fırçaları', 'Diş fırçası', 'Diş fırçaları')
      )
  );
