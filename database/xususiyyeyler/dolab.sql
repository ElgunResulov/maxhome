USE maxhome_db;

-- Dolab/Dolablar ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('dl_brand', 'Brend', 'text', NULL, NULL, 1, 7001),
('dl_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7002),
('dl_color', 'Rəng', 'text', NULL, NULL, 1, 7003),
('dl_functional', 'Funksional özəlliyi', 'text', NULL, NULL, 1, 7004),
('dl_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7005),
('dl_material', 'Material', 'text', NULL, NULL, 1, 7006),
('dl_dimensions', 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'text', 'sm', NULL, 1, 7007),
('dl_door_count', 'Qapı növü və sayı', 'text', NULL, NULL, 1, 7008)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Kateqoriya map (slug + ad fallback)
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'dl_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'dl_product_type', 2
  UNION ALL SELECT 'dl_color', 3
  UNION ALL SELECT 'dl_functional', 4
  UNION ALL SELECT 'dl_country', 5
  UNION ALL SELECT 'dl_material', 6
  UNION ALL SELECT 'dl_dimensions', 7
  UNION ALL SELECT 'dl_door_count', 8
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-yataq-dolablar', 'mb-genc-dolab')
    OR c.name IN ('Dolablar', 'Dolab')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-yataq-dolablar', 'mb-genc-dolab')
   OR c.name IN ('Dolablar', 'Dolab');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Dolab', 2
  UNION ALL SELECT 'Rəng', 'Bej', 3
  UNION ALL SELECT 'Funksional özəlliyi', 'Modulyar', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
  UNION ALL SELECT 'Material', 'Türkiyə Laminatı', 6
  UNION ALL SELECT 'Ölçülər: Eni / Hündürüyü / Dərinliyi', '(80+180)*55*230', 7
  UNION ALL SELECT 'Qapı növü və sayı', '6 qapılı', 8
) x
WHERE c.slug IN ('mb-yataq-dolablar', 'mb-genc-dolab')
   OR c.name IN ('Dolablar', 'Dolab')
ORDER BY p.id, x.sort_order;

-- Brend dəyərini brands cədvəlindən doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('mb-yataq-dolablar', 'mb-genc-dolab') OR c.name IN ('Dolablar', 'Dolab'))
  );

-- İstehsalçı ölkə dəyərini brend slug-a görə doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bellona' THEN 'Türkiyə'
  WHEN 'istikbal' THEN 'Türkiyə'
  WHEN 'weltew' THEN 'Türkiyə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('mb-yataq-dolablar', 'mb-genc-dolab') OR c.name IN ('Dolablar', 'Dolab'))
  );
