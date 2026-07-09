-- Kateqoriya ↔ brend əlaqələri (mağaza filtri və admin panel).
-- Təkrar işlədilə bilər: cədvəl/indeks artıq varsa xəta vermir.
-- #1061 Duplicate key / #1050 Table exists = artıq tətbiq olunub, davam edin.
USE maxhome_db;

CREATE TABLE IF NOT EXISTS category_brands (
  category_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (category_id, brand_id),
  CONSTRAINT fk_category_brands_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_category_brands_brand
    FOREIGN KEY (brand_id) REFERENCES brands(id)
    ON DELETE CASCADE
);

SET @idx_category_brands_brand = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'category_brands'
    AND index_name = 'idx_category_brands_brand'
);
SET @sql_idx_cb = IF(
  @idx_category_brands_brand = 0,
  'CREATE INDEX idx_category_brands_brand ON category_brands(brand_id)',
  'SELECT 1'
);
PREPARE stmt_idx_cb FROM @sql_idx_cb;
EXECUTE stmt_idx_cb;
DEALLOCATE PREPARE stmt_idx_cb;

-- Mövcud categories.brand_id yarpaqlarından avtomatik köçürmə (təkrarlanmır).
INSERT IGNORE INTO category_brands (category_id, brand_id)
SELECT c.id, c.brand_id
FROM categories c
WHERE c.brand_id IS NOT NULL;
