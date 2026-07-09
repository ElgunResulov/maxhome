USE maxhome_db;

-- Dəst TV stendlər üçün xüsusiyyətlər
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('dtv_brand', 'Brend', 'text', NULL, NULL, 1, 6601),
('dtv_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6602),
('dtv_dimensions', 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'text', 'sm', NULL, 1, 6603),
('dtv_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6604),
('dtv_material', 'Material', 'text', NULL, NULL, 1, 6605),
('dtv_color', 'Rəng', 'text', NULL, NULL, 1, 6606)
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
  SELECT 'dtv_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'dtv_product_type', 2
  UNION ALL SELECT 'dtv_dimensions', 3
  UNION ALL SELECT 'dtv_country', 4
  UNION ALL SELECT 'dtv_material', 5
  UNION ALL SELECT 'dtv_color', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-tv-dest')
    OR c.name IN ('Dəst TV stendlər', 'Dest TV stendler', 'TV stend')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Köhnə product specs məlumatlarını təmizlə
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-tv-dest')
   OR c.name IN ('Dəst TV stendlər', 'Dest TV stendler', 'TV stend');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'TV stend', 2
  UNION ALL SELECT 'Ölçülər: Eni / Hündürüyü / Dərinliyi', '191*66*46', 3
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 4
  UNION ALL SELECT 'Material', 'Türkiyə Laminatı', 5
  UNION ALL SELECT 'Rəng', 'Boz', 6
) x
WHERE c.slug IN ('mb-tv-dest')
   OR c.name IN ('Dəst TV stendlər', 'Dest TV stendler', 'TV stend')
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
      AND (c.slug IN ('mb-tv-dest') OR c.name IN ('Dəst TV stendlər', 'Dest TV stendler', 'TV stend'))
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
      AND (c.slug IN ('mb-tv-dest') OR c.name IN ('Dəst TV stendlər', 'Dest TV stendler', 'TV stend'))
  );
