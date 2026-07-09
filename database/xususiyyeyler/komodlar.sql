USE maxhome_db;

-- Komodlar ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('km_brand', 'Brend', 'text', NULL, NULL, 1, 6901),
('km_color', 'Rəng', 'text', NULL, NULL, 1, 6902),
('km_dimensions', 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'text', NULL, NULL, 1, 6903),
('km_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6904),
('km_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6905)
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
  SELECT 'km_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'km_color', 2
  UNION ALL SELECT 'km_dimensions', 3
  UNION ALL SELECT 'km_product_type', 4
  UNION ALL SELECT 'km_country', 5
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-yataq-komod-makiyaj', 'mb-genc-komod')
    OR c.name IN ('Komod və makiyaj masası', 'Komod', 'Komodlar')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-yataq-komod-makiyaj', 'mb-genc-komod')
   OR c.name IN ('Komod və makiyaj masası', 'Komod', 'Komodlar');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Rəng', 'Qoz Ağacı', 2
  UNION ALL SELECT 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'E: 562 H: 455 D: 470', 3
  UNION ALL SELECT 'Məhsul tipi', 'Yataq yanı tumba', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
) x
WHERE c.slug IN ('mb-yataq-komod-makiyaj', 'mb-genc-komod')
   OR c.name IN ('Komod və makiyaj masası', 'Komod', 'Komodlar')
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
      AND (c.slug IN ('mb-yataq-komod-makiyaj', 'mb-genc-komod') OR c.name IN ('Komod və makiyaj masası', 'Komod', 'Komodlar'))
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
      AND (c.slug IN ('mb-yataq-komod-makiyaj', 'mb-genc-komod') OR c.name IN ('Komod və makiyaj masası', 'Komod', 'Komodlar'))
  );
