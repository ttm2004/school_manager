-- Migration 008: Permission Matrix chuẩn
-- Thay thế hardcode if ($role === 'academic_manager') bằng DB-driven

-- Bảng permissions — định nghĩa tất cả quyền trong hệ thống
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(100) NOT NULL COMMENT 'VD: course_section.approve, grade.lock',
    `name`        VARCHAR(150) NOT NULL COMMENT 'Tên hiển thị',
    `module`      VARCHAR(50)  NOT NULL COMMENT 'faculty|academic|admissions|finance...',
    `description` TEXT         NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_perm_code` (`code`),
    INDEX `idx_perm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed permissions
INSERT IGNORE INTO `permissions` (`code`, `name`, `module`) VALUES
-- Academic module
('academic.semester.manage',        'Quản lý học kỳ',              'academic'),
('academic.course_section.create',  'Tạo lớp học phần',            'academic'),
('academic.course_section.approve', 'Duyệt đề xuất mở lớp',        'academic'),
('academic.course_section.reject',  'Từ chối đề xuất mở lớp',      'academic'),
('academic.assignment.approve',     'Duyệt phân công GV',          'academic'),
('academic.grade.view',             'Xem điểm toàn trường',        'academic'),
('academic.grade.override',         'Sửa điểm (override)',         'academic'),
('academic.grade.lock',             'Khóa điểm',                   'academic'),
('academic.report.view',            'Xem báo cáo',                 'academic'),
('academic.notification.send',      'Gửi thông báo toàn trường',   'academic'),
-- Faculty module
('faculty.proposal.submit',         'Gửi đề xuất mở lớp',         'faculty'),
('faculty.assignment.propose',      'Đề xuất phân công GV',        'faculty'),
('faculty.teacher.manage',          'Quản lý GV trong khoa',       'faculty'),
('faculty.student.view',            'Xem SV trong khoa',           'faculty'),
('faculty.curriculum.manage',       'Quản lý CTĐT',                'faculty'),
('faculty.staff.grant_role',        'Cấp quyền nhân viên khoa',    'faculty'),
('faculty.kpi.approve',             'Duyệt KPI giảng viên',        'faculty'),
('faculty.notification.send',       'Gửi thông báo nội bộ khoa',   'faculty'),
-- Teacher
('teacher.grade.input',             'Nhập điểm',                   'teacher'),
('teacher.wish.submit',             'Đăng ký nguyện vọng dạy',     'teacher'),
('teacher.kpi.submit',              'Nộp KPI',                     'teacher'),
-- Student
('student.subject.register',        'Đăng ký học phần',            'student'),
('student.grade.view',              'Xem điểm của mình',           'student'),
('student.evaluation.submit',       'Đánh giá giảng viên',         'student');

-- Gán permissions cho roles (role_permissions đã có, thêm mapping mới)
-- academic_manager có tất cả academic.*
INSERT IGNORE INTO `role_permissions` (role_id, module, permission)
SELECT r.id, 'academic', p.code
FROM roles r, permissions p
WHERE r.code = 'academic_manager' AND p.module = 'academic';

-- academic_staff có view + send notification
INSERT IGNORE INTO `role_permissions` (role_id, module, permission)
SELECT r.id, 'academic', p.code
FROM roles r, permissions p
WHERE r.code = 'academic_staff'
  AND p.code IN ('academic.grade.view','academic.report.view','academic.notification.send');

-- faculty_manager có tất cả faculty.*
INSERT IGNORE INTO `role_permissions` (role_id, module, permission)
SELECT r.id, 'faculty', p.code
FROM roles r, permissions p
WHERE r.code = 'faculty_manager' AND p.module = 'faculty';

SELECT 'Migration 008: permission matrix created' AS status;
