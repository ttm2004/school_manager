-- ============================================================
-- SEED RUNNER — Chạy tất cả seeds theo thứ tự
-- Chạy trong phpMyAdmin: database edu_management
--
-- THỨ TỰ BẮT BUỘC:
-- 1. edu_management.sql          (schema + data gốc)
-- 2. create_curriculum_table.sql (bảng curriculum)
-- 3. create_departments_table.sql (bảng departments)
-- 4. faculty_module_migration.sql (faculty_audit_logs, student_warnings)
-- 5. academic_module_migration.sql (workflow columns)
-- 6. faculty_upgrade_migration.sql (teaching_wishes, teacher_kpi)
-- 7. add_faculty_roles.sql        (roles khoa cụ thể)
-- 8. database/migrations/007_audit_logs_standard.sql
-- 9. database/migrations/008_permission_matrix.sql
-- Sau đó chạy các file seed này theo thứ tự:
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Seed 001: Nhân viên + Trưởng khoa
SOURCE 001_staff_users.sql;

-- Seed 002: Học kỳ + Môn học
SOURCE 002_semesters_subjects.sql;

-- Seed 003: Chương trình đào tạo
SOURCE 003_curriculum.sql;

-- Seed 004: Lớp học phần
SOURCE 004_course_sections.sql;

-- Seed 005: Đăng ký môn + Điểm
SOURCE 005_enrollments_grades.sql;

-- Seed 006: Lịch thi + Thông báo + KPI
SOURCE 006_exam_schedules_notifications.sql;

-- Seed 007: Đánh giá + Học phí
SOURCE 007_evaluation_tuition.sql;

-- Seed 008: Thêm giảng viên mẫu theo ngành
-- Chạy bằng CLI sau file SQL này:
-- php database/seeds/008_more_teachers_by_major.php

SET foreign_key_checks = 1;

-- ── Kiểm tra tổng quan ────────────────────────────────────────
SELECT '=== SEED SUMMARY ===' AS info;
SELECT 'users'            AS tbl, COUNT(*) AS cnt FROM users
UNION ALL SELECT 'teachers',         COUNT(*) FROM teachers
UNION ALL SELECT 'students',         COUNT(*) FROM students
UNION ALL SELECT 'subjects',         COUNT(*) FROM subjects
UNION ALL SELECT 'curriculum',       COUNT(*) FROM curriculum
UNION ALL SELECT 'course_sections',  COUNT(*) FROM course_sections
UNION ALL SELECT 'student_subjects', COUNT(*) FROM student_subjects
UNION ALL SELECT 'grades',           COUNT(*) FROM grades
UNION ALL SELECT 'final_exam_schedules', COUNT(*) FROM final_exam_schedules
UNION ALL SELECT 'notifications',    COUNT(*) FROM notifications
UNION ALL SELECT 'user_roles',       COUNT(*) FROM user_roles;
