ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `training_program_id`,
    ADD INDEX IF NOT EXISTS `idx_students_data_mode` (`data_mode`);

UPDATE `students` s
JOIN `users` u ON u.id = s.user_id
JOIN `admission_applications` aa ON aa.email = u.email
SET s.data_mode = aa.data_mode
WHERE aa.data_mode = 'test';
