# Idempotent on purpose: no DROP TABLE. The registry and the published versions are
# evidence and must survive a reinstallation, a module:schema:apply or a replay.
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ccm_draft_setting`
(
    `id` INTEGER NOT NULL,
    `settings` LONGTEXT NOT NULL,
    `base_version` INTEGER,
    `updated_by` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ccm_service_config`
(
    `service_key` VARCHAR(64) NOT NULL,
    `origin` VARCHAR(16) NOT NULL,
    `kind` VARCHAR(16) NOT NULL,
    `purpose` VARCHAR(64) NOT NULL,
    `enabled` INTEGER DEFAULT 0 NOT NULL,
    `proposed` INTEGER DEFAULT 0 NOT NULL,
    `strictly_necessary` INTEGER DEFAULT 0 NOT NULL,
    `parameters` LONGTEXT,
    `details` LONGTEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`service_key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ccm_version`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `version` INTEGER NOT NULL,
    `snapshot` LONGTEXT NOT NULL,
    `config_hash` CHAR(64) NOT NULL,
    `js_hash` CHAR(64) NOT NULL,
    `served_hash` CHAR(12) NOT NULL,
    `module_version` VARCHAR(32) NOT NULL,
    `author` VARCHAR(255),
    `substantial` INTEGER DEFAULT 0 NOT NULL,
    `comment` VARCHAR(255),
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ccm_version_unique` (`version`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ccm_consent_entry`
(
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `consent_id` CHAR(36) NOT NULL,
    `recorded_at` DATETIME NOT NULL,
    `action` VARCHAR(32) NOT NULL,
    `source` VARCHAR(16) NOT NULL,
    `version` INTEGER NOT NULL,
    `version_hash` CHAR(12) NOT NULL,
    `locale` VARCHAR(10) NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `browser` VARCHAR(64),
    `purposes_json` TEXT NOT NULL,
    `services_json` TEXT NOT NULL,
    `exempt_json` TEXT,
    `ip_truncated` VARCHAR(45),
    `customer_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `idx_ccm_consent_entry_consent_id` (`consent_id`),
    INDEX `idx_ccm_consent_entry_recorded_at` (`recorded_at`),
    INDEX `idx_ccm_consent_entry_action_recorded_at` (`action`, `recorded_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ccm_daily_stat`
(
    `day` DATE NOT NULL,
    `metric` VARCHAR(80) NOT NULL,
    `total` INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (`day`,`metric`)
) ENGINE=InnoDB;

# This file is also read by Thelia as the source of the module tables; keep the
# statement below so that the foreign key checks are back on afterwards.
SET FOREIGN_KEY_CHECKS = 1;
