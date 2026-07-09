  USE maxhome_db;

  -- Alt TV stendlər üçün xüsusiyyətlər
  INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
  VALUES
  ('atv_brand', 'Brend', 'text', NULL, NULL, 1, 6701),
  ('atv_material', 'Material', 'text', NULL, NULL, 1, 6702),
  ('atv_mechanism', 'Mexanizm', 'text', NULL, NULL, 1, 6703),
  ('atv_dimensions', 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'text', NULL, NULL, 1, 6704),
  ('atv_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6705),
  ('atv_color', 'Rəng', 'text', NULL, NULL, 1, 6706),
  ('atv_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6707)
  ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    input_type = VALUES(input_type),
    unit = VALUES(unit),
    options_json = VALUES(options_json),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

  -- Kateqoriya map (slug + ad fallback)
  INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
  SELECT c.id, s.id, 1 AS is_required, x.sort_order
  FROM (
    SELECT 'atv_brand' AS spec_key, 1 AS sort_order
    UNION ALL SELECT 'atv_material', 2
    UNION ALL SELECT 'atv_mechanism', 3
    UNION ALL SELECT 'atv_dimensions', 4
    UNION ALL SELECT 'atv_product_type', 5
    UNION ALL SELECT 'atv_color', 6
    UNION ALL SELECT 'atv_country', 7
  ) AS x
  INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
  INNER JOIN categories c
    ON (
      c.slug IN ('mb-tv-alt')
      OR c.name IN ('Alt TV stendlər', 'Alt TV stendler', 'Alt TV stend')
    )
  ON DUPLICATE KEY UPDATE
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order);

  -- Köhnə product specs məlumatlarını təmizlə
  DELETE ps
  FROM product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN categories c ON c.id = p.category_id
  WHERE c.slug IN ('mb-tv-alt')
    OR c.name IN ('Alt TV stendlər', 'Alt TV stendler', 'Alt TV stend');

  -- Default product specs doldur
  INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
  SELECT p.id, x.spec_key, x.spec_value, x.sort_order
  FROM products p
  INNER JOIN categories c ON c.id = p.category_id
  INNER JOIN (
    SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
    UNION ALL SELECT 'Material', 'MDF', 2
    UNION ALL SELECT 'Mexanizm', 'açılan', 3
    UNION ALL SELECT 'Ölçülər: Eni / Hündürüyü / Dərinliyi', 'E: 1076 H: 344 D: 485', 4
    UNION ALL SELECT 'Məhsul tipi', 'TV stend', 5
    UNION ALL SELECT 'Rəng', 'Ağ', 6
    UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 7
  ) x
  WHERE c.slug IN ('mb-tv-alt')
    OR c.name IN ('Alt TV stendlər', 'Alt TV stendler', 'Alt TV stend')
  ORDER BY p.id, x.sort_order;

  -- Brend dəyərini brands cədvəlindən doldur
  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = b.name
  WHERE ps.spec_key = 'Brend'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND (c.slug IN ('mb-tv-alt') OR c.name IN ('Alt TV stendlər', 'Alt TV stendler', 'Alt TV stend'))
    );

  -- İstehsalçı ölkə dəyərini brend slug-a görə doldur
  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = CASE b.slug
    WHEN 'bellona' THEN 'Türkiyə'
    WHEN 'istikbal' THEN 'Türkiyə'
    WHEN 'weltew' THEN 'Türkiyə'
    ELSE 'Çin'
  END
  WHERE ps.spec_key = 'İstehsalçı ölkə'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND (c.slug IN ('mb-tv-alt') OR c.name IN ('Alt TV stendlər', 'Alt TV stendler', 'Alt TV stend'))
    );
