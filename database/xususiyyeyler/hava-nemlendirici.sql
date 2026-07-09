USE maxhome_db;

-- Hava nemlendirici ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('hm_brand', 'Brend', 'text', NULL, NULL, 1, 5201),
('hm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 5202),
('hm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 5203),
('hm_room_area', 'Tövsiyə edilən otaq sahəsi', 'text', NULL, NULL, 1, 5204),
('hm_noise', 'Səs səviyyəsi', 'text', NULL, NULL, 1, 5205),
('hm_color', 'Rəng', 'text', NULL, NULL, 1, 5206),
('hm_weight', 'Çəki', 'text', NULL, NULL, 1, 5207),
('hm_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 5208),
('hm_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 5209)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Hava nemlendirici kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'hm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'hm_country', 2
  UNION ALL SELECT 'hm_product_type', 3
  UNION ALL SELECT 'hm_room_area', 4
  UNION ALL SELECT 'hm_noise', 5
  UNION ALL SELECT 'hm_color', 6
  UNION ALL SELECT 'hm_weight', 7
  UNION ALL SELECT 'hm_warranty', 8
  UNION ALL SELECT 'hm_included', 9
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('iq-nemlendirici')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Hava nemlendirici mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('iq-nemlendirici');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Hava nəmləndiricisi', 3
  UNION ALL SELECT 'Tövsiyə edilən otaq sahəsi', '81 kv.m', 4
  UNION ALL SELECT 'Səs səviyyəsi', '62.4 db', 5
  UNION ALL SELECT 'Rəng', 'Ağ / qırzılı', 6
  UNION ALL SELECT 'Çəki', '8.21 kq', 7
  UNION ALL SELECT 'Zəmanət', '2 il', 8
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Hava nəmləndirici və təlimat kitabçası', 9
) x
WHERE c.slug IN ('iq-nemlendirici')
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
      AND c.slug IN ('iq-nemlendirici')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'dyson' THEN 'Malayziya'
  WHEN 'xiaomi' THEN 'Çin'
  WHEN 'philips' THEN 'Çin'
  WHEN 'karcher' THEN 'Çin'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('iq-nemlendirici')
  );
