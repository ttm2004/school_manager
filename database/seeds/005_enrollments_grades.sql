-- ============================================================
-- Seed: Đăng ký môn + Điểm số
-- Đủ workflow: đã có điểm, chưa có điểm, điểm thấp (cảnh báo)
-- ============================================================
SET NAMES utf8mb4;

-- ── Đăng ký môn HK1 2025-2026 ────────────────────────────────
-- SV id=1 (sv001) đăng ký CNTT101_01, CNTT201_01, CNTT203_01
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(1, 100, 'registered'),
(1, 102, 'registered'),
(1, 103, 'registered'),
(1, 110, 'registered');

-- SV id=2 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(2, 100, 'registered'),
(2, 102, 'registered'),
(2, 112, 'registered');

-- SV id=3 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(3, 101, 'registered'),
(3, 103, 'registered'),
(3, 110, 'registered'),
(3, 113, 'registered');

-- SV id=4 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(4, 100, 'registered'),
(4, 102, 'registered'),
(4, 104, 'registered');

-- SV id=5 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(5, 101, 'registered'),
(5, 111, 'registered'),
(5, 112, 'registered');

-- SV id=6 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(6, 100, 'registered'),
(6, 110, 'registered'),
(6, 113, 'registered');

-- SV id=7 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(7, 101, 'registered'),
(7, 102, 'registered'),
(7, 111, 'registered');

-- SV id=8 đăng ký
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(8, 100, 'registered'),
(8, 103, 'registered'),
(8, 104, 'registered');

-- ── Đăng ký HK2 (đã đóng) ────────────────────────────────────
INSERT IGNORE INTO `student_subjects` (`student_id`,`course_section_id`,`status`) VALUES
(1, 130, 'registered'),
(1, 131, 'registered'),
(2, 130, 'registered'),
(3, 132, 'registered'),
(4, 130, 'registered'),
(5, 132, 'registered');

-- ── Điểm HK2 (đã có điểm đầy đủ) ────────────────────────────
-- Hàm tính: total = process*0.2 + midterm*0.3 + final*0.5
-- SV1 - CNTT102
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 8.0, 7.5, 8.5, ROUND(8.0*0.2+7.5*0.3+8.5*0.5,2), 'B+'
FROM student_subjects ss WHERE ss.student_id=1 AND ss.course_section_id=130;

-- SV1 - CNTT202
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 9.0, 8.5, 9.0, ROUND(9.0*0.2+8.5*0.3+9.0*0.5,2), 'A'
FROM student_subjects ss WHERE ss.student_id=1 AND ss.course_section_id=131;

-- SV2 - CNTT102
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 6.0, 5.5, 6.0, ROUND(6.0*0.2+5.5*0.3+6.0*0.5,2), 'C+'
FROM student_subjects ss WHERE ss.student_id=2 AND ss.course_section_id=130;

-- SV3 - KETO011 (điểm thấp → cảnh báo)
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 3.5, 3.0, 3.5, ROUND(3.5*0.2+3.0*0.3+3.5*0.5,2), 'F'
FROM student_subjects ss WHERE ss.student_id=3 AND ss.course_section_id=132;

-- SV4 - CNTT102
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 7.0, 6.5, 7.0, ROUND(7.0*0.2+6.5*0.3+7.0*0.5,2), 'B'
FROM student_subjects ss WHERE ss.student_id=4 AND ss.course_section_id=130;

-- SV5 - KETO011 (điểm thấp)
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 4.0, 3.5, 4.0, ROUND(4.0*0.2+3.5*0.3+4.0*0.5,2), 'D+'
FROM student_subjects ss WHERE ss.student_id=5 AND ss.course_section_id=132;

-- ── Điểm HK1 (một số lớp đã có điểm, một số chưa) ────────────
-- SV1 - CNTT101_01 (đã có điểm)
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 8.5, 8.0, 8.5, ROUND(8.5*0.2+8.0*0.3+8.5*0.5,2), 'B+'
FROM student_subjects ss WHERE ss.student_id=1 AND ss.course_section_id=100;

-- SV2 - CNTT101_01
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 7.0, 6.5, 7.5, ROUND(7.0*0.2+6.5*0.3+7.5*0.5,2), 'B'
FROM student_subjects ss WHERE ss.student_id=2 AND ss.course_section_id=100;

-- SV4 - CNTT101_01
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 5.0, 4.5, 5.0, ROUND(5.0*0.2+4.5*0.3+5.0*0.5,2), 'C'
FROM student_subjects ss WHERE ss.student_id=4 AND ss.course_section_id=100;

-- SV6 - CNTT101_01 (điểm thấp → cảnh báo học vụ)
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 3.0, 2.5, 3.0, ROUND(3.0*0.2+2.5*0.3+3.0*0.5,2), 'F'
FROM student_subjects ss WHERE ss.student_id=6 AND ss.course_section_id=100;

-- SV8 - CNTT101_01
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 9.5, 9.0, 9.5, ROUND(9.5*0.2+9.0*0.3+9.5*0.5,2), 'A'
FROM student_subjects ss WHERE ss.student_id=8 AND ss.course_section_id=100;

-- SV3 - KETO010_01 (đã có điểm)
INSERT IGNORE INTO `grades` (`student_subject_id`,`process_score`,`midterm_score`,`final_score`,`total_score`,`letter_grade`)
SELECT ss.id, 8.0, 7.5, 8.0, ROUND(8.0*0.2+7.5*0.3+8.0*0.5,2), 'B+'
FROM student_subjects ss WHERE ss.student_id=3 AND ss.course_section_id=110;

-- ── Cập nhật enrollment_year cho students ────────────────────
UPDATE students SET enrollment_year = 2022 WHERE id IN (1,2,3,4);
UPDATE students SET enrollment_year = 2023 WHERE id IN (5,6,7,8);

SELECT 'Seed 005: enrollments & grades done' AS status;
SELECT
    COUNT(*) AS total_enrollments,
    SUM(CASE WHEN status='registered' THEN 1 ELSE 0 END) AS registered
FROM student_subjects;
SELECT COUNT(*) AS total_grades FROM grades;
