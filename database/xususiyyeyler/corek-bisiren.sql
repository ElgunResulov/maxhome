USE maxhome_db;

-- Corek bisiren ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('bm_brand', 'Brend', 'text', NULL, NULL, 1, 4501),
('bm_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4502),
('bm_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4503),
('bm_program_count', 'Proqramların sayı', 'text', NULL, NULL, 1, 4504),
('bm_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 4505),
('bm_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 4506),
('bm_color', 'Rəng', 'text', NULL, NULL, 1, 4507),
('bm_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4508),
('bm_bake_form', 'Bişmə forması', 'text', NULL, NULL, 1, 4509),
('bm_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4510),
('bm_cake_mode', 'Keks', 'text', NULL, NULL, 1, 4511),
('bm_display', 'Display', 'text', NULL, NULL, 1, 4512),
('bm_max_weight', 'Bişmənin maksimal çəkisi', 'text', NULL, NULL, 1, 4513),
('bm_coating', 'Forma örtüyü', 'text', NULL, NULL, 1, 4514),
('bm_upper_color_select', 'Üst qabığın rəng seçimi', 'text', NULL, NULL, 1, 4515),
('bm_delay_start', 'İşə başlamanın təxirə salınması', 'text', NULL, NULL, 1, 4516),
('bm_view_window', 'Baxış üçün pəncərə', 'text', NULL, NULL, 1, 4517),
('bm_weight_select', 'Bişmə çəkisinin seçilməsi', 'text', NULL, NULL, 1, 4518),
('bm_kneading_blade', 'Xəmirin qarışdırılması üçün qanad', 'text', NULL, NULL, 1, 4519)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Corek bisiren kateqoriyasina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'bm_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'bm_country', 2
  UNION ALL SELECT 'bm_product_type', 3
  UNION ALL SELECT 'bm_program_count', 4
  UNION ALL SELECT 'bm_control_type', 5
  UNION ALL SELECT 'bm_body_material', 6
  UNION ALL SELECT 'bm_color', 7
  UNION ALL SELECT 'bm_warranty', 8
  UNION ALL SELECT 'bm_bake_form', 9
  UNION ALL SELECT 'bm_included', 10
  UNION ALL SELECT 'bm_cake_mode', 11
  UNION ALL SELECT 'bm_display', 12
  UNION ALL SELECT 'bm_max_weight', 13
  UNION ALL SELECT 'bm_coating', 14
  UNION ALL SELECT 'bm_upper_color_select', 15
  UNION ALL SELECT 'bm_delay_start', 16
  UNION ALL SELECT 'bm_view_window', 17
  UNION ALL SELECT 'bm_weight_select', 18
  UNION ALL SELECT 'bm_kneading_blade', 19
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('km-corek-bisiren')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Corek bisiren mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('km-corek-bisiren');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Çörəkbişirən', 3
  UNION ALL SELECT 'Proqramların sayı', '21', 4
  UNION ALL SELECT 'İdarəetmə növü', 'Düymə ilə', 5
  UNION ALL SELECT 'Korpus Materialı', 'Plastik', 6
  UNION ALL SELECT 'Rəng', 'Ağ', 7
  UNION ALL SELECT 'Zəmanət', '3 il', 8
  UNION ALL SELECT 'Bişmə forması', 'Düzbucaqlı', 9
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Bişmə üçün forma, ölçü stəkanı', 10
  UNION ALL SELECT 'Keks', 'var', 11
  UNION ALL SELECT 'Display', 'var', 12
  UNION ALL SELECT 'Bişmənin maksimal çəkisi', '1.1 kq', 13
  UNION ALL SELECT 'Forma örtüyü', 'Yapışmayan örtük', 14
  UNION ALL SELECT 'Üst qabığın rəng seçimi', 'var', 15
  UNION ALL SELECT 'İşə başlamanın təxirə salınması', 'var', 16
  UNION ALL SELECT 'Baxış üçün pəncərə', 'yox', 17
  UNION ALL SELECT 'Bişmə çəkisinin seçilməsi', 'var', 18
  UNION ALL SELECT 'Xəmirin qarışdırılması üçün qanad', 'var', 19
) x
WHERE c.slug IN ('km-corek-bisiren')
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
      AND c.slug IN ('km-corek-bisiren')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'panasonic' THEN 'Çin'
  WHEN 'tefal' THEN 'Fransa'
  WHEN 'moulinex' THEN 'Çin'
  WHEN 'philips' THEN 'Çin'
  WHEN 'kenwood' THEN 'Çin'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('km-corek-bisiren')
  );
