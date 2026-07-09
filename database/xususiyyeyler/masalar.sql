USE maxhome_db;

-- Masalar ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ms_brand', 'Brend', 'text', NULL, NULL, 1, 6201),
('ms_width', 'En', 'text', 'sm', NULL, 1, 6202),
('ms_closed_size', 'Qapalı ölçü', 'text', 'sm', NULL, 1, 6203),
('ms_open_size', 'Açıq ölçü', 'text', 'sm', NULL, 1, 6204),
('ms_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6205),
('ms_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6206),
('ms_material', 'Material', 'text', NULL, NULL, 1, 6207),
('ms_functional', 'Funksional özəlliyi', 'text', NULL, NULL, 1, 6208)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Masa kateqoriyalarina map et (slug + ad fallback)
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ms_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ms_width', 2
  UNION ALL SELECT 'ms_closed_size', 3
  UNION ALL SELECT 'ms_open_size', 4
  UNION ALL SELECT 'ms_country', 5
  UNION ALL SELECT 'ms_product_type', 6
  UNION ALL SELECT 'ms_material', 7
  UNION ALL SELECT 'ms_functional', 8
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-qonaq-masa', 'mb-jurnal-masalari', 'mb-genc-is-masasi', 'mb-yataq-komod-makiyaj')
    OR c.name IN ('Masa', 'Jurnal masaları', 'İş masası', 'Komod və makiyaj masası')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Masa mehsullari ucun evvelki specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE
  c.slug IN ('mb-qonaq-masa', 'mb-jurnal-masalari', 'mb-genc-is-masasi', 'mb-yataq-komod-makiyaj')
  OR c.name IN ('Masa', 'Jurnal masaları', 'İş masası', 'Komod və makiyaj masası');

-- Default product_specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'En', '90', 2
  UNION ALL SELECT 'Qapalı ölçü', '160', 3
  UNION ALL SELECT 'Açıq ölçü', '200', 4
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 5
  UNION ALL SELECT 'Məhsul tipi', 'Masa', 6
  UNION ALL SELECT 'Material', 'Laminat', 7
  UNION ALL SELECT 'Funksional özəlliyi', 'Açılan', 8
) x
WHERE
  c.slug IN ('mb-qonaq-masa', 'mb-jurnal-masalari', 'mb-genc-is-masasi', 'mb-yataq-komod-makiyaj')
  OR c.name IN ('Masa', 'Jurnal masaları', 'İş masası', 'Komod və makiyaj masası')
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
      AND (
        c.slug IN ('mb-qonaq-masa', 'mb-jurnal-masalari', 'mb-genc-is-masasi', 'mb-yataq-komod-makiyaj')
        OR c.name IN ('Masa', 'Jurnal masaları', 'İş masası', 'Komod və makiyaj masası')
      )
  );

-- İstehsalçı ölkəni brend slug-ına gore doldur
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
      AND (
        c.slug IN ('mb-qonaq-masa', 'mb-jurnal-masalari', 'mb-genc-is-masasi', 'mb-yataq-komod-makiyaj')
        OR c.name IN ('Masa', 'Jurnal masaları', 'İş masası', 'Komod və makiyaj masası')
      )
  );
