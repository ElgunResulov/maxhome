USE maxhome_db;

-- Noutbuklar üçün konkret xususiyyet seti (admin paneldə görünəcək sahələr)
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('nb_cpu_cores', 'CPU core sayı', 'number', NULL, NULL, 1, 2601),
('nb_cpu_threads', 'Thread sayı', 'number', NULL, NULL, 1, 2602),
('nb_cpu_base_boost', 'CPU base/boost', 'text', 'GHz', NULL, 1, 2603),
('nb_gpu_vram', 'GPU VRAM', 'text', NULL, NULL, 1, 2604),
('nb_ram_slots', 'RAM slot sayı', 'number', NULL, NULL, 1, 2605),
('nb_ram_max', 'RAM max dəstəyi', 'text', NULL, NULL, 1, 2606),
('nb_ssd_type', 'SSD tipi (NVMe/SATA)', 'text', NULL, NULL, 1, 2607),
('nb_ssd_slots', 'SSD slot sayı', 'number', NULL, NULL, 1, 2608),
('nb_display_gamut', 'Display color gamut (sRGB, DCI-P3)', 'text', NULL, NULL, 1, 2609),
('nb_refresh_rate', 'Refresh rate', 'number', 'Hz', NULL, 1, 2610),
('nb_touchscreen', 'Touchscreen', 'boolean', NULL, NULL, 1, 2611),
('nb_webcam', 'Webcam', 'text', 'MP', NULL, 1, 2612),
('nb_fingerprint', 'Fingerprint', 'boolean', NULL, NULL, 1, 2613),
('nb_keyboard_backlight', 'Keyboard backlight', 'boolean', NULL, NULL, 1, 2614),
('nb_battery', 'Battery', 'text', 'Wh', NULL, 1, 2615),
('nb_adapter_power', 'Adapter gücü', 'text', 'W', NULL, 1, 2616),
('nb_material', 'Material (aluminum/plastic)', 'text', NULL, NULL, 1, 2617)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Noutbuklar kateqoriyalarına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 0 AS is_required, x.sort_order
FROM (
  SELECT 'nb_cpu_cores' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'nb_cpu_threads', 2
  UNION ALL SELECT 'nb_cpu_base_boost', 3
  UNION ALL SELECT 'nb_gpu_vram', 4
  UNION ALL SELECT 'nb_ram_slots', 5
  UNION ALL SELECT 'nb_ram_max', 6
  UNION ALL SELECT 'nb_ssd_type', 7
  UNION ALL SELECT 'nb_ssd_slots', 8
  UNION ALL SELECT 'nb_display_gamut', 9
  UNION ALL SELECT 'nb_refresh_rate', 10
  UNION ALL SELECT 'nb_touchscreen', 11
  UNION ALL SELECT 'nb_webcam', 12
  UNION ALL SELECT 'nb_fingerprint', 13
  UNION ALL SELECT 'nb_keyboard_backlight', 14
  UNION ALL SELECT 'nb_battery', 15
  UNION ALL SELECT 'nb_adapter_power', 16
  UNION ALL SELECT 'nb_material', 17
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN (
  'noutbuklar-ve-komp-b-01-noutbuklar',
  'nb-oyun',
  'nb-is',
  'nb-ultra-yungul',
  'nb-butun'
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

