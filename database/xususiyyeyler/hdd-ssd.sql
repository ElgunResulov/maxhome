USE maxhome_db;

-- HDD / SSD üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ds_brand', 'Brend', 'text', NULL, NULL, 1, 3101),
('ds_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 3102),
('ds_capacity', 'Yaddaş', 'text', 'GB', NULL, 1, 3103),
('ds_type', 'Növü', 'text', NULL, NULL, 1, 3104),
('ds_speed_range', 'Tezlik diapazonu', 'text', NULL, NULL, 1, 3105),
('ds_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 3106)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- HDD / SSD kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ds_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ds_country', 2
  UNION ALL SELECT 'ds_capacity', 3
  UNION ALL SELECT 'ds_type', 4
  UNION ALL SELECT 'ds_speed_range', 5
  UNION ALL SELECT 'ds_warranty', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('nw-disk')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

