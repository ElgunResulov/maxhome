USE maxhome_db;

SET @role_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
);

SET @sql = IF(
    @role_column_exists = 0,
    "ALTER TABLE users ADD COLUMN role ENUM('customer','seller','admin') NOT NULL DEFAULT 'customer' AFTER password_hash",
    "SELECT 'users.role already exists' AS message"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
