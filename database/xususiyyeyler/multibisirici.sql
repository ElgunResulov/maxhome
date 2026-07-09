USE maxhome_db;

-- Multibisirici ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('mc_brand', 'Brend', 'text', NULL, NULL, 1, 4601),
('mc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4602),
('mc_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4603),
('mc_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 4604),
('mc_power', 'Güc', 'text', NULL, NULL, 1, 4605),
('mc_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 4606),
('mc_color', 'Rəng', 'text', NULL, NULL, 1, 4607),
('mc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4608),
('mc_timer', 'Taymer', 'text', NULL, NULL, 1, 4609),
('mc_bowl_coating', 'Kasa örtüyü', 'text', NULL, NULL, 1, 4610)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Multibisirici kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'mc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'mc_country', 2
  UNION ALL SELECT 'mc_product_type', 3
  UNION ALL SELECT 'mc_program_count', 4
  UNION ALL SELECT 'mc_power', 5
  UNION ALL SELECT 'mc_control_type', 6
  UNION ALL SELECT 'mc_color', 7
  UNION ALL SELECT 'mc_warranty', 8
  UNION ALL SELECT 'mc_timer', 9
  UNION ALL SELECT 'mc_bowl_coating', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-multibisirici')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Multibisirici mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-multibisirici');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Multi Bişirici', 3
  UNION ALL SELECT 'Proqramların sayı', '10', 4
  UNION ALL SELECT 'Güc', '1760 Wt', 5
  UNION ALL SELECT 'İdarəetmə növü', 'Elektron', 6
  UNION ALL SELECT 'Rəng', 'Qara', 7
  UNION ALL SELECT 'Zəmanət', '1 il', 8
  UNION ALL SELECT 'Taymer', 'var', 9
  UNION ALL SELECT 'Kasa örtüyü', 'Yapışmayan örtük', 10
) x
WHERE c.slug IN ('km-multibisirici')
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
      AND c.slug IN ('km-multibisirici')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'ninja' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'philips' THEN 'Çin'
  WHEN 'moulinex' THEN 'Çin'
  WHEN 'redmond' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-multibisirici')
  );
