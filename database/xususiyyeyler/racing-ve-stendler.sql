USE maxhome_db;

-- Racing və stendlər üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('rc_brand', 'Brend', 'text', NULL, NULL, 1, 3301),
('rc_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3302),
('rc_color', 'Rəng', 'text', NULL, NULL, 1, 3303),
('rc_weight', 'Çəki', 'text', 'kq', NULL, 1, 3304),
('rc_connection', 'Qoşulma növü', 'text', NULL, NULL, 1, 3305),
('rc_rotation_angle', 'Dönmə anı', 'text', '°', NULL, 1, 3306),
('rc_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3307)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Racing və stendlər kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'rc_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'rc_country', 2
  UNION ALL SELECT 'rc_color', 3
  UNION ALL SELECT 'rc_weight', 4
  UNION ALL SELECT 'rc_connection', 5
  UNION ALL SELECT 'rc_rotation_angle', 6
  UNION ALL SELECT 'rc_warranty', 7
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('vr-racing')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

