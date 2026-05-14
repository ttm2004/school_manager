ALTER TABLE `grades`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `note`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_grades_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_grades_demo_batch` (`demo_batch_id`);

ALTER TABLE `student_evaluations`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `comment`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_eval_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_eval_demo_batch` (`demo_batch_id`);

ALTER TABLE `student_extra_comments`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `comment`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_extra_comments_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_extra_comments_demo_batch` (`demo_batch_id`);

ALTER TABLE `student_warnings`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `note`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_warnings_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_warnings_demo_batch` (`demo_batch_id`);

ALTER TABLE `tuition_invoices`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_tuition_invoices_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_tuition_invoices_demo_batch` (`demo_batch_id`);

ALTER TABLE `tuition_payments`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `note`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_tuition_payments_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_tuition_payments_demo_batch` (`demo_batch_id`);

UPDATE `grades` g
JOIN `student_subjects` ss ON ss.id = g.student_subject_id
SET g.data_mode = ss.data_mode,
    g.demo_batch_id = ss.demo_batch_id
WHERE ss.data_mode = 'test';

UPDATE `student_evaluations` se
JOIN `students` s ON s.id = se.student_id
SET se.data_mode = s.data_mode,
    se.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';

UPDATE `student_extra_comments` sec
JOIN `students` s ON s.id = sec.student_id
SET sec.data_mode = s.data_mode,
    sec.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';

UPDATE `student_warnings` sw
JOIN `students` s ON s.id = sw.student_id
SET sw.data_mode = s.data_mode,
    sw.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';

UPDATE `tuition_invoices` ti
JOIN `students` s ON s.id = ti.student_id
SET ti.data_mode = s.data_mode,
    ti.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';

UPDATE `tuition_payments` tp
JOIN `tuition_invoices` ti ON ti.id = tp.invoice_id
SET tp.data_mode = ti.data_mode,
    tp.demo_batch_id = ti.demo_batch_id
WHERE ti.data_mode = 'test';
