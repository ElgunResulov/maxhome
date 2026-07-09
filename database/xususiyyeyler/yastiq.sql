USE maxhome_db;

-- Yastiq ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ys_brand', 'Brend', 'text', NULL, NULL, 1, 7501),
('ys_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7502),
('ys_material', 'Material', 'text', NULL, NULL, 1, 7503),
('ys_fill_material', 'Dolu materialı', 'text', NULL, NULL, 1, 7504),
('ys_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7505),
('ys_size', 'Ölçü', 'text', 'sm', NULL, 1, 7506),
('ys_color', 'Rəng', 'text', NULL, NULL, 1, 7507)
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
  SELECT 'ys_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ys_product_type', 2
  UNION ALL SELECT 'ys_material', 3
  UNION ALL SELECT 'ys_fill_material', 4
  UNION ALL SELECT 'ys_country', 5
  UNION ALL SELECT 'ys_size', 6
  UNION ALL SELECT 'ys_color', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('tx-yastiqlar')
    OR c.name IN ('Yastıqlar', 'Yastiqlar', 'Yastıq')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tx-yastiqlar')
   OR c.name IN ('Yastıqlar', 'Yastiqlar', 'Yastıq');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Yastıq', 2
  UNION ALL SELECT 'Material', 'Mikrofiber', 3
  UNION ALL SELECT 'Dolu materialı', 'Mikrogel', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
  UNION ALL SELECT 'Ölçü', '50*70', 6
  UNION ALL SELECT 'Rəng', 'Bəyaz', 7
) x
WHERE c.slug IN ('tx-yastiqlar')
   OR c.name IN ('Yastıqlar', 'Yastiqlar', 'Yastıq')
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
      AND (c.slug IN ('tx-yastiqlar') OR c.name IN ('Yastıqlar', 'Yastiqlar', 'Yastıq'))
  );

-- İstehsalçı ölkə dəyərini brend slug-a görə doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'mollis' THEN 'Türkiyə'
  WHEN 'madame-coco' THEN 'Türkiyə'
  WHEN 'karaca-home' THEN 'Türkiyə'
  WHEN 'english-home' THEN 'Türkiyə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('tx-yastiqlar') OR c.name IN ('Yastıqlar', 'Yastiqlar', 'Yastıq'))
  );
