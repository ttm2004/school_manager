-- ============================================================
-- RBAC Migration - Hệ thống phân quyền theo phòng ban
-- Database: edu_management
-- Chạy file này 1 lần duy nhất
-- ============================================================

-- 1. Bảng danh sách roles (phòng ban / chức vụ)
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `code`        VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã role, VD: admissions_staff',
    `name`        VARCHAR(100) NOT NULL COMMENT 'Tên hiển thị, VD: Nhân viên Tuyển sinh',
    `department`  VARCHAR(100) COMMENT 'Phòng ban, VD: Phòng Tuyển sinh',
    `description` TEXT,
    `color`       VARCHAR(20) DEFAULT '#6c757d' COMMENT 'Màu badge hiển thị',
    `is_active`   TINYINT(1) DEFAULT 1,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Bảng gán role cho user (admin cấp quyền)
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `role_id`     INT NOT NULL,
    `granted_by`  INT COMMENT 'user_id của admin đã cấp quyền',
    `granted_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  TIMESTAMP NULL COMMENT 'Hết hạn quyền, NULL = vĩnh viễn',
    `note`        VARCHAR(255) COMMENT 'Ghi chú khi cấp quyền',
    UNIQUE KEY `user_role_unique` (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bảng quyền cụ thể của từng role
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `role_id`    INT NOT NULL,
    `module`     VARCHAR(50) NOT NULL COMMENT 'Module: admissions, academic, finance...',
    `permission` VARCHAR(100) NOT NULL COMMENT 'Quyền: view, create, edit, delete, approve...',
    UNIQUE KEY `role_perm_unique` (`role_id`, `module`, `permission`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed: Roles mặc định theo phòng ban trường đại học
-- ============================================================

INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`) VALUES

-- Phòng Tuyển sinh
('admissions_manager', 'Trưởng phòng Tuyển sinh', 'Phòng Tuyển sinh',
 'Quản lý toàn bộ hoạt động tuyển sinh: xét duyệt, điểm chuẩn, báo cáo', '#0d6efd'),

('admissions_staff', 'Nhân viên Tuyển sinh', 'Phòng Tuyển sinh',
 'Tiếp nhận hồ sơ, hỗ trợ thí sinh, làm thủ tục nhập học', '#0dcaf0'),

-- Phòng Đào tạo
('academic_manager', 'Trưởng phòng Đào tạo', 'Phòng Đào tạo',
 'Quản lý chương trình đào tạo, học kỳ, môn học, lớp học phần', '#198754'),

('academic_staff', 'Nhân viên Đào tạo', 'Phòng Đào tạo',
 'Hỗ trợ đăng ký môn học, thời khóa biểu, lịch thi', '#20c997'),

-- Phòng Tài chính - Kế toán
('finance_manager', 'Trưởng phòng Tài chính', 'Phòng Tài chính - Kế toán',
 'Quản lý học phí, thu chi, báo cáo tài chính', '#ffc107'),

('finance_staff', 'Nhân viên Kế toán', 'Phòng Tài chính - Kế toán',
 'Thu học phí, xử lý miễn giảm, học bổng', '#fd7e14'),

-- Phòng Tổ chức - Nhân sự
('hr_manager', 'Trưởng phòng Nhân sự', 'Phòng Tổ chức - Nhân sự',
 'Quản lý hợp đồng, lương, chấm công giảng viên và nhân viên', '#6f42c1'),

('hr_staff', 'Nhân viên Nhân sự', 'Phòng Tổ chức - Nhân sự',
 'Hỗ trợ hồ sơ nhân sự, chấm công', '#d63384'),

-- Phòng Công tác sinh viên
('student_affairs_manager', 'Trưởng phòng Công tác SV', 'Phòng Công tác Sinh viên',
 'Quản lý kỷ luật, học bổng, hoạt động ngoại khóa', '#dc3545'),

('student_affairs_staff', 'Nhân viên Công tác SV', 'Phòng Công tác Sinh viên',
 'Hỗ trợ sinh viên, xử lý đơn từ, hoạt động phong trào', '#e35d6a'),

-- Phòng Khảo thí
('exam_manager', 'Trưởng phòng Khảo thí', 'Phòng Khảo thí',
 'Quản lý lịch thi, ngân hàng đề, kết quả thi', '#0d2d6b'),

('exam_staff', 'Nhân viên Khảo thí', 'Phòng Khảo thí',
 'Sắp xếp phòng thi, giám sát, nhập điểm', '#1a4fa0'),

-- Phòng CNTT
('it_admin', 'Quản trị CNTT', 'Phòng Công nghệ Thông tin',
 'Quản lý hệ thống, backup, tài khoản người dùng', '#495057');

-- ============================================================
-- Seed: Permissions mặc định cho từng role
-- ============================================================

-- admissions_manager: toàn quyền module tuyển sinh
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'admissions', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_applications' as perm UNION SELECT 'create_application'
    UNION SELECT 'edit_application' UNION SELECT 'delete_application'
    UNION SELECT 'approve_application' UNION SELECT 'reject_application'
    UNION SELECT 'set_cutoff_score' UNION SELECT 'run_auto_review'
    UNION SELECT 'view_results' UNION SELECT 'publish_results'
    UNION SELECT 'view_reports' UNION SELECT 'export_data'
    UNION SELECT 'manage_methods' UNION SELECT 'manage_news'
    UNION SELECT 'manage_enrollment'
) p WHERE r.code = 'admissions_manager';

-- admissions_staff: quyền hạn chế
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'admissions', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_applications' as perm UNION SELECT 'create_application'
    UNION SELECT 'edit_application' UNION SELECT 'view_results'
    UNION SELECT 'manage_enrollment'
) p WHERE r.code = 'admissions_staff';

-- academic_manager: toàn quyền module đào tạo
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'academic', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_subjects' as perm UNION SELECT 'manage_subjects'
    UNION SELECT 'manage_curriculum' UNION SELECT 'manage_semesters'
    UNION SELECT 'manage_sections' UNION SELECT 'manage_schedules'
    UNION SELECT 'view_grades' UNION SELECT 'manage_grades'
    UNION SELECT 'view_reports' UNION SELECT 'export_data'
) p WHERE r.code = 'academic_manager';

-- academic_staff
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'academic', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_subjects' as perm UNION SELECT 'manage_sections'
    UNION SELECT 'manage_schedules' UNION SELECT 'view_grades'
) p WHERE r.code = 'academic_staff';

-- finance_manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'finance', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_tuition' as perm UNION SELECT 'manage_tuition'
    UNION SELECT 'view_scholarships' UNION SELECT 'manage_scholarships'
    UNION SELECT 'view_reports' UNION SELECT 'export_data'
) p WHERE r.code = 'finance_manager';

-- finance_staff
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'finance', p.perm FROM roles r
CROSS JOIN (
    SELECT 'view_tuition' as perm UNION SELECT 'collect_tuition'
    UNION SELECT 'view_scholarships'
) p WHERE r.code = 'finance_staff';
