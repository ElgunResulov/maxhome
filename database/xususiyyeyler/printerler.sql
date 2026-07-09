USE maxhome_db;

-- Printerlər üçün konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('pr_brand', 'Brend', 'text', NULL, NULL, 1, 2901),
('pr_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2902),
('pr_weight', 'Çəki', 'text', 'kq', NULL, 1, 2903),
('pr_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', 'mm', NULL, 1, 2904),
('pr_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2905),
('pr_color', 'Rəng', 'text', NULL, NULL, 1, 2906),
('pr_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2907),
('pr_cartridge', 'Kartric', 'number', NULL, NULL, 1, 2908),
('pr_print_speed', 'Çap sürəti', 'text', NULL, NULL, 1, 2909),
('pr_print_color', 'Rəngli çap', 'text', NULL, NULL, 1, 2910),
('pr_colors_count', 'Rəng sayı', 'number', NULL, NULL, 1, 2911),
('pr_print_tech', 'Çap texnologiyası', 'text', NULL, NULL, 1, 2912),
('pr_scanner', 'Skaner', 'text', NULL, NULL, 1, 2913),
('pr_copy', 'Kopir', 'text', NULL, NULL, 1, 2914),
('pr_print_formats', 'Çap formatı', 'text', NULL, NULL, 1, 2915),
('pr_max_dpi', 'Çapın maksimal nöqtələrinin sayı (dpi)', 'text', 'dpi', NULL, 1, 2916)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Printerlər kateqoriyasına map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'pr_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'pr_product_type', 2
  UNION ALL SELECT 'pr_weight', 3
  UNION ALL SELECT 'pr_warranty', 4
  UNION ALL SELECT 'pr_print_speed', 5
  UNION ALL SELECT 'pr_print_color', 6
  UNION ALL SELECT 'pr_colors_count', 7
  UNION ALL SELECT 'pr_print_tech', 8
  UNION ALL SELECT 'pr_scanner', 9
  UNION ALL SELECT 'pr_country', 10
  UNION ALL SELECT 'pr_color', 11
  UNION ALL SELECT 'pr_dimensions', 12
  UNION ALL SELECT 'pr_cartridge', 13
  UNION ALL SELECT 'pr_print_formats', 14
  UNION ALL SELECT 'pr_max_dpi', 15
  UNION ALL SELECT 'pr_copy', 16
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('pr-printer')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

