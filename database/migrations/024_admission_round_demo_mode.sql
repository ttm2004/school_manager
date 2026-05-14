ALTER TABLE `admission_rounds`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_admission_rounds_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_admission_rounds_mode_status` (`data_mode`, `status`);

DROP INDEX IF EXISTS `year_unique` ON `admission_rounds`;
CREATE UNIQUE INDEX IF NOT EXISTS `year_mode_unique` ON `admission_rounds` (`year`, `data_mode`);

UPDATE `admission_rounds`
SET `data_mode` = 'system'
WHERE `data_mode` IS NULL OR `data_mode` = '';

UPDATE `admission_rounds`
SET `demo_batch_id` = CONCAT('admission_round_demo_', id)
WHERE `data_mode` = 'test' AND (`demo_batch_id` IS NULL OR `demo_batch_id` = '');

INSERT INTO `admission_rounds`
    (`year`, `name`, `reg_start`, `reg_end`, `review_start`, `review_end`, `enroll_deadline`,
     `supp_reg_start`, `supp_reg_end`, `supp_review_end`, `supp_enroll_deadline`, `supp_score_bonus`,
     `status`, `data_mode`, `demo_batch_id`, `notes`, `created_by`)
SELECT
    `year`, CONCAT(`name`, ' - Demo/Test'), `reg_start`, `reg_end`, `review_start`, `review_end`, `enroll_deadline`,
    `supp_reg_start`, `supp_reg_end`, `supp_review_end`, `supp_enroll_deadline`, `supp_score_bonus`,
    `status`, 'test', CONCAT('admission_round_demo_seed_', id), CONCAT(COALESCE(`notes`, ''), '\nBản sao dùng cho Demo/Test.'),
    `created_by`
FROM `admission_rounds`
WHERE `data_mode` = 'system'
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM `admission_rounds` WHERE `data_mode` = 'test' LIMIT 1) AS existing_test)
ORDER BY `year` DESC, `id` DESC
LIMIT 1;
