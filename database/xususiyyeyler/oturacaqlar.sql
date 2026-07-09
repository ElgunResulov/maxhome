  USE maxhome_db;

  -- Oturacaq/Oturacaqlar ucun xususiyyetler
  INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
  VALUES
  ('ot_brand', 'Brend', 'text', NULL, NULL, 1, 6501),
  ('ot_sponge_density', 'Süngər sıxlığı', 'text', NULL, NULL, 1, 6502),
  ('ot_seat_sponge_thickness', 'Oturacaq süngər qalınlığı', 'text', 'sm', NULL, 1, 6503),
  ('ot_seat_width', 'Oturma eni', 'text', 'sm', NULL, 1, 6504),
  ('ot_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6505),
  ('ot_color', 'Rəng', 'text', NULL, NULL, 1, 6506),
  ('ot_leg_material', 'Ayaq materialı', 'text', NULL, NULL, 1, 6507),
  ('ot_fabric', 'Parça', 'text', NULL, NULL, 1, 6508),
  ('ot_back_sponge_thickness', 'Arxa süngər qalınlığı', 'text', 'sm', NULL, 1, 6509),
  ('ot_seat_depth', 'Oturma dərinliyi', 'text', 'sm', NULL, 1, 6510),
  ('ot_seat_height', 'Oturma hündürlüyü', 'text', 'sm', NULL, 1, 6511),
  ('ot_height', 'Hündürlüyü', 'text', 'sm', NULL, 1, 6512),
  ('ot_weight', 'Ağırlıq', 'text', 'kq', NULL, 1, 6513),
  ('ot_leg_color', 'Ayaq rəngi', 'text', NULL, NULL, 1, 6514),
  ('ot_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 6515)
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
    SELECT 'ot_brand' AS spec_key, 1 AS sort_order
    UNION ALL SELECT 'ot_sponge_density', 2
    UNION ALL SELECT 'ot_seat_sponge_thickness', 3
    UNION ALL SELECT 'ot_seat_width', 4
    UNION ALL SELECT 'ot_product_type', 5
    UNION ALL SELECT 'ot_color', 6
    UNION ALL SELECT 'ot_leg_material', 7
    UNION ALL SELECT 'ot_fabric', 8
    UNION ALL SELECT 'ot_back_sponge_thickness', 9
    UNION ALL SELECT 'ot_seat_depth', 10
    UNION ALL SELECT 'ot_seat_height', 11
    UNION ALL SELECT 'ot_height', 12
    UNION ALL SELECT 'ot_weight', 13
    UNION ALL SELECT 'ot_leg_color', 14
    UNION ALL SELECT 'ot_country', 15
  ) AS x
  INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
  INNER JOIN categories c
    ON (
      c.slug IN ('mb-qonaq-oturacaqlar', 'mb-genc-oturacaq')
      OR c.name IN ('Oturacaqlar', 'Oturacaq')
    )
  ON DUPLICATE KEY UPDATE
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order);

  -- Evvelki specs temizle
  DELETE ps
  FROM product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN categories c ON c.id = p.category_id
  WHERE c.slug IN ('mb-qonaq-oturacaqlar', 'mb-genc-oturacaq')
    OR c.name IN ('Oturacaqlar', 'Oturacaq');

  -- Default specs doldur
  INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
  SELECT p.id, x.spec_key, x.spec_value, x.sort_order
  FROM products p
  INNER JOIN categories c ON c.id = p.category_id
  INNER JOIN (
    SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
    UNION ALL SELECT 'Süngər sıxlığı', '32 danste orta sərt', 2
    UNION ALL SELECT 'Oturacaq süngər qalınlığı', '4', 3
    UNION ALL SELECT 'Oturma eni', '46', 4
    UNION ALL SELECT 'Məhsul tipi', 'Oturacaq', 5
    UNION ALL SELECT 'Rəng', 'Krem', 6
    UNION ALL SELECT 'Ayaq materialı', 'Metal Profil', 7
    UNION ALL SELECT 'Parça', 'Yumşaq, antibakterial', 8
    UNION ALL SELECT 'Arxa süngər qalınlığı', '4', 9
    UNION ALL SELECT 'Oturma dərinliyi', '46', 10
    UNION ALL SELECT 'Oturma hündürlüyü', '47', 11
    UNION ALL SELECT 'Hündürlüyü', '86', 12
    UNION ALL SELECT 'Ağırlıq', '8', 13
    UNION ALL SELECT 'Ayaq rəngi', 'Qara', 14
    UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 15
  ) x
  WHERE c.slug IN ('mb-qonaq-oturacaqlar', 'mb-genc-oturacaq')
    OR c.name IN ('Oturacaqlar', 'Oturacaq')
  ORDER BY p.id, x.sort_order;

  -- Brendi brands cedvelinden doldur
  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = b.name
  WHERE ps.spec_key = 'Brend'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND (c.slug IN ('mb-qonaq-oturacaqlar', 'mb-genc-oturacaq') OR c.name IN ('Oturacaqlar', 'Oturacaq'))
    );

  -- Istehsalci olkeni brend slug-na gore update et
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
        AND (c.slug IN ('mb-qonaq-oturacaqlar', 'mb-genc-oturacaq') OR c.name IN ('Oturacaqlar', 'Oturacaq'))
    );
