USE maxhome_db;

-- Periferiya (sican ve klaviatura) ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('pf_brand', 'Brend', 'text', NULL, NULL, 1, 2701),
('pf_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2702),
('pf_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2703),
('pf_color', 'Rəng', 'text', NULL, NULL, 1, 2704),
('pf_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2705),
('pf_connection', 'Qoşulma', 'text', NULL, NULL, 1, 2706),
('pf_extra', 'Əlavə özəllik', 'text', NULL, NULL, 1, 2707),
('pf_type', 'Növü', 'text', NULL, NULL, 1, 2708),
('pf_compatibility', 'Uyğunluq', 'text', NULL, NULL, 1, 2709),
('pf_cable_length', 'Kabel uzunluğu', 'text', 'm', NULL, 1, 2710),
('pf_lighting', 'İşıqlandırma', 'text', NULL, NULL, 1, 2711),
('pf_size', 'Ölçü', 'text', 'mm', NULL, 1, 2712)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Kateqoriyalara map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'pf_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'pf_country', 2
  UNION ALL SELECT 'pf_product_type', 3
  UNION ALL SELECT 'pf_color', 4
  UNION ALL SELECT 'pf_warranty', 5
  UNION ALL SELECT 'pf_connection', 6
  UNION ALL SELECT 'pf_extra', 7
  UNION ALL SELECT 'pf_type', 8
  UNION ALL SELECT 'pf_compatibility', 9
  UNION ALL SELECT 'pf_cable_length', 10
  UNION ALL SELECT 'pf_lighting', 11
  UNION ALL SELECT 'pf_size', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('noutbuklar-ve-komp-b-06-periferiya', 'ak-sican-klaviatura')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('noutbuklar-ve-komp-b-06-periferiya', 'ak-sican-klaviatura');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'pf_brand' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'pf_country', '{COUNTRY}', 2
  UNION ALL SELECT 'pf_product_type', 'Klaviatura və mouse dəsti', 3
  UNION ALL SELECT 'pf_color', 'Qara', 4
  UNION ALL SELECT 'pf_warranty', '1 il', 5
  UNION ALL SELECT 'pf_connection', 'Simsiz', 6
  UNION ALL SELECT 'pf_extra', '800/1200/1600 DPI', 7
  UNION ALL SELECT 'pf_type', 'Mexaniki', 8
  UNION ALL SELECT 'pf_compatibility', 'Windows, PC', 9
  UNION ALL SELECT 'pf_cable_length', '1.8', 10
  UNION ALL SELECT 'pf_lighting', 'RGB', 11
  UNION ALL SELECT 'pf_size', '365 x 42 x 137', 12
) x
WHERE c.slug IN ('noutbuklar-ve-komp-b-06-periferiya', 'ak-sican-klaviatura')
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key = 'pf_brand'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('noutbuklar-ve-komp-b-06-periferiya', 'ak-sican-klaviatura')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'asus' THEN 'Çin'
  WHEN 'logitech' THEN 'İsveçrə'
  WHEN 'a4tech' THEN 'Tayvan'
  WHEN 'hp' THEN 'ABŞ'
  WHEN 'lenovo' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'pf_country'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('noutbuklar-ve-komp-b-06-periferiya', 'ak-sican-klaviatura')
  );
