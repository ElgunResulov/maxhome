USE maxhome_db;

-- Uze qulluq ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('fc_brand', 'Brend', 'text', NULL, NULL, 1, 6001),
('fc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6002),
('fc_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6003),
('fc_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 6004),
('fc_weight', 'Çəki', 'text', NULL, NULL, 1, 6005),
('fc_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 6006),
('fc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 6007),
('fc_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 6008),
('fc_power_type', 'Enerji təchizatının tipi', 'text', NULL, NULL, 1, 6009),
('fc_color', 'Rəng', 'text', NULL, NULL, 1, 6010),
('fc_usage_type', 'İstifadə növü', 'text', NULL, NULL, 1, 6011)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Uze qulluq kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
  OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Sürət sayı',
  'Çəki',
  'Ölçülər: Hündürlüyü / Eni / Dərinliyi',
  'Zəmanət',
  'Qablaşdırmaya daxildir',
  'Enerji təchizatının tipi',
  'Rəng',
  'İstifadə növü'
)
AND s.spec_key NOT LIKE 'fc\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'fc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'fc_country', 2
  UNION ALL SELECT 'fc_product_type', 3
  UNION ALL SELECT 'fc_speed_count', 4
  UNION ALL SELECT 'fc_weight', 5
  UNION ALL SELECT 'fc_dimensions', 6
  UNION ALL SELECT 'fc_warranty', 7
  UNION ALL SELECT 'fc_included', 8
  UNION ALL SELECT 'fc_power_type', 9
  UNION ALL SELECT 'fc_color', 10
  UNION ALL SELECT 'fc_usage_type', 11
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
  OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Uze qulluq mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
   OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Üzə qulluq cihazı', 3
  UNION ALL SELECT 'Sürət sayı', '1', 4
  UNION ALL SELECT 'Çəki', '0.5 kq', 5
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '15.5x11x5.7 sm', 6
  UNION ALL SELECT 'Zəmanət', '2 il', 7
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Cihaz, başlıq və təlimat', 8
  UNION ALL SELECT 'Enerji təchizatının tipi', 'Batareya', 9
  UNION ALL SELECT 'Rəng', 'Ağ', 10
  UNION ALL SELECT 'İstifadə növü', 'Quru'
) x
WHERE c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
   OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu')
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
        c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
        OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'braun' THEN 'Almaniya'
  WHEN 'philips' THEN 'Çin'
  WHEN 'panasonic' THEN 'Yaponiya'
  WHEN 'beurer' THEN 'Almaniya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gozellik-baxim-b-02-uz-ve-beden', 'gz-uze-qulluq', 'gz-uz-qulluq')
        OR c.name IN ('Üz və bədən', 'Üzə qulluq', 'Üz qulluğu')
      )
  );
