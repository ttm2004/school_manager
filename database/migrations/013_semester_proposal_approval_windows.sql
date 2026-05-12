SET NAMES utf8mb4;

ALTER TABLE `semesters`
    ADD COLUMN IF NOT EXISTS `proposal_start` DATETIME NULL AFTER `end_date`,
    ADD COLUMN IF NOT EXISTS `proposal_end` DATETIME NULL AFTER `proposal_start`,
    ADD COLUMN IF NOT EXISTS `approval_start` DATETIME NULL AFTER `proposal_end`,
    ADD COLUMN IF NOT EXISTS `approval_end` DATETIME NULL AFTER `approval_start`;

UPDATE `semesters`
SET `proposal_end` = COALESCE(`proposal_end`, CONCAT(`proposal_deadline`, ' 23:59:59'))
WHERE `proposal_deadline` IS NOT NULL;

UPDATE `semesters`
SET `proposal_start` = COALESCE(`proposal_start`, DATE_SUB(`start_date`, INTERVAL 75 DAY)),
    `proposal_end` = COALESCE(`proposal_end`, DATE_SUB(`start_date`, INTERVAL 45 DAY)),
    `approval_start` = COALESCE(`approval_start`, DATE_SUB(`start_date`, INTERVAL 44 DAY)),
    `approval_end` = COALESCE(`approval_end`, DATE_SUB(`start_date`, INTERVAL 21 DAY))
WHERE `start_date` IS NOT NULL;

CREATE INDEX IF NOT EXISTS `idx_semesters_proposal_window` ON `semesters` (`proposal_start`, `proposal_end`);
CREATE INDEX IF NOT EXISTS `idx_semesters_approval_window` ON `semesters` (`approval_start`, `approval_end`);
