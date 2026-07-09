USE maxhome_db;

-- Audio ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('au2_brand', 'Brend', 'text', NULL, NULL, 1, 3001),
('au2_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3002),
('au2_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3003),
('au2_wifi', 'Wi-Fi', 'text', NULL, NULL, 1, 3004),
('au2_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 3005),
('au2_freq', 'Tezlik diapazonu, Hz', 'text', NULL, NULL, 1, 3006),
('au2_power', 'Səs gücü', 'text', NULL, NULL, 1, 3007),
('au2_jack', '3,5 mm audio çıxış', 'text', NULL, NULL, 1, 3008),
('au2_color', 'Rəng', 'text', NULL, NULL, 1, 3009),
('au2_weight', 'Çəki', 'text', NULL, NULL, 1, 3010),
('au2_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3011),
('au2_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3012),
('au2_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3013)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Audio kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'au2_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'au2_country', 2
  UNION ALL SELECT 'au2_product_type', 3
  UNION ALL SELECT 'au2_wifi', 4
  UNION ALL SELECT 'au2_bluetooth', 5
  UNION ALL SELECT 'au2_freq', 6
  UNION ALL SELECT 'au2_power', 7
  UNION ALL SELECT 'au2_jack', 8
  UNION ALL SELECT 'au2_color', 9
  UNION ALL SELECT 'au2_weight', 10
  UNION ALL SELECT 'au2_dimensions', 11
  UNION ALL SELECT 'au2_warranty', 12
  UNION ALL SELECT 'au2_included', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('tv-audio-video-b-02-audio', 'au-saundbar', 'au-kinoteatr', 'au-kalonka')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Audio mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tv-audio-video-b-02-audio', 'au-saundbar', 'au-kinoteatr', 'au-kalonka');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Portativ Ağıllı Akustika', 3
  UNION ALL SELECT 'Wi-Fi', 'var', 4
  UNION ALL SELECT 'Bluetooth', 'Var', 5
  UNION ALL SELECT 'Tezlik diapazonu, Hz', '2.4 GHz / 5 GHz', 6
  UNION ALL SELECT 'Səs gücü', '50 W', 7
  UNION ALL SELECT '3,5 mm audio çıxış', 'var', 8
  UNION ALL SELECT 'Rəng', 'Bənövşəyi', 9
  UNION ALL SELECT 'Çəki', '1.76 kq', 10
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '20.1 x 13.2 x 13.2 sm', 11
  UNION ALL SELECT 'Zəmanət', '1 il', 12
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Əsas hissə, təlimat kitabçası.', 13
) x
WHERE c.slug IN ('tv-audio-video-b-02-audio', 'au-saundbar', 'au-kinoteatr', 'au-kalonka')
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
      AND c.slug IN ('tv-audio-video-b-02-audio', 'au-saundbar', 'au-kinoteatr', 'au-kalonka')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'yandex' THEN 'Çin'
  WHEN 'sony' THEN 'Malayziya'
  WHEN 'samsung' THEN 'Vyetnam'
  WHEN 'jbl' THEN 'Çin'
  WHEN 'lg' THEN 'İndoneziya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('tv-audio-video-b-02-audio', 'au-saundbar', 'au-kinoteatr', 'au-kalonka')
  );
