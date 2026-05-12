SET NAMES utf8mb4;

ALTER TABLE `subjects`
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`;

ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `min_students` INT NOT NULL DEFAULT 20 AFTER `expected_students`;

UPDATE `subjects`
SET `is_active` = 1
WHERE `is_active` IS NULL;

UPDATE `course_sections`
SET `min_students` = 20
WHERE `min_students` IS NULL OR `min_students` <= 0;

CREATE INDEX IF NOT EXISTS `idx_subjects_is_active` ON `subjects` (`is_active`);
CREATE INDEX IF NOT EXISTS `idx_cs_schedule_teacher` ON `course_sections` (`semester_id`, `teacher_id`, `day_sessions`);
CREATE INDEX IF NOT EXISTS `idx_cs_schedule_room` ON `course_sections` (`semester_id`, `room`, `day_sessions`);
