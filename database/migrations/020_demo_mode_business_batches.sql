ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_students_demo_batch` (`demo_batch_id`);

ALTER TABLE `student_subjects`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_student_subjects_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_student_subjects_demo_batch` (`demo_batch_id`);

ALTER TABLE `pending_enrollments`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_pending_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_pending_demo_batch` (`demo_batch_id`);

UPDATE `students` s
JOIN `users` u ON u.id = s.user_id
JOIN `admission_applications` aa ON aa.email = u.email
SET s.demo_batch_id = aa.import_batch_id
WHERE s.data_mode = 'test' AND s.demo_batch_id IS NULL;

UPDATE `student_subjects` ss
JOIN `students` s ON s.id = ss.student_id
SET ss.data_mode = s.data_mode, ss.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';

UPDATE `pending_enrollments` pe
JOIN `students` s ON s.id = pe.student_id
SET pe.data_mode = s.data_mode, pe.demo_batch_id = s.demo_batch_id
WHERE s.data_mode = 'test';
