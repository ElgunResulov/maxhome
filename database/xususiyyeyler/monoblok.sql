USE maxhome_db;

-- Monobloklar üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('mb_brand', 'Brend', 'text', NULL, NULL, 1, 2701),
('mb_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2702),
('mb_cpu', 'Prosessor tipi', 'text', NULL, NULL, 1, 2703),
('mb_cpu_speed', 'Prosessor tezliyi', 'text', NULL, NULL, 1, 2704),
('mb_cpu_cores', 'Nüvələrin sayı', 'number', NULL, NULL, 1, 2705),
('mb_os', 'Əməliyyat sistemi', 'text', NULL, NULL, 1, 2706),
('mb_ram', 'Operativ yaddaş', 'text', 'GB', NULL, 1, 2707),
('mb_storage', 'Quraşdırılmış yaddaş', 'text', 'GB', NULL, 1, 2708),
('mb_screen_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2709),
('mb_screen_resolution', 'Ekran icazəsi', 'text', NULL, NULL, 1, 2710),
('mb_screen_type', 'Ekran örtüyünün növü', 'text', NULL, NULL, 1, 2711),
('mb_bluetooth', 'Bluetooth', 'text', NULL, NULL, 1, 2712),
('mb_hdmi', 'HDMI', 'text', NULL, NULL, 1, 2713),
('mb_webcam', 'Veb-kamera', 'text', NULL, NULL, 1, 2714),
('mb_speakers_count', 'Dinamiklərin sayı', 'number', NULL, NULL, 1, 2715),
('mb_color', 'Rəng', 'text', NULL, NULL, 1, 2716),
('mb_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2717),
('mb_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2718),
('mb_in_box', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2719)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Monoblok kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'mb_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'mb_product_type', 2
  UNION ALL SELECT 'mb_cpu', 3
  UNION ALL SELECT 'mb_cpu_speed', 4
  UNION ALL SELECT 'mb_cpu_cores', 5
  UNION ALL SELECT 'mb_os', 6
  UNION ALL SELECT 'mb_ram', 7
  UNION ALL SELECT 'mb_storage', 8
  UNION ALL SELECT 'mb_screen_size', 9
  UNION ALL SELECT 'mb_screen_resolution', 10
  UNION ALL SELECT 'mb_screen_type', 11
  UNION ALL SELECT 'mb_bluetooth', 12
  UNION ALL SELECT 'mb_hdmi', 13
  UNION ALL SELECT 'mb_webcam', 14
  UNION ALL SELECT 'mb_speakers_count', 15
  UNION ALL SELECT 'mb_color', 16
  UNION ALL SELECT 'mb_country', 17
  UNION ALL SELECT 'mb_warranty', 18
  UNION ALL SELECT 'mb_in_box', 19
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('pc-monoblok')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

