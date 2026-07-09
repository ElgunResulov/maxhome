  USE maxhome_db;

  -- Yataq destleri ucun xususiyyetler
  INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
  VALUES
  ('yd_brand', 'Brend', 'text', NULL, NULL, 1, 7301),
  ('yd_material', 'Material', 'text', NULL, NULL, 1, 7302),
  ('yd_duvet_count', 'Yorğan üzü', 'text', NULL, NULL, 1, 7303),
  ('yd_duvet_size', 'Yorğan üzü ölçü', 'text', 'sm', NULL, 1, 7304),
  ('yd_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 7305),
  ('yd_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 7306),
  ('yd_sheet_size', 'Çarşaf ölçü', 'text', 'sm', NULL, 1, 7307),
  ('yd_pillow_size', 'Yastıq üzünün ölçüsü', 'text', 'sm', NULL, 1, 7308)
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
    SELECT 'yd_brand' AS spec_key, 1 AS sort_order
    UNION ALL SELECT 'yd_material', 2
    UNION ALL SELECT 'yd_duvet_count', 3
    UNION ALL SELECT 'yd_duvet_size', 4
    UNION ALL SELECT 'yd_product_type', 5
    UNION ALL SELECT 'yd_country', 6
    UNION ALL SELECT 'yd_sheet_size', 7
    UNION ALL SELECT 'yd_pillow_size', 8
  ) AS x
  INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
  INNER JOIN categories c
    ON (
      c.slug IN ('tx-yataq-destleri')
      OR c.name IN ('Yataq dəstləri', 'Yataq destleri', 'Yataq dəsti')
    )
  ON DUPLICATE KEY UPDATE
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order);

  -- Kohne product specs temizle
  DELETE ps
  FROM product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN categories c ON c.id = p.category_id
  WHERE c.slug IN ('tx-yataq-destleri')
    OR c.name IN ('Yataq dəstləri', 'Yataq destleri', 'Yataq dəsti');

  -- Default product specs doldur
  INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
  SELECT p.id, x.spec_key, x.spec_value, x.sort_order
  FROM products p
  INNER JOIN categories c ON c.id = p.category_id
  INNER JOIN (
    SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
    UNION ALL SELECT 'Material', '100 % pambıq', 2
    UNION ALL SELECT 'Yorğan üzü', '2 əd', 3
    UNION ALL SELECT 'Yorğan üzü ölçü', '160*220', 4
    UNION ALL SELECT 'Məhsul tipi', 'Yataq dəsti (ailəvi)', 5
    UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 6
    UNION ALL SELECT 'Çarşaf ölçü', '240*260', 7
    UNION ALL SELECT 'Yastıq üzünün ölçüsü', '50*70', 8
  ) x
  WHERE c.slug IN ('tx-yataq-destleri')
    OR c.name IN ('Yataq dəstləri', 'Yataq destleri', 'Yataq dəsti')
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
        AND (c.slug IN ('tx-yataq-destleri') OR c.name IN ('Yataq dəstləri', 'Yataq destleri', 'Yataq dəsti'))
    );

  -- İstehsalçı ölkə dəyərini brend slug-a görə doldur
  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = CASE b.slug
    WHEN 'madame-coco' THEN 'Türkiyə'
    WHEN 'karaca-home' THEN 'Türkiyə'
    WHEN 'english-home' THEN 'Türkiyə'
    WHEN 'cotton-box' THEN 'Türkiyə'
    ELSE 'Çin'
  END
  WHERE ps.spec_key = 'İstehsalçı ölkə'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND (c.slug IN ('tx-yataq-destleri') OR c.name IN ('Yataq dəstləri', 'Yataq destleri', 'Yataq dəsti'))
    );
