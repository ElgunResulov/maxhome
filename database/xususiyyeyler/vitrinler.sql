USE maxhome_db;

-- Vitrin/Vitrinler ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('vt_brand', 'Brend', 'text', NULL, NULL, 1, 6401),
('vt_color', 'Rəng', 'text', NULL, NULL, 1, 6402),
('vt_size_short', 'Vitrin (En/Hün/Dər)', 'text', 'sm', NULL, 1, 6403),
('vt_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6404),
('vt_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6405),
('vt_size_full', 'Ölçülər: Eni / Hündürlüyü / Dərinliyi', 'text', NULL, NULL, 1, 6406)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Vitrin kateqoriyalarina map (slug + ad fallback)
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'vt_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'vt_color', 2
  UNION ALL SELECT 'vt_size_short', 3
  UNION ALL SELECT 'vt_product_type', 4
  UNION ALL SELECT 'vt_country', 5
  UNION ALL SELECT 'vt_size_full', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-qonaq-vitrin')
    OR c.name IN ('Vitrin', 'Vitrinlər', 'Vitrinler')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Vitrin mehsullari ucun kohne specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-qonaq-vitrin')
   OR c.name IN ('Vitrin', 'Vitrinlər', 'Vitrinler');

-- Default product_specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Rəng', 'Qoz Ağacı', 2
  UNION ALL SELECT 'Vitrin (En/Hün/Dər)', '100x163.4x47', 3
  UNION ALL SELECT 'Məhsul tipi', 'Vitrin', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
  UNION ALL SELECT 'Ölçülər: Eni / Hündürlüyü / Dərinliyi', 'E:1000 H:1634 D:470', 6
) x
WHERE c.slug IN ('mb-qonaq-vitrin')
   OR c.name IN ('Vitrin', 'Vitrinlər', 'Vitrinler')
ORDER BY p.id, x.sort_order;

-- Brendi brands cedvelinden doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (c.slug IN ('mb-qonaq-vitrin') OR c.name IN ('Vitrin', 'Vitrinlər', 'Vitrinler'))
  );

-- Istehsalci olkeni brend slug-na gore update et
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
      AND (c.slug IN ('mb-qonaq-vitrin') OR c.name IN ('Vitrin', 'Vitrinlər', 'Vitrinler'))
  );
