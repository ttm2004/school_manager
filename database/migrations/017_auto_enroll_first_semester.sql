ALTER TABLE `student_subjects`
    MODIFY COLUMN `status` ENUM('registered','cancelled','completed','auto_enrolled') DEFAULT 'registered';

CREATE TABLE IF NOT EXISTS `pending_enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `cohort_id` INT NULL,
    `program_id` INT NULL,
    `reason` VARCHAR(255) DEFAULT 'Khong tim duoc lop HP phu hop',
    `status` ENUM('pending','resolved','ignored') DEFAULT 'pending',
    `note` TEXT NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pending_student_subject_semester` (`student_id`, `subject_id`, `semester_id`),
    INDEX `idx_pending_status` (`status`),
    INDEX `idx_pending_semester` (`semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
