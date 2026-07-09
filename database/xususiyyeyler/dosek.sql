USE maxhome_db;

-- Dosekler ucun xususiyyetler
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ds_brand', 'Brend', 'text', NULL, NULL, 1, 7201),
('ds_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7202),
('ds_size', 'Ölçülər', 'text', 'sm', NULL, 1, 7203),
('ds_feature', 'Əlavə özəllik', 'text', NULL, NULL, 1, 7204),
('ds_fabric', 'Parça materialı', 'text', NULL, NULL, 1, 7205),
('ds_weight', 'Ağırlıq', 'text', 'kq', NULL, 1, 7206),
('ds_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7207),
('ds_height', 'Hündürlüyü', 'text', 'sm', NULL, 1, 7208),
('ds_use_type', 'İstifadə növü', 'text', NULL, NULL, 1, 7209),
('ds_firmness', 'Sərtlik Dərəcəsi', 'text', NULL, NULL, 1, 7210),
('ds_internal', 'Daxili tərkib', 'text', NULL, NULL, 1, 7211)
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
  SELECT 'ds_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ds_product_type', 2
  UNION ALL SELECT 'ds_size', 3
  UNION ALL SELECT 'ds_feature', 4
  UNION ALL SELECT 'ds_fabric', 5
  UNION ALL SELECT 'ds_weight', 6
  UNION ALL SELECT 'ds_country', 7
  UNION ALL SELECT 'ds_height', 8
  UNION ALL SELECT 'ds_use_type', 9
  UNION ALL SELECT 'ds_firmness', 10
  UNION ALL SELECT 'ds_internal', 11
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c
  ON (
    c.slug IN ('mb-yataq-dosekler')
    OR c.name IN ('Döşəklər', 'Dosekler', 'Döşək')
  )
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Kohne product specs temizle
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('mb-yataq-dosekler')
   OR c.name IN ('Döşəklər', 'Dosekler', 'Döşək');

-- Default product specs doldur
INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Məhsul tipi', 'Matras', 2
  UNION ALL SELECT 'Ölçülər', '80x200', 3
  UNION ALL SELECT 'Əlavə özəllik', 'Hücrə texnologiya', 4
  UNION ALL SELECT 'Parça materialı', 'Toxunma parça', 5
  UNION ALL SELECT 'Ağırlıq', '15.917', 6
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 7
  UNION ALL SELECT 'Hündürlüyü', '21', 8
  UNION ALL SELECT 'İstifadə növü', 'İki tərəfli', 9
  UNION ALL SELECT 'Sərtlik Dərəcəsi', 'Sərt', 10
  UNION ALL SELECT 'Daxili tərkib', 'DHT Yay Sistemi', 11
) x
WHERE c.slug IN ('mb-yataq-dosekler')
   OR c.name IN ('Döşəklər', 'Dosekler', 'Döşək')
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
      AND (c.slug IN ('mb-yataq-dosekler') OR c.name IN ('Döşəklər', 'Dosekler', 'Döşək'))
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
      AND (c.slug IN ('mb-yataq-dosekler') OR c.name IN ('Döşəklər', 'Dosekler', 'Döşək'))
  );
