USE maxhome_db;

-- Konsol/Konsollar ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('kn_brand', 'Brend', 'text', NULL, NULL, 1, 6301),
('kn_depth', 'Dərinlik', 'text', 'sm', NULL, 1, 6302),
('kn_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6303),
('kn_dimensions', 'Ölçülər: Eni / Hündürlüyü / Dərinliyi', 'text', 'sm', NULL, 1, 6304),
('kn_width', 'En', 'text', 'sm', NULL, 1, 6305),
('kn_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6306),
('kn_height', 'Hündürlüyü', 'text', 'sm', NULL, 1, 6307),
('kn_color', 'Rəng', 'text', NULL, NULL, 1, 6308)
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
  SELECT 'kn_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'kn_depth', 2
  UNION ALL SELECT 'kn_product_type', 3
  UNION ALL SELECT 'kn_dimensions', 4
  UNION ALL SELECT 'kn_width', 5
  UNION ALL SELECT 'kn_country', 6
  UNION ALL SELECT 'kn_height', 7
  UNION ALL SELECT 'kn_color', 8
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-qonaq-konsollar')
    OR c.name IN ('Konsollar', 'Konsol')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Evvelki specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-qonaq-konsollar')
   OR c.name IN ('Konsollar', 'Konsol');

-- Default specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Dərinlik', '46', 2
  UNION ALL SELECT 'Məhsul tipi', 'Konsol', 3
  UNION ALL SELECT 'Ölçülər: Eni / Hündürlüyü / Dərinliyi', '191*171*46', 4
  UNION ALL SELECT 'En', '191', 5
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 6
  UNION ALL SELECT 'Hündürlüyü', '80+90', 7
  UNION ALL SELECT 'Rəng', 'Boz', 8
) x
WHERE c.slug IN ('mb-qonaq-konsollar')
   OR c.name IN ('Konsollar', 'Konsol')
ORDER BY p.id, x.sort_order;

-- Brend adini brands-dan doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('mb-qonaq-konsollar') OR c.name IN ('Konsollar', 'Konsol'))
  );

-- İstehsalçı olke update
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
      AND (c.slug IN ('mb-qonaq-konsollar') OR c.name IN ('Konsollar', 'Konsol'))
  );
