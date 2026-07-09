USE maxhome_db;

-- Fenler ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hr_brand', 'Brend', 'text', NULL, NULL, 1, 4201),
('hr_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4202),
('hr_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4203),
('hr_power', 'Güc', 'text', NULL, NULL, 1, 4204),
('hr_speed_count', 'Sürət sayı', 'text', NULL, NULL, 1, 4205),
('hr_color', 'Rəng', 'text', NULL, NULL, 1, 4206),
('hr_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4207),
('hr_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4208),
('hr_overheat_protection', 'Qızmadan müdafiə', 'text', NULL, NULL, 1, 4209),
('hr_ionization', 'İonlaşdırılma', 'text', NULL, NULL, 1, 4210),
('hr_cool_shot', 'Soyuq havanın verilməsi', 'text', NULL, NULL, 1, 4211),
('hr_diffuser', 'Diffuzor', 'text', NULL, NULL, 1, 4212)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Fen kateqoriyasina map et
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('km-fen')
  OR c.name IN ('Fenlər')
)
AND s.spec_key REGEXP '^(bh_|ms_|hs_)';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hr_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hr_country', 2
  UNION ALL SELECT 'hr_product_type', 3
  UNION ALL SELECT 'hr_power', 4
  UNION ALL SELECT 'hr_speed_count', 5
  UNION ALL SELECT 'hr_color', 6
  UNION ALL SELECT 'hr_warranty', 7
  UNION ALL SELECT 'hr_included', 8
  UNION ALL SELECT 'hr_overheat_protection', 9
  UNION ALL SELECT 'hr_ionization', 10
  UNION ALL SELECT 'hr_cool_shot', 11
  UNION ALL SELECT 'hr_diffuser', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('km-fen')
  OR c.name IN ('Fenlər')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Fen mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-fen')
   OR c.name IN ('Fenlər');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Fen', 3
  UNION ALL SELECT 'Güc', '2000 W', 4
  UNION ALL SELECT 'Sürət sayı', '3', 5
  UNION ALL SELECT 'Rəng', 'Qara', 6
  UNION ALL SELECT 'Zəmanət', '1 il', 7
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Fen və təlimat kitabçası', 8
  UNION ALL SELECT 'Qızmadan müdafiə', 'var', 9
  UNION ALL SELECT 'İonlaşdırılma', 'var', 10
  UNION ALL SELECT 'Soyuq havanın verilməsi', 'var', 11
  UNION ALL SELECT 'Diffuzor', 'var', 12
) x
WHERE c.slug IN ('km-fen')
   OR c.name IN ('Fenlər')
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
        c.slug IN ('km-fen')
        OR c.name IN ('Fenlər')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'fakir' THEN 'Türkiyə'
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
        c.slug IN ('km-fen')
        OR c.name IN ('Fenlər')
      )
  );
