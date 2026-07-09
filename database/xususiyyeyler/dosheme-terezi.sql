USE maxhome_db;

-- Doseme terezi ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('fs_brand', 'Brend', 'text', NULL, NULL, 1, 6101),
('fs_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6102),
('fs_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6103),
('fs_color', 'Rəng', 'text', NULL, NULL, 1, 6104),
('fs_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 6105),
('fs_max_weight', 'Maksimal çəki, kq', 'text', NULL, NULL, 1, 6106)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Doseme terezi kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
  OR c.slug LIKE '%terezi%'
  OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
  OR (c.name LIKE '%tərəzi%' AND EXISTS (
    SELECT 1
    FROM categories pc
    WHERE pc.id = c.parent_id
      AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
  ))
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'Rəng',
  'Zəmanət',
  'Maksimal çəki, kq'
)
AND s.spec_key NOT LIKE 'fs\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'fs_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'fs_country', 2
  UNION ALL SELECT 'fs_product_type', 3
  UNION ALL SELECT 'fs_color', 4
  UNION ALL SELECT 'fs_warranty', 5
  UNION ALL SELECT 'fs_max_weight', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
  OR c.slug LIKE '%terezi%'
  OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
  OR (c.name LIKE '%tərəzi%' AND EXISTS (
    SELECT 1
    FROM categories pc
    WHERE pc.id = c.parent_id
      AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
  ))
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Doseme terezi mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
   OR c.slug LIKE '%terezi%'
   OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
   OR (c.name LIKE '%tərəzi%' AND EXISTS (
     SELECT 1
     FROM categories pc
     WHERE pc.id = c.parent_id
       AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
   ));

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Döşəmə tərəzisi', 3
  UNION ALL SELECT 'Rəng', 'Bənövşəyi', 4
  UNION ALL SELECT 'Zəmanət', '2 il', 5
  UNION ALL SELECT 'Maksimal çəki, kq', '180 kq', 6
) x
WHERE c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
   OR c.slug LIKE '%terezi%'
   OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
   OR (c.name LIKE '%tərəzi%' AND EXISTS (
     SELECT 1
     FROM categories pc
     WHERE pc.id = c.parent_id
       AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
   ))
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
        c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
        OR c.slug LIKE '%terezi%'
        OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
        OR (c.name LIKE '%tərəzi%' AND EXISTS (
          SELECT 1
          FROM categories pc
          WHERE pc.id = c.parent_id
            AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
        ))
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'tefal' THEN 'Çin'
  WHEN 'beurer' THEN 'Almaniya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'soehnle' THEN 'Almaniya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('km-terezi', 'km-doseme-terezi', 'gz-doseme-terezi', 'gz-doseme-terezileri', 'gz-tereziler')
        OR c.slug LIKE '%terezi%'
        OR c.name IN ('Tərəzi', 'Döşəmə tərəzisi', 'Döşəmə tərəzi', 'Döşəmə tərəziləri', 'Dösəmə tərəziləri')
        OR (c.name LIKE '%tərəzi%' AND EXISTS (
          SELECT 1
          FROM categories pc
          WHERE pc.id = c.parent_id
            AND pc.name IN ('Baxım üçün cihazlar', 'Baxim üçün cihazlar')
        ))
      )
  );
