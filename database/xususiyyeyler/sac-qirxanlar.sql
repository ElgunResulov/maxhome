USE maxhome_db;

-- Sac qirxanlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hc_brand', 'Brend', 'text', NULL, NULL, 1, 5701),
('hc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5702),
('hc_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5703),
('hc_color', 'Rəng', 'text', NULL, NULL, 1, 5704),
('hc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5705),
('hc_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5706),
('hc_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 5707),
('hc_battery_life', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 5708),
('hc_max_cut_length', 'Kəsimin maksimal uzunluğu, mm', 'text', NULL, NULL, 1, 5709),
('hc_min_cut_length', 'Kəsimin minimal uzunluğu, mm', 'text', NULL, NULL, 1, 5710),
('hc_head_count', 'Başlıqlar sayı', 'text', NULL, NULL, 1, 5711),
('hc_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5712)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Sac qirxan kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
  OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Rəng',
  'Zəmanət',
  'Qablaşdırmaya daxildir',
  'Tam dolma müddəti, saat',
  'Avtonom iş müddəti',
  'Kəsimin maksimal uzunluğu, mm',
  'Kəsimin minimal uzunluğu, mm',
  'Başlıqlar sayı',
  'Enerji təchizatının tipi'
)
AND s.spec_key NOT LIKE 'hc\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hc_country', 2
  UNION ALL SELECT 'hc_product_type', 3
  UNION ALL SELECT 'hc_color', 4
  UNION ALL SELECT 'hc_warranty', 5
  UNION ALL SELECT 'hc_included', 6
  UNION ALL SELECT 'hc_charge_time', 7
  UNION ALL SELECT 'hc_battery_life', 8
  UNION ALL SELECT 'hc_max_cut_length', 9
  UNION ALL SELECT 'hc_min_cut_length', 10
  UNION ALL SELECT 'hc_head_count', 11
  UNION ALL SELECT 'hc_power_type', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
  OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Sac qirxan mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
   OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Saç qırxan', 3
  UNION ALL SELECT 'Rəng', 'Qara', 4
  UNION ALL SELECT 'Zəmanət', '2 il', 5
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Saç qırxan cihazı, başlıq və adapter', 6
  UNION ALL SELECT 'Tam dolma müddəti, saat', '8 saat', 7
  UNION ALL SELECT 'Avtonom iş müddəti', '50 dəqiqə', 8
  UNION ALL SELECT 'Kəsimin maksimal uzunluğu, mm', '24 mm', 9
  UNION ALL SELECT 'Kəsimin minimal uzunluğu, mm', '0.5 mm', 10
  UNION ALL SELECT 'Başlıqlar sayı', '1', 11
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Batareya'
) x
WHERE c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
   OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar')
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
        c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
        OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'braun' THEN 'Almaniya'
  WHEN 'philips' THEN 'Niderland'
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'remington' THEN 'ABŞ'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-sac-masin', 'km-trimmer', 'gz-sac-qirxan', 'gz-trimmer')
        OR c.name IN ('Maşınlar və trimmerlər', 'Trimmer və maşınlar', 'Saç qırxan', 'Saç qırxanlar')
      )
  );
