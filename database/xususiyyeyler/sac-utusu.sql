USE maxhome_db;

-- Sac utusu ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hs_brand', 'Brend', 'text', NULL, NULL, 1, 5401),
('hs_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5402),
('hs_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5403),
('hs_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 5404),
('hs_heat_time', 'Qızma müddəti, s', 'text', NULL, NULL, 1, 5405),
('hs_cable_length', 'Kabel uzunluğu', 'text', NULL, NULL, 1, 5406),
('hs_plate_material', 'Örtüyün materialı', 'text', NULL, NULL, 1, 5407),
('hs_color', 'Rəng', 'text', NULL, NULL, 1, 5408),
('hs_weight', 'Çəki', 'text', NULL, NULL, 1, 5409),
('hs_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5410),
('hs_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5411),
('hs_overheat_protection', 'Qızmadan müdafiə', 'text', NULL, NULL, 1, 5412),
('hs_heat_mode_count', 'İstilik rejimin sayı', 'text', NULL, NULL, 1, 5413)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Sac utusu kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
  OR c.name IN ('Saç ütüsü', 'Saç ütüləri')
)
AND s.label IN (
  'Brend',
  'İstehsalçı ölkə',
  'Məhsul tipi',
  'İdarəetmə növü',
  'Qızma müddəti, s',
  'Kabel uzunluğu',
  'Örtüyün materialı',
  'Rəng',
  'Çəki',
  'Zəmanət',
  'Qablaşdırmaya daxildir',
  'Qızmadan müdafiə',
  'İstilik rejimin sayı'
)
AND s.spec_key NOT LIKE 'hs\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hs_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hs_country', 2
  UNION ALL SELECT 'hs_product_type', 3
  UNION ALL SELECT 'hs_control_type', 4
  UNION ALL SELECT 'hs_heat_time', 5
  UNION ALL SELECT 'hs_cable_length', 6
  UNION ALL SELECT 'hs_plate_material', 7
  UNION ALL SELECT 'hs_color', 8
  UNION ALL SELECT 'hs_weight', 9
  UNION ALL SELECT 'hs_warranty', 10
  UNION ALL SELECT 'hs_included', 11
  UNION ALL SELECT 'hs_overheat_protection', 12
  UNION ALL SELECT 'hs_heat_mode_count', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
  OR c.name IN ('Saç ütüsü', 'Saç ütüləri')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Sac utusu mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
   OR c.name IN ('Saç ütüsü', 'Saç ütüləri');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Ütü Feni', 3
  UNION ALL SELECT 'İdarəetmə növü', 'Mexaniki', 4
  UNION ALL SELECT 'Qızma müddəti, s', '15 saniyə', 5
  UNION ALL SELECT 'Kabel uzunluğu', '2 m', 6
  UNION ALL SELECT 'Örtüyün materialı', 'Keramik', 7
  UNION ALL SELECT 'Rəng', 'Narıncı', 8
  UNION ALL SELECT 'Çəki', '0.9 kq', 9
  UNION ALL SELECT 'Zəmanət', '2 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Fen və təlimat kitabçası', 11
  UNION ALL SELECT 'Qızmadan müdafiə', 'var', 12
  UNION ALL SELECT 'İstilik rejimin sayı', '2', 13
) x
WHERE c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
   OR c.name IN ('Saç ütüsü', 'Saç ütüləri')
ORDER BY p.id, x.sort_order;

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
        c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
        OR c.name IN ('Saç ütüsü', 'Saç ütüləri')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'dyson' THEN 'Malayziya'
  WHEN 'philips' THEN 'Çin'
  WHEN 'babyliss' THEN 'Çin'
  WHEN 'rowenta' THEN 'Almaniya'
  WHEN 'panasonic' THEN 'Tayland'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-sac-fen', 'gz-sac-utusu', 'gz-sac-utuleri')
        OR c.name IN ('Saç ütüsü', 'Saç ütüləri')
      )
  );
