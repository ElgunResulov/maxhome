USE maxhome_db;

-- Dreame Multistayler ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ms_brand', 'Brend', 'text', NULL, NULL, 1, 5501),
('ms_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5502),
('ms_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5503),
('ms_power', 'İstehlak gücü, Vt', 'text', NULL, NULL, 1, 5504),
('ms_head_count', 'Başlıq sayı', 'text', NULL, NULL, 1, 5505),
('ms_cable_length', 'Kabel uzunluğu', 'text', NULL, NULL, 1, 5506),
('ms_color', 'Rəng', 'text', NULL, NULL, 1, 5507),
('ms_weight', 'Çəki', 'text', NULL, NULL, 1, 5508),
('ms_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5509),
('ms_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5510),
('ms_heat_mode_count', 'İstilik rejimin sayı', 'text', NULL, NULL, 1, 5511),
('ms_ionization', 'İonlaşdırılma', 'text', NULL, NULL, 1, 5512)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Multistayler kateqoriyalarina map et (slug + ad fallback)
DELETE csm
FROM category_spec_map csm
INNER JOIN spec_definitions s ON s.id = csm.spec_definition_id
INNER JOIN categories c ON c.id = csm.category_id
WHERE (
  c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
  OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar')
)
AND s.spec_key LIKE 'ms\_%';

INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ms_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ms_country', 2
  UNION ALL SELECT 'ms_product_type', 3
  UNION ALL SELECT 'ms_power', 4
  UNION ALL SELECT 'ms_head_count', 5
  UNION ALL SELECT 'ms_cable_length', 6
  UNION ALL SELECT 'ms_color', 7
  UNION ALL SELECT 'ms_weight', 8
  UNION ALL SELECT 'ms_warranty', 9
  UNION ALL SELECT 'ms_included', 10
  UNION ALL SELECT 'ms_heat_mode_count', 11
  UNION ALL SELECT 'ms_ionization', 12
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
  OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Multistayler mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
   OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Multistayler', 3
  UNION ALL SELECT 'İstehlak gücü, Vt', '1300 W', 4
  UNION ALL SELECT 'Başlıq sayı', '6', 5
  UNION ALL SELECT 'Kabel uzunluğu', '2.8 m', 6
  UNION ALL SELECT 'Rəng', 'Bənövşəyi', 7
  UNION ALL SELECT 'Çəki', '291 qr', 8
  UNION ALL SELECT 'Zəmanət', '1 il', 9
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Fen və məlumat kitabçası', 10
  UNION ALL SELECT 'İstilik rejimin sayı', '3', 11
  UNION ALL SELECT 'İonlaşdırılma', 'yox', 12
) x
WHERE c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
   OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar')
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
        c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
        OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar')
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'dreame' THEN 'Çin'
  WHEN 'dyson' THEN 'Malayziya'
  WHEN 'philips' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('gz-multistayler', 'gz-multistayler-ve-masalar', 'gz-multistayler-masalar')
        OR c.name IN ('Multistayler', 'Multistaylerlər və maşalar', 'Multistayler və maşalar')
      )
  );
