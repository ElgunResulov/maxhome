USE maxhome_db;

-- Skanerlər üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('sc_brand', 'Brend', 'text', NULL, NULL, 1, 3001),
('sc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3002),
('sc_speed', 'Sürət sayı', 'text', NULL, NULL, 1, 3003),
('sc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3004),
('sc_use_type', 'İstifadə növü', 'text', NULL, NULL, 1, 3005),
('sc_type', 'Növü', 'text', NULL, NULL, 1, 3006),
('sc_interfaces', 'İnterfeyslər', 'text', NULL, NULL, 1, 3007),
('sc_size', 'Ölçü', 'text', NULL, NULL, 1, 3008),
('sc_color', 'Rəng', 'text', NULL, NULL, 1, 3009),
('sc_format', 'Format', 'text', NULL, NULL, 1, 3010),
('sc_max_dpi', 'Çapın maksimal nöqtələrinin sayı (dpi)', 'text', 'dpi', NULL, 1, 3011),
('sc_print_color', 'Çapın rəngi', 'text', NULL, NULL, 1, 3012),
('sc_weight', 'Çəki', 'text', 'kq', NULL, 1, 3013)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Skanerlər kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'sc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'sc_country', 2
  UNION ALL SELECT 'sc_speed', 3
  UNION ALL SELECT 'sc_warranty', 4
  UNION ALL SELECT 'sc_use_type', 5
  UNION ALL SELECT 'sc_type', 6
  UNION ALL SELECT 'sc_interfaces', 7
  UNION ALL SELECT 'sc_size', 8
  UNION ALL SELECT 'sc_color', 9
  UNION ALL SELECT 'sc_format', 10
  UNION ALL SELECT 'sc_max_dpi', 11
  UNION ALL SELECT 'sc_print_color', 12
  UNION ALL SELECT 'sc_weight', 13
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('pr-skaner')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

