-- Settings table for admin-configurable values (e.g., WhatsApp order routing).
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(190) NOT NULL,
  value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default WhatsApp number (used by checkout redirect). Keep digits only.
INSERT INTO settings (`key`, value)
VALUES ('orders_whatsapp_number', '994559375801')
ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP;

