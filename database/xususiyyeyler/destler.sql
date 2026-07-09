USE maxhome_db;

-- Mebel destleri ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('st_brand', 'Brend', 'text', NULL, NULL, 1, 6101),
('st_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6102),
('st_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6103),
('st_material', 'Material', 'text', NULL, NULL, 1, 6104),
('st_color', 'Rəng', 'text', NULL, NULL, 1, 6105),
('st_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 6106),
('st_piece_count', 'Dəst sayı', 'text', NULL, NULL, 1, 6107),
('st_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 6108),
('st_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 6109)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Destler kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'st_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'st_country', 2
  UNION ALL SELECT 'st_product_type', 3
  UNION ALL SELECT 'st_material', 4
  UNION ALL SELECT 'st_color', 5
  UNION ALL SELECT 'st_dimensions', 6
  UNION ALL SELECT 'st_piece_count', 7
  UNION ALL SELECT 'st_warranty', 8
  UNION ALL SELECT 'st_included', 9
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('mb-qonaq-destler', 'mb-yataq-destler', 'mb-yumsaq-destler', 'mb-genc-destler')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Destler mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-qonaq-destler', 'mb-yataq-destler', 'mb-yumsaq-destler', 'mb-genc-destler');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Dəst', 3
  UNION ALL SELECT 'Material', 'MDF / Parça', 4
  UNION ALL SELECT 'Rəng', 'Qəhvəyi', 5
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'standart', 6
  UNION ALL SELECT 'Dəst sayı', '3', 7
  UNION ALL SELECT 'Zəmanət', '2 il', 8
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Dəst elementləri və montaj kitabçası', 9
) x
WHERE c.slug IN ('mb-qonaq-destler', 'mb-yataq-destler', 'mb-yumsaq-destler', 'mb-genc-destler')
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'Brend'
  AND EXISTS (
    SELECT 1 FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('mb-qonaq-destler', 'mb-yataq-destler', 'mb-yumsaq-destler', 'mb-genc-destler')
  );

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
    SELECT 1 FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('mb-qonaq-destler', 'mb-yataq-destler', 'mb-yumsaq-destler', 'mb-genc-destler')
  );
