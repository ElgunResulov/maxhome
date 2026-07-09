USE maxhome_db;

CREATE TABLE IF NOT EXISTS homepage_slides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    badge_text VARCHAR(80) NULL,
    title VARCHAR(160) NOT NULL,
    body_text VARCHAR(420) NULL,
    primary_label VARCHAR(60) NULL,
    primary_url VARCHAR(255) NULL,
    secondary_label VARCHAR(60) NULL,
    secondary_url VARCHAR(255) NULL,
    image_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_homepage_slides_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

