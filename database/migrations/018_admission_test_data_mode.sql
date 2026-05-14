ALTER TABLE `admission_applications`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `round_id`,
    ADD COLUMN IF NOT EXISTS `import_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD COLUMN IF NOT EXISTS `imported_by` INT NULL AFTER `import_batch_id`,
    ADD COLUMN IF NOT EXISTS `imported_at` DATETIME NULL AFTER `imported_by`,
    ADD INDEX IF NOT EXISTS `idx_adm_app_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_adm_app_import_batch` (`import_batch_id`);
