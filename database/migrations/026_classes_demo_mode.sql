ALTER TABLE `classes`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `cohort_id`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_classes_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_classes_mode_major_year` (`data_mode`, `major_id`, `enrollment_year`);

UPDATE `classes`
SET `data_mode` = 'system'
WHERE `data_mode` IS NULL OR `data_mode` = '';
