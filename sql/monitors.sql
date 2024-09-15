CREATE TABLE IF NOT EXISTS `monitors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `url` varchar(255) NOT NULL,
    `check_interval` int(11) NOT NULL DEFAULT 300,
    `expected_status_code` int(11) NOT NULL DEFAULT 200,
    `expected_keyword` varchar(255) DEFAULT NULL,
    `last_check_time` timestamp NULL DEFAULT NULL,
    `last_status` enum('up', 'down') DEFAULT NULL,
    `last_response_time` float DEFAULT NULL,
    `last_status_code` int(11) DEFAULT NULL,
    `last_error` text,
    `last_alert_time` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_last_check_time` (`last_check_time`),
    INDEX `idx_last_status` (`last_status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE
    `monitors` COMMENT 'Stores information about monitored websites and their status';