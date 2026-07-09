USE maxhome_db;

-- Desmal ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('dm_brand', 'Brend', 'text', NULL, NULL, 1, 7601),
('dm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7602),
('dm_material', 'Material', 'text', NULL, NULL, 1, 7603),
('dm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7604),
('dm_size', 'Ölçü', 'text', 'sm', NULL, 1, 7605),
('dm_piece_count', 'Parça sayı', 'text', NULL, NULL, 1, 7606)
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
  SELECT 'dm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'dm_product_type', 2
  UNION ALL SELECT 'dm_material', 3
  UNION ALL SELECT 'dm_country', 4
  UNION ALL SELECT 'dm_size', 5
  UNION ALL SELECT 'dm_piece_count', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('tx-hamam')
    OR c.name IN ('Hamam üçün tekstil', 'Dəsmallar', 'Desmallar', 'Dəsmal')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tx-hamam')
   OR c.name IN ('Hamam üçün tekstil', 'Dəsmallar', 'Desmallar', 'Dəsmal');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Dəsmal dəsti', 2
  UNION ALL SELECT 'Material', '100 % pambıq', 3
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 4
  UNION ALL SELECT 'Ölçü', '50*90/90*150', 5
  UNION ALL SELECT 'Parça sayı', '2', 6
) x
WHERE c.slug IN ('tx-hamam')
   OR c.name IN ('Hamam üçün tekstil', 'Dəsmallar', 'Desmallar', 'Dəsmal')
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
      AND (c.slug IN ('tx-hamam') OR c.name IN ('Hamam üçün tekstil', 'Dəsmallar', 'Desmallar', 'Dəsmal'))
  );

-- İstehsalçı ölkə dəyərini brend slug-a görə doldur
UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'iyigeceler' THEN 'Türkiyə'
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
      AND (c.slug IN ('tx-hamam') OR c.name IN ('Hamam üçün tekstil', 'Dəsmallar', 'Desmallar', 'Dəsmal'))
  );
