-- ============================================================
-- Faculty Management Roles Migration
-- Thêm roles cho module Quản lý Khoa/Viện
-- Chạy file này 1 lần trong phpMyAdmin hoặc MySQL CLI
-- ============================================================

INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`) VALUES

-- Khoa/Viện — Trưởng khoa
('faculty_manager', 'Trưởng Khoa/Viện', 'Khoa/Viện',
 'Quản lý ngành học, lớp học, giảng viên, lịch thi, phân công giảng dạy trong khoa', '#0d2d6b'),

-- Khoa/Viện — Thư ký khoa
('faculty_staff', 'Thư ký Khoa/Viện', 'Khoa/Viện',
 'Xem và hỗ trợ nghiệp vụ khoa: danh sách SV, điểm số, lịch thi, đánh giá GV', '#1a4fa0');

-- ============================================================
-- Permissions cho faculty_manager (toàn quyền trong khoa)
-- ============================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'faculty', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_majors'       as perm UNION SELECT 'edit_majors'
    UNION SELECT 'view_classes'         UNION SELECT 'manage_classes'
    UNION SELECT 'view_curriculum'      UNION SELECT 'manage_curriculum'
    UNION SELECT 'view_teachers'        UNION SELECT 'edit_teachers'
    UNION SELECT 'view_students'        UNION SELECT 'view_grades'
    UNION SELECT 'view_exam_schedules'  UNION SELECT 'manage_exam_schedules'
    UNION SELECT 'manage_assignments'   UNION SELECT 'view_evaluation'
    UNION SELECT 'view_reports'
) p WHERE r.code = 'faculty_manager';

-- ============================================================
-- Permissions cho faculty_staff (chỉ xem + nhập liệu cơ bản)
-- ============================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'faculty', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_majors'       as perm
    UNION SELECT 'view_classes'
    UNION SELECT 'view_curriculum'
    UNION SELECT 'view_teachers'
    UNION SELECT 'view_students'
    UNION SELECT 'view_grades'
    UNION SELECT 'view_exam_schedules'
    UNION SELECT 'view_evaluation'
    UNION SELECT 'view_reports'
) p WHERE r.code = 'faculty_staff';

-- ============================================================
-- Kiểm tra kết quả
-- ============================================================
SELECT id, code, name, department, color FROM roles WHERE code IN ('faculty_manager', 'faculty_staff');
