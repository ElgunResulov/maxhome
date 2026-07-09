USE maxhome_db;

-- Daraqli fen ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('bh_brand', 'Brend', 'text', NULL, NULL, 1, 5301),
('bh_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5302),
('bh_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5303),
('bh_power', 'Güc', 'text', NULL, NULL, 1, 5304),
('bh_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 5305),
('bh_head_count', 'Başlıq sayı', 'text', NULL, NULL, 1, 5306),
('bh_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 5307),
('bh_coating_material', 'Örtüyün materialı', 'text', NULL, NULL, 1, 5308),
('bh_color', 'Rəng', 'text', NULL, NULL, 1, 5309),
('bh_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5310),
('bh_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5311),
('bh_cable_length', 'Kabel uzunluğu', 'text', NULL, NULL, 1, 5312),
('bh_overheat_protection', 'Qızmadan müdafiə', 'text', NULL, NULL, 1, 5313),
('bh_ionization', 'İonlaşdırılma', 'text', NULL, NULL, 1, 5314),
('bh_cool_shot', 'Soyuq havanın verilməsi', 'text', NULL, NULL, 1, 5315)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Daraqli fen kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('km-fen', 'gz-sac-fen')
  OR c.name IN ('Fenlər', 'Fen və ütülər')
)
AND s.spec_key LIKE 'bh\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'bh_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'bh_country', 2
  UNION ALL SELECT 'bh_product_type', 3
  UNION ALL SELECT 'bh_power', 4
  UNION ALL SELECT 'bh_speed_count', 5
  UNION ALL SELECT 'bh_head_count', 6
  UNION ALL SELECT 'bh_body_material', 7
  UNION ALL SELECT 'bh_coating_material', 8
  UNION ALL SELECT 'bh_color', 9
  UNION ALL SELECT 'bh_warranty', 10
  UNION ALL SELECT 'bh_included', 11
  UNION ALL SELECT 'bh_cable_length', 12
  UNION ALL SELECT 'bh_overheat_protection', 13
  UNION ALL SELECT 'bh_ionization', 14
  UNION ALL SELECT 'bh_cool_shot', 15
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-daraqli-fen')
  OR c.name IN ('Daraqlı fen')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Daraqli fen mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-daraqli-fen')
   OR c.name IN ('Daraqlı fen');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Daraqlı fen', 3
  UNION ALL SELECT 'Güc', '1000 W', 4
  UNION ALL SELECT 'Sürət sayı', '2', 5
  UNION ALL SELECT 'Başlıq sayı', '3', 6
  UNION ALL SELECT 'Korpus Materialı', 'Plastik', 7
  UNION ALL SELECT 'Örtüyün materialı', 'Keramika', 8
  UNION ALL SELECT 'Rəng', 'Çəhrayı', 9
  UNION ALL SELECT 'Zəmanət', '2 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Fen və məlumat kitabçası', 11
  UNION ALL SELECT 'Kabel uzunluğu', '1.8 m', 12
  UNION ALL SELECT 'Qızmadan müdafiə', 'var', 13
  UNION ALL SELECT 'İonlaşdırılma', 'var', 14
  UNION ALL SELECT 'Soyuq havanın verilməsi', 'var', 15
) x
WHERE c.slug IN ('gz-daraqli-fen')
   OR c.name IN ('Daraqlı fen')
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
        c.slug IN ('gz-daraqli-fen')
        OR c.name IN ('Daraqlı fen')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'Çin'
  WHEN 'dreame' THEN 'Çin'
  WHEN 'fakir' THEN 'Türkiyə'
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
        c.slug IN ('gz-daraqli-fen')
        OR c.name IN ('Daraqlı fen')
      )
  );
