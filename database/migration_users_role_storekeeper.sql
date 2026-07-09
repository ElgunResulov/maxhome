USE maxhome_db;

SET @role_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
);

SET @sql = IF(
    @role_column_exists = 1,
    "ALTER TABLE users MODIFY COLUMN role ENUM('customer','seller','storekeeper','admin') NOT NULL DEFAULT 'customer'",
    "SELECT 'users.role column is missing; run migration_users_role.sql first' AS message"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

