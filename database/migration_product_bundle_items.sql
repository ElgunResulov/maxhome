-- Ekosistem / kampaniya: mehsul sehifesinde admin terefinden secilen elave aktiv mehsullar
-- Istifade: MySQL-de maxhome_db icinde bu skripti icra edin.

USE maxhome_db;

CREATE TABLE IF NOT EXISTS product_bundle_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_product_id BIGINT UNSIGNED NOT NULL,
  bundle_product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parent_bundle (parent_product_id, bundle_product_id),
  CONSTRAINT fk_bundle_parent_product
    FOREIGN KEY (parent_product_id) REFERENCES products(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_bundle_child_product
    FOREIGN KEY (bundle_product_id) REFERENCES products(id)
    ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_bundle_parent ON product_bundle_items (parent_product_id, sort_order);
