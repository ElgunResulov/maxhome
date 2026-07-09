USE maxhome_db;

-- Qeympadlar (gamepad) üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('gp_brand', 'Brend', 'text', NULL, NULL, 1, 3201),
('gp_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3202),
('gp_interfaces', 'İnterfeyslər', 'text', NULL, NULL, 1, 3203),
('gp_compatibility', 'Uyğunluq', 'text', NULL, NULL, 1, 3204),
('gp_color', 'Rəng', 'text', NULL, NULL, 1, 3205),
('gp_connection_type', 'Qoşulma növü', 'text', NULL, NULL, 1, 3206),
('gp_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3207)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Qeympadlar kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'gp_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'gp_country', 2
  UNION ALL SELECT 'gp_interfaces', 3
  UNION ALL SELECT 'gp_compatibility', 4
  UNION ALL SELECT 'gp_color', 5
  UNION ALL SELECT 'gp_connection_type', 6
  UNION ALL SELECT 'gp_warranty', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('ga-qeympad')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

