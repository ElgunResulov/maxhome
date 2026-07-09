USE maxhome_db;

-- Paltaryuyan ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('wm_brand', 'Brend', 'text', NULL, NULL, 1, 3501),
('wm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3502),
('wm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3503),
('wm_type', 'Paltaryuyan növü', 'text', NULL, NULL, 1, 3504),
('wm_motor', 'Mühərrik tipi', 'text', NULL, NULL, 1, 3505),
('wm_max_load', 'Maksimal yükləmə', 'text', NULL, NULL, 1, 3506),
('wm_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 3507),
('wm_energy_class', 'Enerji sərfiyyatı sinfi', 'text', NULL, NULL, 1, 3508),
('wm_child_lock', 'Uşaq kilidi', 'text', NULL, NULL, 1, 3509),
('wm_load_type', 'Yükləmə tipi', 'text', NULL, NULL, 1, 3510),
('wm_color', 'Rəng', 'text', NULL, NULL, 1, 3511),
('wm_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3512),
('wm_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3513),
('wm_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 3514),
('wm_spin_speed', 'Fırlanma sürəti, dövr/dəq.', 'text', NULL, NULL, 1, 3515),
('wm_spin_noise', 'Sıxılma zamanı səs səviyyəsi', 'text', NULL, NULL, 1, 3516),
('wm_wash_noise', 'Yuma zamanı səs səviyyəsi', 'text', NULL, NULL, 1, 3517),
('wm_spin_class', 'Sıxılma sinfi', 'text', NULL, NULL, 1, 3518),
('wm_wash_class', 'Yuyulma sinfi', 'text', NULL, NULL, 1, 3519)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Paltaryuyan kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'wm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'wm_country', 2
  UNION ALL SELECT 'wm_product_type', 3
  UNION ALL SELECT 'wm_type', 4
  UNION ALL SELECT 'wm_motor', 5
  UNION ALL SELECT 'wm_max_load', 6
  UNION ALL SELECT 'wm_program_count', 7
  UNION ALL SELECT 'wm_energy_class', 8
  UNION ALL SELECT 'wm_child_lock', 9
  UNION ALL SELECT 'wm_load_type', 10
  UNION ALL SELECT 'wm_color', 11
  UNION ALL SELECT 'wm_dimensions', 12
  UNION ALL SELECT 'wm_warranty', 13
  UNION ALL SELECT 'wm_included', 14
  UNION ALL SELECT 'wm_spin_speed', 15
  UNION ALL SELECT 'wm_spin_noise', 16
  UNION ALL SELECT 'wm_wash_noise', 17
  UNION ALL SELECT 'wm_spin_class', 18
  UNION ALL SELECT 'wm_wash_class', 19
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('bk-paltaryuyan')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Paltaryuyan mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('bk-paltaryuyan');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Paltaryuyan', 3
  UNION ALL SELECT 'Paltaryuyan növü', 'Solo', 4
  UNION ALL SELECT 'Mühərrik tipi', 'Standart', 5
  UNION ALL SELECT 'Maksimal yükləmə', '8 kq', 6
  UNION ALL SELECT 'Proqramların sayı', '15', 7
  UNION ALL SELECT 'Enerji sərfiyyatı sinfi', 'A', 8
  UNION ALL SELECT 'Uşaq kilidi', 'var', 9
  UNION ALL SELECT 'Yükləmə tipi', 'Öndən', 10
  UNION ALL SELECT 'Rəng', 'Ağ', 11
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '84.5x59.8x59 sm', 12
  UNION ALL SELECT 'Zəmanət', '3 il', 13
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Şlanqlar', 14
  UNION ALL SELECT 'Fırlanma sürəti, dövr/dəq.', '1400 dövrə/dəq', 15
  UNION ALL SELECT 'Sıxılma zamanı səs səviyyəsi', '73 db', 16
  UNION ALL SELECT 'Yuma zamanı səs səviyyəsi', '55 db', 17
  UNION ALL SELECT 'Sıxılma sinfi', 'A', 18
  UNION ALL SELECT 'Yuyulma sinfi', 'A', 19
) x
WHERE c.slug IN ('bk-paltaryuyan')
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
      AND c.slug IN ('bk-paltaryuyan')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'bosch' THEN 'Polşa'
  WHEN 'lg' THEN 'Cənubi Koreya'
  WHEN 'samsung' THEN 'Polşa'
  WHEN 'beko' THEN 'Türkiyə'
  WHEN 'midea' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('bk-paltaryuyan')
  );
