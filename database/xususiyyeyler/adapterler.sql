USE maxhome_db;

-- Adapterlər (kabellər və adapterlər) üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ad_brand', 'Brend', 'text', NULL, NULL, 1, 3401),
('ad_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3402),
('ad_input', 'Giriş', 'text', NULL, NULL, 1, 3403),
('ad_ports', 'Portların sayı', 'text', NULL, NULL, 1, 3404),
('ad_type', 'Növü', 'text', NULL, NULL, 1, 3405),
('ad_power', 'Güc', 'text', 'W', NULL, 1, 3406),
('ad_color', 'Rəng', 'text', NULL, NULL, 1, 3407),
('ad_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3408)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Adapterlər kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ad_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ad_country', 2
  UNION ALL SELECT 'ad_input', 3
  UNION ALL SELECT 'ad_ports', 4
  UNION ALL SELECT 'ad_type', 5
  UNION ALL SELECT 'ad_power', 6
  UNION ALL SELECT 'ad_color', 7
  UNION ALL SELECT 'ad_warranty', 8
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('tv-ak-kabel')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

