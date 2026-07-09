USE maxhome_db;

-- Video texnika ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('vd2_brand', 'Brend', 'text', NULL, NULL, 1, 3101),
('vd2_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3102),
('vd2_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 3103),
('vd2_kind', 'Növü', 'text', NULL, NULL, 1, 3104),
('vd2_sensor', 'Matrisa tipi', 'text', NULL, NULL, 1, 3105),
('vd2_processor', 'Prosessor növü', 'text', NULL, NULL, 1, 3106),
('vd2_video_resolution', 'Video çəkilişin maksimal icazəsi', 'text', NULL, NULL, 1, 3107),
('vd2_burst', 'Sürətli çəkiliş', 'text', NULL, NULL, 1, 3108),
('vd2_image_format', 'Şəkil sıxılma formatı', 'text', NULL, NULL, 1, 3109),
('vd2_card_support', 'Yaddaş kartı dəstəyi', 'text', NULL, NULL, 1, 3110),
('vd2_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 3111),
('vd2_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 3112),
('vd2_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3113)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Video texnika kateqoriyalarina map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'vd2_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'vd2_country', 2
  UNION ALL SELECT 'vd2_type', 3
  UNION ALL SELECT 'vd2_kind', 4
  UNION ALL SELECT 'vd2_sensor', 5
  UNION ALL SELECT 'vd2_processor', 6
  UNION ALL SELECT 'vd2_video_resolution', 7
  UNION ALL SELECT 'vd2_burst', 8
  UNION ALL SELECT 'vd2_image_format', 9
  UNION ALL SELECT 'vd2_card_support', 10
  UNION ALL SELECT 'vd2_bluetooth', 11
  UNION ALL SELECT 'vd2_dimensions', 12
  UNION ALL SELECT 'vd2_warranty', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('tv-audio-video-b-03-video-texnika', 'vd-proyektor', 'vd-pleyer')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Video texnika mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tv-audio-video-b-03-video-texnika', 'vd-proyektor', 'vd-pleyer');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Fotokamera', 3
  UNION ALL SELECT 'Növü', 'Güzgüsüz', 4
  UNION ALL SELECT 'Matrisa tipi', 'CMOS', 5
  UNION ALL SELECT 'Prosessor növü', 'DIGIC', 6
  UNION ALL SELECT 'Video çəkilişin maksimal icazəsi', '3840 x 2160 (4K UHD)', 7
  UNION ALL SELECT 'Sürətli çəkiliş', '15 kadr/s', 8
  UNION ALL SELECT 'Şəkil sıxılma formatı', 'HEIF, JPEG, RAW', 9
  UNION ALL SELECT 'Yaddaş kartı dəstəyi', 'SD, SDHC, SDXC', 10
  UNION ALL SELECT 'Bluetooth', 'var', 11
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', '19.9 x 8.88 sm', 12
  UNION ALL SELECT 'Zəmanət', '1 il', 13
) x
WHERE c.slug IN ('tv-audio-video-b-03-video-texnika', 'vd-proyektor', 'vd-pleyer')
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
      AND c.slug IN ('tv-audio-video-b-03-video-texnika', 'vd-proyektor', 'vd-pleyer')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'canon' THEN 'Yaponiya'
  WHEN 'sony' THEN 'Yaponiya'
  WHEN 'nikon' THEN 'Yaponiya'
  WHEN 'fujifilm' THEN 'Yaponiya'
  WHEN 'panasonic' THEN 'Yaponiya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('tv-audio-video-b-03-video-texnika', 'vd-proyektor', 'vd-pleyer')
  );
