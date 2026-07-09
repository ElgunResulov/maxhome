USE maxhome_db;

-- Inqolyatorlar (Inhalyatorlar) ucun konkret xususiyyet seti
INSERT INTO spec_definitions (spec_key, label, input_type, unit, options_json, is_active, sort_order)
VALUES
('ih_brand', 'Brend', 'text', NULL, NULL, 1, 6001),
('ih_manufacturer', 'Marka', 'text', NULL, NULL, 1, 6002),
('ih_product_type', 'Məhsul tipi', 'text', NULL, NULL, 1, 6003),
('ih_size', 'Ölçü', 'text', NULL, NULL, 1, 6004),
('ih_weight', 'Çəki', 'text', NULL, NULL, 1, 6005),
('ih_warranty', 'Zəmanət', 'text', NULL, NULL, 1, 6006)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  input_type = VALUES(input_type),
  unit = VALUES(unit),
  options_json = VALUES(options_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Inqolyator kateqoriyalarina map et (slug + ad fallback)
INSERT INTO category_spec_map (category_id, spec_definition_id, is_required, sort_order)
SELECT c.id, s.id, 1 AS is_required, x.sort_order
FROM (
  SELECT 'ih_brand' AS spec_key, 1 AS sort_order
  UNION ALL SELECT 'ih_manufacturer', 2
  UNION ALL SELECT 'ih_product_type', 3
  UNION ALL SELECT 'ih_size', 4
  UNION ALL SELECT 'ih_weight', 5
  UNION ALL SELECT 'ih_warranty', 6
) AS x
INNER JOIN spec_definitions s ON s.spec_key = x.spec_key
INNER JOIN categories c ON (
  c.slug IN ('tb-inhalyator')
  OR c.name IN ('İnhalyatorlar', 'İnqolyatorlar', 'Inhaler')
)
ON DUPLICATE KEY UPDATE
  is_required = VALUES(is_required),
  sort_order = VALUES(sort_order);

-- Inqolyator mehsullari ucun default product_specs
DELETE ps
FROM product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE c.slug IN ('tb-inhalyator')
   OR c.name IN ('İnhalyatorlar', 'İnqolyatorlar', 'Inhaler');

INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order)
SELECT p.id, x.spec_key, x.spec_value, x.sort_order
FROM products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT 'Brend' AS spec_key, '{BRAND}' AS spec_value, 1 AS sort_order
  UNION ALL SELECT 'Marka', '{BRAND}', 2
  UNION ALL SELECT 'Məhsul tipi', 'İnqolyator', 3
  UNION ALL SELECT 'Ölçü', '117/69/46', 4
  UNION ALL SELECT 'Çəki', '180g', 5
  UNION ALL SELECT 'Zəmanət', '1 il', 6
) x
WHERE c.slug IN ('tb-inhalyator')
   OR c.name IN ('İnhalyatorlar', 'İnqolyatorlar', 'Inhaler')
ORDER BY p.id, x.sort_order;

UPDATE product_specs ps
INNER JOIN products p ON p.id = ps.product_id
INNER JOIN brands b ON b.id = p.brand_id
SET ps.spec_value = b.name
WHERE ps.spec_key IN ('Brend', 'Marka')
  AND EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.id = p.category_id
      AND (
        c.slug IN ('tb-inhalyator')
        OR c.name IN ('İnhalyatorlar', 'İnqolyatorlar', 'Inhaler')
      )
  );
