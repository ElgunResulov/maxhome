USE maxhome_db;

-- Sobalar ve pliteler ucun temiz (tekrarsiz) xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('sv_brand', 'Brend', 'text', NULL, NULL, 1, 3701),
('sv_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3702),
('sv_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3703),
('sv_stove_type', 'Soba tipi', 'text', NULL, NULL, 1, 3704),
('sv_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 3705),
('sv_color', 'Rəng', 'text', NULL, NULL, 1, 3706),
('sv_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3707),
('sv_weight', 'Çəki', 'text', NULL, NULL, 1, 3708),
('sv_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3709),
('sv_burner_total', 'Konforun ümumi sayı', 'text', NULL, NULL, 1, 3710),
('sv_gas_burner', 'Qaz konforu', 'text', NULL, NULL, 1, 3711),
('sv_electric_ignition', 'Elektroalışma', 'text', NULL, NULL, 1, 3712),
('sv_oven_volume', 'Soba həcmi, L', 'text', NULL, NULL, 1, 3713),
('sv_cover', 'Qapaq', 'text', NULL, NULL, 1, 3714),
('sv_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3715)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Yalniz bk-soba kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'sv_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'sv_country', 2
  UNION ALL SELECT 'sv_product_type', 3
  UNION ALL SELECT 'sv_stove_type', 4
  UNION ALL SELECT 'sv_control_type', 5
  UNION ALL SELECT 'sv_color', 6
  UNION ALL SELECT 'sv_dimensions', 7
  UNION ALL SELECT 'sv_weight', 8
  UNION ALL SELECT 'sv_warranty', 9
  UNION ALL SELECT 'sv_burner_total', 10
  UNION ALL SELECT 'sv_gas_burner', 11
  UNION ALL SELECT 'sv_electric_ignition', 12
  UNION ALL SELECT 'sv_oven_volume', 13
  UNION ALL SELECT 'sv_cover', 14
  UNION ALL SELECT 'sv_included', 15
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-soba')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Oxsar/tekrar xususiyyetleri tam temizle:
-- Bu hissede bk-soba ucun butun evvelki product_specs silinir,
-- sonra yalniz temiz ve unikal siyahi yeniden yazilir.
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug = 'bk-soba';

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Solo Soba', 3
  UNION ALL SELECT 'Soba tipi', 'Qaz', 4
  UNION ALL SELECT 'İdarəetmə növü', 'Mexaniki', 5
  UNION ALL SELECT 'Rəng', 'Boz', 6
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '85 x 60 x 60 sm', 7
  UNION ALL SELECT 'Çəki', '48.5 kq', 8
  UNION ALL SELECT 'Zəmanət', '2 il', 9
  UNION ALL SELECT 'Konforun ümumi sayı', '4', 10
  UNION ALL SELECT 'Qaz konforu', '4', 11
  UNION ALL SELECT 'Elektroalışma', 'Yoxdur', 12
  UNION ALL SELECT 'Soba həcmi, L', '63 lt', 13
  UNION ALL SELECT 'Qapaq', 'var', 14
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Tava, barmaqlıq', 15
) x
WHERE c.slug = 'bk-soba'
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
      AND c.slug = 'bk-soba'
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Polşa'
  WHEN 'gefest' THEN 'Belarus'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug = 'bk-soba'
  );
