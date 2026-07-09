USE maxhome_db;

-- Utuler ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ir_brand', 'Brend', 'text', NULL, NULL, 1, 4101),
('ir_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4102),
('ir_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4103),
('ir_power', 'Güc', 'text', NULL, NULL, 1, 4104),
('ir_steam_pressure', 'Buxar təkanı', 'text', NULL, NULL, 1, 4105),
('ir_heat_time', 'Qızma müddəti', 'text', NULL, NULL, 1, 4106),
('ir_color', 'Rəng', 'text', NULL, NULL, 1, 4107),
('ir_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4108),
('ir_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4109),
('ir_cable_length', 'Kabel uzunluğu', 'text', NULL, NULL, 1, 4110),
('ir_soleplate', 'Altlıq örtüyü', 'text', NULL, NULL, 1, 4111),
('ir_tank_volume', 'Su rezervuarının həcmi, ml', 'text', NULL, NULL, 1, 4112),
('ir_button_groove', 'Düymə yeri', 'text', NULL, NULL, 1, 4113),
('ir_self_clean', 'Özünü təmizləmə sistemi', 'text', NULL, NULL, 1, 4114),
('ir_vertical_steam', 'Şaquli buxar', 'text', NULL, NULL, 1, 4115),
('ir_anti_drip', 'Damcıya qarşı sistem', 'text', NULL, NULL, 1, 4116),
('ir_anti_calc', 'Ərpdən müdafiə', 'text', NULL, NULL, 1, 4117)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Utu kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ir_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ir_country', 2
  UNION ALL SELECT 'ir_product_type', 3
  UNION ALL SELECT 'ir_power', 4
  UNION ALL SELECT 'ir_steam_pressure', 5
  UNION ALL SELECT 'ir_heat_time', 6
  UNION ALL SELECT 'ir_color', 7
  UNION ALL SELECT 'ir_warranty', 8
  UNION ALL SELECT 'ir_included', 9
  UNION ALL SELECT 'ir_cable_length', 10
  UNION ALL SELECT 'ir_soleplate', 11
  UNION ALL SELECT 'ir_tank_volume', 12
  UNION ALL SELECT 'ir_button_groove', 13
  UNION ALL SELECT 'ir_self_clean', 14
  UNION ALL SELECT 'ir_vertical_steam', 15
  UNION ALL SELECT 'ir_anti_drip', 16
  UNION ALL SELECT 'ir_anti_calc', 17
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-utu')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Utu mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-utu');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Generator Ütü', 3
  UNION ALL SELECT 'Güc', '2400 W', 4
  UNION ALL SELECT 'Buxar təkanı', '8 bar', 5
  UNION ALL SELECT 'Qızma müddəti', '2 dəqiqə', 6
  UNION ALL SELECT 'Rəng', 'Qara/Qırzılı', 7
  UNION ALL SELECT 'Zəmanət', '2 il', 8
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Ütü generator və təlimat kitabı', 9
  UNION ALL SELECT 'Kabel uzunluğu', '1.6 m', 10
  UNION ALL SELECT 'Altlıq örtüyü', 'Steam Glide Elite', 11
  UNION ALL SELECT 'Su rezervuarının həcmi, ml', '1.8 lt', 12
  UNION ALL SELECT 'Düymə yeri', 'yox', 13
  UNION ALL SELECT 'Özünü təmizləmə sistemi', 'var', 14
  UNION ALL SELECT 'Şaquli buxar', 'var', 15
  UNION ALL SELECT 'Damcıya qarşı sistem', 'var', 16
  UNION ALL SELECT 'Ərpdən müdafiə', 'var', 17
) x
WHERE c.slug IN ('km-utu')
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
      AND c.slug IN ('km-utu')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'philips' THEN 'İndoneziya'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'bosch' THEN 'İspaniya'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-utu')
  );
