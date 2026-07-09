  USE maxhome_db;

  -- Meyve qurudan ucun konkret xususiyyet seti
  INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
  VALUES
  ('fd_brand', 'Brend', 'text', NULL, NULL, 1, 4701),
  ('fd_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 4702),
  ('fd_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 4703),
  ('fd_power', 'İstehlak gücü, Vt', 'text', NULL, NULL, 1, 4704),
  ('fd_control_type', 'İdarəetmə növü', 'text', NULL, NULL, 1, 4705),
  ('fd_body_material', 'Korpus Materialı', 'text', NULL, NULL, 1, 4706),
  ('fd_color', 'Rəng', 'text', NULL, NULL, 1, 4707),
  ('fd_weight', 'Çəki', 'text', NULL, NULL, 1, 4708),
  ('fd_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 4709),
  ('fd_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 4710),
  ('fd_tray_count', 'Bölmələrin sayı', 'text', NULL, NULL, 1, 4711)
  ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    input_type = VALUES(input_type),
    unit = VALUES(unit),
    options_json = VALUES(options_json),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

  -- Meyve qurudan kateqoriyasina map et
  INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
  SELECT c.id, s.id, 1 AS is_required, x.sort_order
  FROM (
    SELECT 'fd_brand' AS spec_key, 1 AS sort_order
    UNION ALL SELECT 'fd_country', 2
    UNION ALL SELECT 'fd_product_type', 3
    UNION ALL SELECT 'fd_power', 4
    UNION ALL SELECT 'fd_control_type', 5
    UNION ALL SELECT 'fd_body_material', 6
    UNION ALL SELECT 'fd_color', 7
    UNION ALL SELECT 'fd_weight', 8
    UNION ALL SELECT 'fd_warranty', 9
    UNION ALL SELECT 'fd_included', 10
    UNION ALL SELECT 'fd_tray_count', 11
  ) AS x
  INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
  INNER JOIN categories c ON c.slug IN ('km-terevez-meyve-qurudan')
  ON DUPLICATE KEY UPDATE
    is_required = VALUES(is_required),
    sort_order = VALUES(sort_order);

  -- Meyve qurudan mehsullari ucun default product_specs
  DELETE ps
  FROM product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN categories c ON c.id = p.category_id
  WHERE c.slug IN ('km-terevez-meyve-qurudan');

  INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
  SELECT p.id, x.spec_key, x.spec_value, x.sort_order
  FROM products p
  INNER JOIN categories c ON c.id = p.category_id
  INNER JOIN (
    SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
    UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
    UNION ALL SELECT 'Məhsul tipi', 'Meyvə Qurudan', 3
    UNION ALL SELECT 'İstehlak gücü, Vt', '550 Vt', 4
    UNION ALL SELECT 'İdarəetmə növü', 'Elektron', 5
    UNION ALL SELECT 'Korpus Materialı', 'Plastik', 6
    UNION ALL SELECT 'Rəng', 'Ağ', 7
    UNION ALL SELECT 'Çəki', '5 kq', 8
    UNION ALL SELECT 'Zəmanət', '1 il', 9
    UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Listlər, təlimat kitabçası', 10
    UNION ALL SELECT 'Bölmələrin sayı', '10', 11
  ) x
  WHERE c.slug IN ('km-terevez-meyve-qurudan')
  ORDER BY p.id, x.sort_order;

  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = b.name
  WHERE ps.spec_key = 'Brend'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND c.slug IN ('km-terevez-meyve-qurudan')
    );

  UPDATE product_specs ps
  INNER JOIN products p ON p.id = ps.product_id
  INNER JOIN brands b ON b.id = p.brand_id
  SET ps.spec_value = CASE b.slug
    WHEN 'ardesto' THEN 'Çin'
    WHEN 'tefal' THEN 'Fransa'
    WHEN 'bosch' THEN 'Sloveniya'
    WHEN 'midea' THEN 'Çin'
    WHEN 'kenwood' THEN 'Çin'
    ELSE 'Çin'
  END
  WHERE ps.spec_key = 'İstehsalçı ölkə'
    AND EXISTS (
      SELECT 1
      FROM categories c
      WHERE c.id = p.category_id
        AND c.slug IN ('km-terevez-meyve-qurudan')
    );
