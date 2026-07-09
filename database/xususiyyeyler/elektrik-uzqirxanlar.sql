USE maxhome_db;

-- Elektrik uzqirxanlar ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('es_brand', 'Brend', 'text', NULL, NULL, 1, 5601),
('es_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5602),
('es_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5603),
('es_color', 'Rəng', 'text', NULL, NULL, 1, 5604),
('es_weight', 'Çəki', 'text', NULL, NULL, 1, 5605),
('es_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5606),
('es_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5607),
('es_head_count', 'Ülgüc başlıqlarının sayı', 'text', NULL, NULL, 1, 5608),
('es_shaving_type', 'Qırxma növü', 'text', NULL, NULL, 1, 5609),
('es_shaving_method', 'Qırxma üsulu', 'text', NULL, NULL, 1, 5610),
('es_full_charge_time', 'Tam dolma müddəti, saat', 'text', NULL, NULL, 1, 5611),
('es_one_session_charge_time', 'Bir seans üçün dolma müddəti, saat', 'text', NULL, NULL, 1, 5612),
('es_battery_life', 'Avtonom iş müddəti', 'text', NULL, NULL, 1, 5613),
('es_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 5614)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Elektrik uzqirxan kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
  OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Rəng',
  'Çəki',
  'Zəmanət',
  'Qablaşdırmaya daxildir',
  'Ülgüc başlıqlarının sayı',
  'Qırxma növü',
  'Qırxma üsulu',
  'Tam dolma müddəti, saat',
  'Bir seans üçün dolma müddəti, saat',
  'Avtonom iş müddəti',
  'Enerji təchizatının tipi'
)
AND s.spec_key NOT LIKE 'es\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'es_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'es_country', 2
  UNION ALL SELECT 'es_product_type', 3
  UNION ALL SELECT 'es_color', 4
  UNION ALL SELECT 'es_weight', 5
  UNION ALL SELECT 'es_warranty', 6
  UNION ALL SELECT 'es_included', 7
  UNION ALL SELECT 'es_head_count', 8
  UNION ALL SELECT 'es_shaving_type', 9
  UNION ALL SELECT 'es_shaving_method', 10
  UNION ALL SELECT 'es_full_charge_time', 11
  UNION ALL SELECT 'es_one_session_charge_time', 12
  UNION ALL SELECT 'es_battery_life', 13
  UNION ALL SELECT 'es_power_type', 14
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
  OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Elektrik uzqirxan mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
   OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Elektrik üzqırxan', 3
  UNION ALL SELECT 'Rəng', 'Qara', 4
  UNION ALL SELECT 'Çəki', '220 qr', 5
  UNION ALL SELECT 'Zəmanət', '2 il', 6
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Üz qırxan cihazı, başlıq və adapter', 7
  UNION ALL SELECT 'Ülgüc başlıqlarının sayı', '3', 8
  UNION ALL SELECT 'Qırxma növü', 'Quru və yaş', 9
  UNION ALL SELECT 'Qırxma üsulu', 'Torlu', 10
  UNION ALL SELECT 'Tam dolma müddəti, saat', '1 saat', 11
  UNION ALL SELECT 'Bir seans üçün dolma müddəti, saat', '5 dəq', 12
  UNION ALL SELECT 'Avtonom iş müddəti', '45 dəq', 13
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Li-Ion', 14
) x
WHERE c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
   OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar')
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
        c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
        OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'philips' THEN 'Niderland'
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
        c.slug IN ('gz-kisi-qirxan', 'gz-elektrik-uzqirxan', 'gz-uzqirxan')
        OR c.name IN ('Üzqırxan', 'Elektrik üzqırxanlar', 'Elektrik üz qırxanlar')
      )
  );
