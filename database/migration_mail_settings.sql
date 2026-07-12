INSERT INTO settings (`key`, value)
VALUES
    ('mail_transport', 'smtp'),
    ('smtp_host', ''),
    ('smtp_port', '587'),
    ('smtp_encryption', 'tls'),
    ('smtp_username', ''),
    ('smtp_password', ''),
    ('mail_from_email', 'noreply@maxhome.az'),
    ('mail_from_name', 'MAXHOME')
ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP;
