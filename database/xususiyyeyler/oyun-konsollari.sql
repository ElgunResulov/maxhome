USE maxhome_db;

-- Oyun konsollari ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('gc_brand', 'Brend', 'text', NULL, NULL, 1, 2801),
('gc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2802),
('gc_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2803),
('gc_cpu', 'Prosessor tipi', 'text', NULL, NULL, 1, 2804),
('gc_gpu', 'Qrafik prosessor', 'text', NULL, NULL, 1, 2805),
('gc_ram', 'Operativ yaddaş', 'text', NULL, NULL, 1, 2806),
('gc_storage', 'Quraşdırılmış yaddaş', 'text', NULL, NULL, 1, 2807),
('gc_cores', 'Nüvələrin sayı', 'text', NULL, NULL, 1, 2808),
('gc_usb', 'USB', 'text', NULL, NULL, 1, 2809),
('gc_wifi', 'Wi-Fi', 'text', NULL, NULL, 1, 2810),
('gc_color', 'Rəng', 'text', NULL, NULL, 1, 2811),
('gc_weight', 'Çəki', 'text', NULL, NULL, 1, 2812),
('gc_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2813),
('gc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2814),
('gc_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2815)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Oyun konsolu kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'gc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'gc_country', 2
  UNION ALL SELECT 'gc_product_type', 3
  UNION ALL SELECT 'gc_cpu', 4
  UNION ALL SELECT 'gc_gpu', 5
  UNION ALL SELECT 'gc_ram', 6
  UNION ALL SELECT 'gc_storage', 7
  UNION ALL SELECT 'gc_cores', 8
  UNION ALL SELECT 'gc_usb', 9
  UNION ALL SELECT 'gc_wifi', 10
  UNION ALL SELECT 'gc_color', 11
  UNION ALL SELECT 'gc_weight', 12
  UNION ALL SELECT 'gc_dimensions', 13
  UNION ALL SELECT 'gc_warranty', 14
  UNION ALL SELECT 'gc_included', 15
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'oyun-konsollar-b-01-oyun-konsollari',
  'gm-playstation',
  'gm-xbox',
  'gm-nintendo',
  'gm-butun-konsol'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Oyun konsolu mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN (
  'oyun-konsollar-b-01-oyun-konsollari',
  'gm-playstation',
  'gm-xbox',
  'gm-nintendo',
  'gm-butun-konsol'
);

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Stasionar oyun konsolu', 3
  UNION ALL SELECT 'Prosessor tipi', 'AMD Zen 2', 4
  UNION ALL SELECT 'Qrafik prosessor', 'AMD RDNA 2', 5
  UNION ALL SELECT 'Operativ yaddaş', '16 GB', 6
  UNION ALL SELECT 'Quraşdırılmış yaddaş', '1 TB', 7
  UNION ALL SELECT 'Nüvələrin sayı', '8-nüvəli', 8
  UNION ALL SELECT 'USB', 'x3', 9
  UNION ALL SELECT 'Wi-Fi', 'var', 10
  UNION ALL SELECT 'Rəng', 'Ağ', 11
  UNION ALL SELECT 'Çəki', '2.6 kq', 12
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '358 × 80 × 216 mm', 13
  UNION ALL SELECT 'Zəmanət', '1 il', 14
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Kontroller, HDMI-kabel, USB-kabel', 15
) x
WHERE c.slug IN (
  'oyun-konsollar-b-01-oyun-konsollari',
  'gm-playstation',
  'gm-xbox',
  'gm-nintendo',
  'gm-butun-konsol'
)
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
      AND c.slug IN (
        'oyun-konsollar-b-01-oyun-konsollari',
        'gm-playstation',
        'gm-xbox',
        'gm-nintendo',
        'gm-butun-konsol'
      )
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'sony' THEN 'Yaponiya'
  WHEN 'microsoft' THEN 'ABŞ'
  WHEN 'nintendo' THEN 'Yaponiya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN (
        'oyun-konsollar-b-01-oyun-konsollari',
        'gm-playstation',
        'gm-xbox',
        'gm-nintendo',
        'gm-butun-konsol'
      )
  );
