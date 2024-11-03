CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(255) NOT NULL,
    `setting_value` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    `settings` (`setting_key`, `setting_value`)
VALUES
    ('timezone', 'UTC'),
    ('date_format', 'Y-m-d'),
    ('time_format', 'H:i:s'),
    ('check_interval', '300'),
    ('notification_email', ''),
    ('installed', 'false'),
    ('smtp_host', ''),
    ('smtp_port', ''),
    ('smtp_user', ''),
    ('smtp_pass', ''),
    ('telegram_bot_token', ''),
    ('telegram_default_chat_id', '') ON DUPLICATE KEY
UPDATE
    `setting_value` =
VALUES
(`setting_value`);

CREATE INDEX idx_settings_updated_at ON `settings` (`updated_at`);

ALTER TABLE
    `settings` COMMENT 'Stores application-wide settings';