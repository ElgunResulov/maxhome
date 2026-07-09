-- Kateqoriya uzre saxlanilan elave ozellik sablonlari (admin mehsul formu)
-- Istifade: MySQL-de maxhome_db icinde bu skripti icra edin.

USE maxhome_db;

CREATE TABLE IF NOT EXISTS category_extra_spec_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 9000,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_category_extra_spec_label (category_id, label),
  CONSTRAINT fk_category_extra_spec_template_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_category_extra_spec_templates_category
  ON category_extra_spec_templates (category_id, sort_order);
