  -- Köhnə maxhome_db şeması üçün: categories.cədvəlinə brand_id əlavə edir.
  -- Əgər "Duplicate column" və ya "Duplicate key name" alırsınızsa, addım artıq tətbiq olunub deməkdir.
  -- Yeni quraşdırmada yalnız maxhome_schema.sql işlədin.
  USE maxhome_db;

  ALTER TABLE categories
    ADD COLUMN brand_id BIGINT UNSIGNED NULL AFTER parent_id;

  ALTER TABLE categories
    ADD CONSTRAINT fk_categories_brand    FOREIGN KEY (brand_id) REFERENCES brands(id)
      ON DELETE SET NULL;

  CREATE INDEX idx_categories_brand ON categories(brand_id);
