USE maxhome_db;

-- Trimmer ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('tm_brand', 'Brend', 'text', NULL, NULL, 1, 5801),
('tm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5802),
('tm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5803),
('tm_usage', 'Təyinat', 'text', NULL, NULL, 1, 5804),
('tm_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5805),
('tm_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 5806),
('tm_max_cut_length', 'Kəsimin maksimal uzunluğu, mm', 'text', NULL, NULL, 1, 5807),
('tm_head_count', 'Başlıqlar sayı', 'text', NULL, NULL, 1, 5808),
('tm_battery_life', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 5809),
('tm_color', 'Rəng', 'text', NULL, NULL, 1, 5810),
('tm_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5811),
('tm_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5812),
('tm_min_cut_length', 'Kəsimin minimal uzunluğu, mm', 'text', NULL, NULL, 1, 5813)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Trimmer kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
  OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Təyinat',
  'Zəmanət',
  'Tam dolma müddəti, saat',
  'Kəsimin maksimal uzunluğu, mm',
  'Başlıqlar sayı',
  'Avtonom iş müddəti',
  'Rəng',
  'Qablaşdırmaya daxildir',
  'Enerji təchizatının tipi',
  'Kəsimin minimal uzunluğu, mm'
)
AND s.spec_key NOT LIKE 'tm\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'tm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'tm_country', 2
  UNION ALL SELECT 'tm_product_type', 3
  UNION ALL SELECT 'tm_usage', 4
  UNION ALL SELECT 'tm_warranty', 5
  UNION ALL SELECT 'tm_charge_time', 6
  UNION ALL SELECT 'tm_max_cut_length', 7
  UNION ALL SELECT 'tm_head_count', 8
  UNION ALL SELECT 'tm_battery_life', 9
  UNION ALL SELECT 'tm_color', 10
  UNION ALL SELECT 'tm_included', 11
  UNION ALL SELECT 'tm_power_type', 12
  UNION ALL SELECT 'tm_min_cut_length', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
  OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Trimmer mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
   OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Trimmer', 3
  UNION ALL SELECT 'Təyinat', 'Saqqal və bığ üçün', 4
  UNION ALL SELECT 'Zəmanət', '2 il', 5
  UNION ALL SELECT 'Tam dolma müddəti, saat', '1 saat', 6
  UNION ALL SELECT 'Kəsimin maksimal uzunluğu, mm', '16 mm', 7
  UNION ALL SELECT 'Başlıqlar sayı', '8', 8
  UNION ALL SELECT 'Avtonom iş müddəti', '70 dəqiqə', 9
  UNION ALL SELECT 'Rəng', 'Qara', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Trimmer, 6 ədəd ölçü başlığı, adapter', 11
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Ni-MH', 12
  UNION ALL SELECT 'Kəsimin minimal uzunluğu, mm', '0.5 mm', 13
) x
WHERE c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
   OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər')
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
        c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
        OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'İndoneziya'
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'braun' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-kisi-trimmer', 'km-trimmer', 'gz-sac-masin', 'gz-trimmer')
        OR c.name IN ('Trimmer', 'Trimmer və maşınlar', 'Maşınlar və trimmerlər')
      )
  );
