ALTER TABLE `semesters`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_semesters_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_semesters_demo_batch` (`demo_batch_id`);

ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_course_sections_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_course_sections_demo_batch` (`demo_batch_id`);

ALTER TABLE `final_exam_schedules`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_final_exam_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_final_exam_demo_batch` (`demo_batch_id`);

ALTER TABLE `grade_locks`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `note`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_grade_locks_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_grade_locks_demo_batch` (`demo_batch_id`);

ALTER TABLE `tuition_periods`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_tuition_periods_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_tuition_periods_demo_batch` (`demo_batch_id`);

CREATE TABLE IF NOT EXISTS `course_section_schedule_changes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_section_id` INT NOT NULL,
    `original_date` DATE NOT NULL,
    `new_date` DATE NOT NULL,
    `new_day_session` VARCHAR(30) NOT NULL,
    `room` VARCHAR(50) NULL,
    `reason` TEXT NULL,
    `approved_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_section_original` (`course_section_id`, `original_date`),
    INDEX `idx_section_new` (`course_section_id`, `new_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `course_section_schedule_changes`
    ADD COLUMN IF NOT EXISTS `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `reason`,
    ADD COLUMN IF NOT EXISTS `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`,
    ADD INDEX IF NOT EXISTS `idx_schedule_changes_data_mode` (`data_mode`),
    ADD INDEX IF NOT EXISTS `idx_schedule_changes_demo_batch` (`demo_batch_id`);

UPDATE `semesters`
SET data_mode = 'test',
    demo_batch_id = COALESCE(demo_batch_id, CONCAT('academic_demo_', id))
WHERE LOWER(semester_name) LIKE '%test%' OR LOWER(semester_name) LIKE '%demo%';

UPDATE `course_sections` cs
JOIN `semesters` sm ON sm.id = cs.semester_id
SET cs.data_mode = sm.data_mode,
    cs.demo_batch_id = sm.demo_batch_id
WHERE sm.data_mode = 'test';

UPDATE `final_exam_schedules` fes
JOIN `course_sections` cs ON cs.id = fes.course_section_id
SET fes.data_mode = cs.data_mode,
    fes.demo_batch_id = cs.demo_batch_id
WHERE cs.data_mode = 'test';

UPDATE `grade_locks` gl
JOIN `course_sections` cs ON cs.id = gl.course_section_id
SET gl.data_mode = cs.data_mode,
    gl.demo_batch_id = cs.demo_batch_id
WHERE cs.data_mode = 'test';

UPDATE `tuition_periods` tp
JOIN `semesters` sm ON sm.id = tp.semester_id
SET tp.data_mode = sm.data_mode,
    tp.demo_batch_id = sm.demo_batch_id
WHERE sm.data_mode = 'test';

UPDATE `tuition_invoices` ti
JOIN `course_sections` cs ON cs.semester_id = ti.semester_id
JOIN `student_subjects` ss ON ss.course_section_id = cs.id AND ss.student_id = ti.student_id
SET ti.data_mode = cs.data_mode,
    ti.demo_batch_id = cs.demo_batch_id
WHERE cs.data_mode = 'test';

UPDATE `tuition_payments` tp
JOIN `tuition_invoices` ti ON ti.id = tp.invoice_id
SET tp.data_mode = ti.data_mode,
    tp.demo_batch_id = ti.demo_batch_id
WHERE ti.data_mode = 'test';
