USE maxhome_db;

-- Oyun monitorları üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('mn_brand', 'Brend', 'text', NULL, NULL, 1, 2801),
('mn_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2802),
('mn_matrix_type', 'Matris tipi', 'text', NULL, NULL, 1, 2803),
('mn_screen_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2804),
('mn_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2805),
('mn_coating', 'Ekran örtüyünün növü', 'text', NULL, NULL, 1, 2806),
('mn_aspect_ratio', 'Ekran', 'text', NULL, NULL, 1, 2807),
('mn_color', 'Rəng', 'text', NULL, NULL, 1, 2808),
('mn_weight', 'Çəki', 'text', NULL, NULL, 1, 2809),
('mn_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2810),
('mn_in_box', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2811),
('mn_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2812),
('mn_contrast', 'Kontrast', 'text', NULL, NULL, 1, 2813),
('mn_viewing_angles', 'Görüntü sahəsi', 'text', NULL, NULL, 1, 2814),
('mn_brightness', 'Parlaqlıq', 'text', NULL, NULL, 1, 2815)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Oyun monitorları kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'mn_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'mn_product_type', 2
  UNION ALL SELECT 'mn_matrix_type', 3
  UNION ALL SELECT 'mn_screen_size', 4
  UNION ALL SELECT 'mn_resolution', 5
  UNION ALL SELECT 'mn_coating', 6
  UNION ALL SELECT 'mn_aspect_ratio', 7
  UNION ALL SELECT 'mn_color', 8
  UNION ALL SELECT 'mn_weight', 9
  UNION ALL SELECT 'mn_dimensions', 10
  UNION ALL SELECT 'mn_in_box', 11
  UNION ALL SELECT 'mn_warranty', 12
  UNION ALL SELECT 'mn_contrast', 13
  UNION ALL SELECT 'mn_brightness', 14
  UNION ALL SELECT 'mn_viewing_angles', 15
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('mn-oyun')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

