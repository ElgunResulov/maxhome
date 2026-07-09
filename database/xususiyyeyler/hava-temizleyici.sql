USE maxhome_db;

-- Hava temizleyici ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ap_brand', 'Brend', 'text', NULL, NULL, 1, 5101),
('ap_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5102),
('ap_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5103),
('ap_room_area', 'Tövsiyə edilən otaq sahəsi', 'text', NULL, NULL, 1, 5104),
('ap_color', 'Rəng', 'text', NULL, NULL, 1, 5105),
('ap_weight', 'Çəki', 'text', NULL, NULL, 1, 5106),
('ap_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 5107),
('ap_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5108),
('ap_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5109),
('ap_power_levels', 'Güc səviyyəsinin sayı', 'text', NULL, NULL, 1, 5110)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Hava temizleyici kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ap_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ap_country', 2
  UNION ALL SELECT 'ap_product_type', 3
  UNION ALL SELECT 'ap_room_area', 4
  UNION ALL SELECT 'ap_color', 5
  UNION ALL SELECT 'ap_weight', 6
  UNION ALL SELECT 'ap_dimensions', 7
  UNION ALL SELECT 'ap_warranty', 8
  UNION ALL SELECT 'ap_included', 9
  UNION ALL SELECT 'ap_power_levels', 10
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('iq-temizleyici')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Hava temizleyici mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('iq-temizleyici');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Hava təmizləyici', 3
  UNION ALL SELECT 'Tövsiyə edilən otaq sahəsi', '60 m2', 4
  UNION ALL SELECT 'Rəng', 'Ağ', 5
  UNION ALL SELECT 'Çəki', '5.8 kq', 6
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '260x260x486', 7
  UNION ALL SELECT 'Zəmanət', '1 il', 8
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Əsas hissə, təlimat kitabçası.', 9
  UNION ALL SELECT 'Güc səviyyəsinin sayı', '5', 10
) x
WHERE c.slug IN ('iq-temizleyici')
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
      AND c.slug IN ('iq-temizleyici')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'karcher' THEN 'Çin'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'philips' THEN 'Çin'
  WHEN 'levoit' THEN 'Çin'
  WHEN 'samsung' THEN 'Vyetnam'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('iq-temizleyici')
  );
