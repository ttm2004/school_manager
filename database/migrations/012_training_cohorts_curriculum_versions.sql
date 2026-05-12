SET NAMES utf8mb4;

ALTER TABLE `training_programs`
    ADD COLUMN IF NOT EXISTS `version_code` VARCHAR(50) NULL AFTER `program_name`,
    ADD COLUMN IF NOT EXISTS `effective_year` INT NULL AFTER `version_code`,
    ADD COLUMN IF NOT EXISTS `duration_years` DECIMAL(3,1) NOT NULL DEFAULT 4.0 AFTER `total_credits`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'active' AFTER `duration_years`;

UPDATE `training_programs`
SET `version_code` = COALESCE(`version_code`, `school_year`, CONCAT('CTDT-', `id`)),
    `effective_year` = COALESCE(`effective_year`, CAST(SUBSTRING(COALESCE(`school_year`, YEAR(CURDATE())), 1, 4) AS UNSIGNED))
WHERE `version_code` IS NULL OR `effective_year` IS NULL;

INSERT INTO `training_programs` (`major_id`, `program_name`, `version_code`, `effective_year`, `school_year`, `total_credits`, `duration_years`, `status`, `note`)
SELECT m.id,
       CONCAT('CTĐT ', m.major_name, ' ', y.enrollment_year),
       CONCAT('CTDT-', m.major_code, '-', y.enrollment_year),
       y.enrollment_year,
       CONCAT(y.enrollment_year, '-', y.enrollment_year + 1),
       COALESCE(m.total_credits, 120),
       4.0,
       'active',
       'Tạo tự động từ dữ liệu lớp/sinh viên hiện có'
FROM majors m
JOIN (
    SELECT DISTINCT enrollment_year FROM classes WHERE enrollment_year IS NOT NULL
    UNION
    SELECT DISTINCT enrollment_year FROM students WHERE enrollment_year IS NOT NULL
) y
WHERE NOT EXISTS (
    SELECT 1 FROM training_programs tp
    WHERE tp.major_id = m.id AND tp.effective_year = y.enrollment_year
);

CREATE TABLE IF NOT EXISTS `training_cohorts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `major_id` INT NOT NULL,
    `enrollment_year` INT NOT NULL,
    `program_id` INT NOT NULL,
    `cohort_code` VARCHAR(50) NOT NULL,
    `cohort_name` VARCHAR(150) NOT NULL,
    `duration_years` DECIMAL(3,1) NOT NULL DEFAULT 4.0,
    `start_date` DATE NULL,
    `expected_end_date` DATE NULL,
    `status` ENUM('planned','active','completed','closed') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_training_cohort_major_year` (`major_id`, `enrollment_year`),
    KEY `idx_training_cohort_program` (`program_id`),
    KEY `idx_training_cohort_status` (`status`),
    CONSTRAINT `fk_training_cohort_major` FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_training_cohort_program` FOREIGN KEY (`program_id`) REFERENCES `training_programs`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `training_cohorts` (`major_id`, `enrollment_year`, `program_id`, `cohort_code`, `cohort_name`, `duration_years`, `start_date`, `expected_end_date`, `status`)
SELECT m.id,
       y.enrollment_year,
       (
           SELECT tp.id
           FROM training_programs tp
           WHERE tp.major_id = m.id AND tp.effective_year <= y.enrollment_year
           ORDER BY tp.effective_year DESC, tp.id DESC
           LIMIT 1
       ) AS program_id,
       CONCAT(m.major_code, '-', y.enrollment_year),
       CONCAT(m.major_name, ' khóa ', y.enrollment_year, '-', y.enrollment_year + 4),
       4.0,
       CONCAT(y.enrollment_year, '-08-15'),
       CONCAT(y.enrollment_year + 4, '-08-14'),
       'active'
FROM majors m
JOIN (
    SELECT DISTINCT enrollment_year FROM classes WHERE enrollment_year IS NOT NULL
    UNION
    SELECT DISTINCT enrollment_year FROM students WHERE enrollment_year IS NOT NULL
) y
WHERE NOT EXISTS (
    SELECT 1 FROM training_cohorts tc
    WHERE tc.major_id = m.id AND tc.enrollment_year = y.enrollment_year
);

ALTER TABLE `classes`
    ADD COLUMN IF NOT EXISTS `cohort_id` INT NULL AFTER `enrollment_year`;

ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `cohort_id` INT NULL AFTER `enrollment_year`,
    ADD COLUMN IF NOT EXISTS `training_program_id` INT NULL AFTER `cohort_id`;

ALTER TABLE `curriculum`
    ADD COLUMN IF NOT EXISTS `program_id` INT NULL AFTER `major_id`,
    ADD COLUMN IF NOT EXISTS `allow_off_semester` TINYINT(1) NOT NULL DEFAULT 0 AFTER `prerequisite_ids`;

ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `target_cohort_id` INT NULL AFTER `semester_id`,
    ADD COLUMN IF NOT EXISTS `allow_off_semester` TINYINT(1) NOT NULL DEFAULT 0 AFTER `min_students`,
    ADD COLUMN IF NOT EXISTS `off_semester_reason` TEXT NULL AFTER `allow_off_semester`;

UPDATE classes cl
JOIN training_cohorts tc ON tc.major_id = cl.major_id AND tc.enrollment_year = cl.enrollment_year
SET cl.cohort_id = tc.id
WHERE cl.cohort_id IS NULL;

UPDATE students s
JOIN classes cl ON s.class_id = cl.id
LEFT JOIN training_cohorts tc ON tc.id = cl.cohort_id
SET s.enrollment_year = COALESCE(s.enrollment_year, cl.enrollment_year),
    s.cohort_id = COALESCE(s.cohort_id, tc.id),
    s.training_program_id = COALESCE(s.training_program_id, tc.program_id),
    s.expected_grad_year = COALESCE(s.expected_grad_year, tc.enrollment_year + CEIL(tc.duration_years))
WHERE tc.id IS NOT NULL;

-- CTDT hiện có được xem là khung dùng chung cho các khóa.
-- Khi cần quản lý từng phiên bản thật, tạo bản ghi curriculum riêng và gán program_id cụ thể.
UPDATE curriculum SET program_id = NULL;

CREATE INDEX IF NOT EXISTS `idx_classes_cohort` ON `classes` (`cohort_id`);
CREATE INDEX IF NOT EXISTS `idx_students_cohort` ON `students` (`cohort_id`);
CREATE INDEX IF NOT EXISTS `idx_students_program` ON `students` (`training_program_id`);
CREATE INDEX IF NOT EXISTS `idx_curriculum_program` ON `curriculum` (`program_id`);
CREATE INDEX IF NOT EXISTS `idx_course_sections_cohort` ON `course_sections` (`target_cohort_id`);
