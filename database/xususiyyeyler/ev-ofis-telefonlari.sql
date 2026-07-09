USE maxhome_db;

-- Ev ve ofis telefonlari ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ct_brand', 'Brend', 'text', NULL, NULL, 1, 2601),
('ct_country', 'İstehsalçı ölkə', 'text', NULL, NULL, 1, 2602),
('ct_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 2603),
('ct_matrix_type', 'Matris tipi', 'text', NULL, NULL, 1, 2604),
('ct_display_size', 'Ekranın ölçüsü', 'text', NULL, NULL, 1, 2605),
('ct_color', 'Rəng', 'text', NULL, NULL, 1, 2606),
('ct_weight', 'Çəki', 'text', NULL, NULL, 1, 2607),
('ct_dimensions', 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'text', NULL, NULL, 1, 2608),
('ct_battery_capacity', 'Akkumulyatorun həcmi', 'text', NULL, NULL, 1, 2609),
('ct_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 2610),
('ct_included', 'Qablaşdırmaya daxildir', 'text', NULL, NULL, 1, 2611),
('ct_button_backlight', 'Düymələrin işıqlandırılması', 'text', NULL, NULL, 1, 2612),
('ct_speakerphone', 'Spikerfon', 'text', NULL, NULL, 1, 2613),
('ct_range', 'İşləmə radiusu (qapalı/açıq ərazi)', 'text', NULL, NULL, 1, 2614),
('ct_connected_handsets', 'Qoşulan dəstək sayı', 'text', NULL, NULL, 1, 2615),
('ct_phonebook', 'Telefon kitabçası', 'text', NULL, NULL, 1, 2616),
('ct_standby_talk_time', 'Dəstəyin işləmə vaxtı (danışıq/gözləmə)', 'text', NULL, NULL, 1, 2617),
('ct_caller_id', 'Nömrə təyinedici', 'text', NULL, NULL, 1, 2618),
('ct_auto_answer', 'Avto-cavab', 'text', NULL, NULL, 1, 2619),
('ct_radio_nanny', 'Radio dayə funksiyası', 'text', NULL, NULL, 1, 2620)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Kateqoriyalara map et
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ct_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ct_country', 2
  UNION ALL SELECT 'ct_product_type', 3
  UNION ALL SELECT 'ct_matrix_type', 4
  UNION ALL SELECT 'ct_display_size', 5
  UNION ALL SELECT 'ct_color', 6
  UNION ALL SELECT 'ct_weight', 7
  UNION ALL SELECT 'ct_dimensions', 8
  UNION ALL SELECT 'ct_battery_capacity', 9
  UNION ALL SELECT 'ct_warranty', 10
  UNION ALL SELECT 'ct_included', 11
  UNION ALL SELECT 'ct_button_backlight', 12
  UNION ALL SELECT 'ct_speakerphone', 13
  UNION ALL SELECT 'ct_range', 14
  UNION ALL SELECT 'ct_connected_handsets', 15
  UNION ALL SELECT 'ct_phonebook', 16
  UNION ALL SELECT 'ct_standby_talk_time', 17
  UNION ALL SELECT 'ct_caller_id', 18
  UNION ALL SELECT 'ct_auto_answer', 19
  UNION ALL SELECT 'ct_radio_nanny', 20
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON c.slug IN ('eo-ev-ofis-telefonlari', 'eo-gigaset')
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('eo-ev-ofis-telefonlari', 'eo-gigaset');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'İstehsalçı ölkə', '{COUNTRY}', 2
  UNION ALL SELECT 'Məhsul tipi', 'Dect', 3
  UNION ALL SELECT 'Matris tipi', 'Ağ-qara (Monoxrom)', 4
  UNION ALL SELECT 'Ekranın ölçüsü', '1.4"', 5
  UNION ALL SELECT 'Rəng', 'Metal', 6
  UNION ALL SELECT 'Çəki', 'Baza 110 qr, Dəstək 105 qr', 7
  UNION ALL SELECT 'Ölçülər: Hündürlüyü / Eni / Dərinliyi', 'Baza - 50*96*120, Dəstək - 158', 8
  UNION ALL SELECT 'Akkumulyatorun həcmi', '2 x NiMH AAA rechargeable', 9
  UNION ALL SELECT 'Zəmanət', '5 il', 10
  UNION ALL SELECT 'Qablaşdırmaya daxildir', 'Baza, Dəstək, Oturacaq, Adapter', 11
  UNION ALL SELECT 'Düymələrin işıqlandırılması', 'yox', 12
  UNION ALL SELECT 'Spikerfon', 'var', 13
  UNION ALL SELECT 'İşləmə radiusu (qapalı/açıq ərazi)', '50/300', 14
  UNION ALL SELECT 'Qoşulan dəstək sayı', '4', 15
  UNION ALL SELECT 'Telefon kitabçası', '50 nömrə', 16
  UNION ALL SELECT 'Dəstəyin işləmə vaxtı (danışıq/gözləmə)', '18/170 saat', 17
  UNION ALL SELECT 'Nömrə təyinedici', 'var', 18
  UNION ALL SELECT 'Avto-cavab', 'yox', 19
  UNION ALL SELECT 'Radio dayə funksiyası', 'yox', 20
) x
WHERE c.slug IN ('eo-ev-ofis-telefonlari', 'eo-gigaset')
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
      AND c.slug IN ('eo-ev-ofis-telefonlari', 'eo-gigaset')
  );

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = CASE b.slug
  WHEN 'panasonic' THEN 'Malayziya'
  WHEN 'gigaset' THEN 'Almaniya'
  ELSE 'Çin'
END
WHERE ps.spec_key = 'İstehsalçı ölkə'
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND c.slug IN ('eo-ev-ofis-telefonlari', 'eo-gigaset')
  );
