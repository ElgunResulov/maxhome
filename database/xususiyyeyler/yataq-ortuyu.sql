USE maxhome_db;

-- Yataq ortuyu ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('yo_brand', 'Brend', 'text', NULL, NULL, 1, 7401),
('yo_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7402),
('yo_blanket_size', 'Battaniyə ölçü', 'text', 'sm', NULL, 1, 7403),
('yo_pillow_size', 'Yastıq üzünün ölçüsü', 'text', 'sm', NULL, 1, 7404),
('yo_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7405),
('yo_material', 'Material', 'text', NULL, NULL, 1, 7406),
('yo_pillow_count', 'Yastıq üzü', 'text', NULL, NULL, 1, 7407)
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
  SELECT 'yo_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'yo_product_type', 2
  UNION ALL SELECT 'yo_blanket_size', 3
  UNION ALL SELECT 'yo_pillow_size', 4
  UNION ALL SELECT 'yo_country', 5
  UNION ALL SELECT 'yo_material', 6
  UNION ALL SELECT 'yo_pillow_count', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('tx-yataq-ortukleri')
    OR c.name IN ('Yataq örtükləri', 'Yataq ortukleri', 'Yataq örtüyü')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tx-yataq-ortukleri')
   OR c.name IN ('Yataq örtükləri', 'Yataq ortukleri', 'Yataq örtüyü');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Yataq örtüsü dəsti', 2
  UNION ALL SELECT 'Battaniyə ölçü', '220*240', 3
  UNION ALL SELECT 'Yastıq üzünün ölçüsü', '50*70', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
  UNION ALL SELECT 'Material', '100 % pambıq', 6
  UNION ALL SELECT 'Yastıq üzü', '2', 7
) x
WHERE c.slug IN ('tx-yataq-ortukleri')
   OR c.name IN ('Yataq örtükləri', 'Yataq ortukleri', 'Yataq örtüyü')
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
      AND (c.slug IN ('tx-yataq-ortukleri') OR c.name IN ('Yataq örtükləri', 'Yataq ortukleri', 'Yataq örtüyü'))
  );

-- İstehsalçı ölkə dəyərini brend slug-a görə doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'madame-coco' THEN 'Türkiyə'
  WHEN 'karaca-home' THEN 'Türkiyə'
  WHEN 'english-home' THEN 'Türkiyə'
  WHEN 'iyigeceler' THEN 'Türkiyə'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('tx-yataq-ortukleri') OR c.name IN ('Yataq örtükləri', 'Yataq ortukleri', 'Yataq örtüyü'))
  );
