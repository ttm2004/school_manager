SET NAMES utf8mb4;

ALTER TABLE `classes`
    ADD COLUMN IF NOT EXISTS `enrollment_year` INT NULL AFTER `school_year`;

ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `enrollment_year` INT NULL AFTER `class_id`;

UPDATE `classes`
SET `enrollment_year` = CAST(SUBSTRING(`school_year`, 1, 4) AS UNSIGNED)
WHERE (`enrollment_year` IS NULL OR `enrollment_year` = 0)
  AND `school_year` REGEXP '^[0-9]{4}';

UPDATE `students` s
JOIN `classes` c ON s.`class_id` = c.`id`
SET s.`enrollment_year` = c.`enrollment_year`
WHERE (s.`enrollment_year` IS NULL OR s.`enrollment_year` = 0)
  AND c.`enrollment_year` IS NOT NULL;

UPDATE `semesters`
SET `start_date` = CONCAT(SUBSTRING(`school_year`, 1, 4), '-08-15'),
    `end_date` = CONCAT(SUBSTRING(`school_year`, 1, 4), '-12-31')
WHERE `semester_name` REGEXP '1'
  AND `school_year` REGEXP '^[0-9]{4}';

UPDATE `semesters`
SET `start_date` = CONCAT(CAST(SUBSTRING(`school_year`, 1, 4) AS UNSIGNED) + 1, '-01-01'),
    `end_date` = CONCAT(CAST(SUBSTRING(`school_year`, 1, 4) AS UNSIGNED) + 1, '-05-31')
WHERE `semester_name` REGEXP '2'
  AND `school_year` REGEXP '^[0-9]{4}';

UPDATE `semesters`
SET `start_date` = CONCAT(CAST(SUBSTRING(`school_year`, 1, 4) AS UNSIGNED) + 1, '-06-01'),
    `end_date` = CONCAT(CAST(SUBSTRING(`school_year`, 1, 4) AS UNSIGNED) + 1, '-08-14')
WHERE (`semester_name` REGEXP '3'
       OR LOWER(`semester_name`) LIKE '%hè%'
       OR LOWER(`semester_name`) LIKE '%he%')
  AND `school_year` REGEXP '^[0-9]{4}';

CREATE INDEX IF NOT EXISTS `idx_classes_enrollment_year` ON `classes` (`enrollment_year`);
CREATE INDEX IF NOT EXISTS `idx_students_enrollment_year` ON `students` (`enrollment_year`);
