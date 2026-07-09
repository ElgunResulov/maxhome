USE maxhome_db;

-- Yataqlar ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('yt_brand', 'Brend', 'text', NULL, NULL, 1, 7101),
('yt_construction', 'Konstruksiya', 'text', NULL, NULL, 1, 7102),
('yt_base_size', 'Baza ölçü', 'text', 'sm', NULL, 1, 7103),
('yt_leg_height', 'Ayaq hündürlüyü', 'text', 'sm', NULL, 1, 7104),
('yt_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7105),
('yt_main_size', 'Ölçülər', 'text', 'sm', NULL, 1, 7106),
('yt_body_material', 'Korpus materialı', 'text', NULL, NULL, 1, 7107),
('yt_leg_material', 'Ayaq materialı', 'text', NULL, NULL, 1, 7108),
('yt_base_type', 'Baza tipi', 'text', NULL, NULL, 1, 7109),
('yt_headboard_size', 'Başlıq ölçü', 'text', 'sm', NULL, 1, 7110),
('yt_no_leg_height', 'Ayaq xaric hündürlüyü', 'text', 'sm', NULL, 1, 7111),
('yt_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7112),
('yt_color', 'Rəng', 'text', NULL, NULL, 1, 7113),
('yt_has_base', 'Baza', 'text', NULL, NULL, 1, 7114),
('yt_fabric_material', 'Parça materialı', 'text', NULL, NULL, 1, 7115),
('yt_leg_color', 'Ayaq rəngi', 'text', NULL, NULL, 1, 7116)
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
  SELECT 'yt_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'yt_construction', 2
  UNION ALL SELECT 'yt_base_size', 3
  UNION ALL SELECT 'yt_leg_height', 4
  UNION ALL SELECT 'yt_product_type', 5
  UNION ALL SELECT 'yt_main_size', 6
  UNION ALL SELECT 'yt_body_material', 7
  UNION ALL SELECT 'yt_leg_material', 8
  UNION ALL SELECT 'yt_base_type', 9
  UNION ALL SELECT 'yt_headboard_size', 10
  UNION ALL SELECT 'yt_no_leg_height', 11
  UNION ALL SELECT 'yt_country', 12
  UNION ALL SELECT 'yt_color', 13
  UNION ALL SELECT 'yt_has_base', 14
  UNION ALL SELECT 'yt_fabric_material', 15
  UNION ALL SELECT 'yt_leg_color', 16
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-yataq-yataqlar', 'mb-genc-yataq')
    OR c.name IN ('Yataqlar', 'Yataq')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-yataq-yataqlar', 'mb-genc-yataq')
   OR c.name IN ('Yataqlar', 'Yataq');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Konstruksiya', 'Dayanıqlı polad karkas', 2
  UNION ALL SELECT 'Baza ölçü', '121x201', 3
  UNION ALL SELECT 'Ayaq hündürlüyü', '7.5', 4
  UNION ALL SELECT 'Məhsul tipi', 'Baza - başlıq set', 5
  UNION ALL SELECT 'Ölçülər', '120x200', 6
  UNION ALL SELECT 'Korpus materialı', 'Metal karkas + MDF', 7
  UNION ALL SELECT 'Ayaq materialı', 'Plastik', 8
  UNION ALL SELECT 'Baza tipi', 'Hovuzlu', 9
  UNION ALL SELECT 'Başlıq ölçü', '131 cm x 116 cm x 9 cm', 10
  UNION ALL SELECT 'Ayaq xaric hündürlüyü', '27', 11
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 12
  UNION ALL SELECT 'Rəng', 'Boz', 13
  UNION ALL SELECT 'Baza', 'Var', 14
  UNION ALL SELECT 'Parça materialı', 'Kətan', 15
  UNION ALL SELECT 'Ayaq rəngi', 'Qara', 16
) x
WHERE c.slug IN ('mb-yataq-yataqlar', 'mb-genc-yataq')
   OR c.name IN ('Yataqlar', 'Yataq')
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
      AND (c.slug IN ('mb-yataq-yataqlar', 'mb-genc-yataq') OR c.name IN ('Yataqlar', 'Yataq'))
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
      AND (c.slug IN ('mb-yataq-yataqlar', 'mb-genc-yataq') OR c.name IN ('Yataqlar', 'Yataq'))
  );
