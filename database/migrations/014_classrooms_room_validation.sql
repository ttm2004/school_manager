SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `classrooms` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `room_code` VARCHAR(50) NOT NULL,
    `room_name` VARCHAR(150) NULL,
    `building` VARCHAR(100) NULL,
    `room_type` ENUM('theory','lab','computer_lab','online','other') NOT NULL DEFAULT 'theory',
    `capacity` INT NOT NULL DEFAULT 40,
    `status` ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    `note` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_classrooms_code` (`room_code`),
    KEY `idx_classrooms_type_status` (`room_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `classroom_id` INT NULL AFTER `room`;

INSERT IGNORE INTO `classrooms` (`room_code`, `room_name`, `building`, `room_type`, `capacity`, `status`) VALUES
('A101', 'Phòng học A101', 'A', 'theory', 60, 'active'),
('A102', 'Phòng học A102', 'A', 'theory', 60, 'active'),
('B201', 'Phòng học B201', 'B', 'theory', 80, 'active'),
('LAB01', 'Phòng thực hành LAB01', 'Lab', 'lab', 40, 'active'),
('PM01', 'Phòng máy PM01', 'Lab', 'computer_lab', 45, 'active'),
('ONLINE', 'Lớp học trực tuyến', NULL, 'online', 999, 'active');

UPDATE course_sections cs
JOIN classrooms cr ON cr.room_code = cs.room
SET cs.classroom_id = cr.id
WHERE cs.classroom_id IS NULL AND cs.room IS NOT NULL AND cs.room <> '';

CREATE INDEX IF NOT EXISTS `idx_course_sections_classroom` ON `course_sections` (`classroom_id`);
