-- ============================================================
-- Faculty Roles Extended Migration
-- Thêm roles cho từng khoa cụ thể + quyền manage_staff_roles
-- Chạy file này trong phpMyAdmin sau các migration trước
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. Thêm quyền manage_staff_roles cho faculty_manager (role_id=14)
-- ============================================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`) VALUES
(14, 'faculty', 'manage_staff_roles'),
(14, 'faculty', 'manage_notifications'),
(14, 'faculty', 'manage_departments'),
(14, 'faculty', 'export_data'),
(15, 'faculty', 'export_data');

-- ============================================================
-- 2. Thêm cột faculty_scope vào bảng user_roles
--    Cho phép gán role gắn với khoa cụ thể
-- ============================================================
ALTER TABLE `user_roles`
    ADD COLUMN IF NOT EXISTS `faculty_scope` INT NULL DEFAULT NULL
        COMMENT 'faculty_id nếu role này chỉ có hiệu lực trong khoa cụ thể (NULL = toàn trường)';

CREATE INDEX IF NOT EXISTS `idx_user_roles_faculty_scope`
    ON `user_roles` (`faculty_scope`);

-- ============================================================
-- 3. Thêm roles cho từng khoa cụ thể
--    Pattern: faculty_{faculty_code}_manager / faculty_{faculty_code}_staff
-- ============================================================

-- Khoa Công nghệ Thông tin (faculty_id=1, code=CNTT)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('faculty_cntt_manager', 'Trưởng Khoa CNTT', 'Khoa Công nghệ Thông tin', 'Trưởng Khoa Công nghệ Thông tin - toàn quyền quản lý khoa CNTT', '#0d2d6b', 1),
('faculty_cntt_staff',   'Thư ký Khoa CNTT', 'Khoa Công nghệ Thông tin', 'Thư ký Khoa Công nghệ Thông tin - hỗ trợ nghiệp vụ', '#1a4fa0', 1);

-- Khoa Kinh tế (faculty_id=2, code=KT)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('faculty_kt_manager', 'Trưởng Khoa Kinh tế', 'Khoa Kinh tế', 'Trưởng Khoa Kinh tế - toàn quyền quản lý khoa Kinh tế', '#198754', 1),
('faculty_kt_staff',   'Thư ký Khoa Kinh tế', 'Khoa Kinh tế', 'Thư ký Khoa Kinh tế - hỗ trợ nghiệp vụ', '#20c997', 1);

-- Khoa Ngoại ngữ (faculty_id=3, code=NN)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('faculty_nn_manager', 'Trưởng Khoa Ngoại ngữ', 'Khoa Ngoại ngữ', 'Trưởng Khoa Ngoại ngữ - toàn quyền quản lý khoa Ngoại ngữ', '#6f42c1', 1),
('faculty_nn_staff',   'Thư ký Khoa Ngoại ngữ', 'Khoa Ngoại ngữ', 'Thư ký Khoa Ngoại ngữ - hỗ trợ nghiệp vụ', '#d63384', 1);

-- Khoa Luật (faculty_id=4, code=LUAT)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('faculty_luat_manager', 'Trưởng Khoa Luật', 'Khoa Luật', 'Trưởng Khoa Luật - toàn quyền quản lý khoa Luật', '#dc3545', 1),
('faculty_luat_staff',   'Thư ký Khoa Luật', 'Khoa Luật', 'Thư ký Khoa Luật - hỗ trợ nghiệp vụ', '#e35d6a', 1);

-- Khoa Sư phạm (faculty_id=5, code=SP)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('faculty_sp_manager', 'Trưởng Khoa Sư phạm', 'Khoa Sư phạm', 'Trưởng Khoa Sư phạm - toàn quyền quản lý khoa Sư phạm', '#ffc107', 1),
('faculty_sp_staff',   'Thư ký Khoa Sư phạm', 'Khoa Sư phạm', 'Thư ký Khoa Sư phạm - hỗ trợ nghiệp vụ', '#fd7e14', 1);

-- ============================================================
-- 4. Gán permissions cho các roles khoa cụ thể
--    (giống faculty_manager/faculty_staff nhưng gắn với khoa)
-- ============================================================

-- Permissions cho tất cả *_manager roles của khoa
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, rp.module, rp.permission
FROM roles r
CROSS JOIN role_permissions rp
WHERE r.code LIKE 'faculty_%_manager'
  AND rp.role_id = 14;  -- Copy từ faculty_manager

-- Permissions cho tất cả *_staff roles của khoa
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, rp.module, rp.permission
FROM roles r
CROSS JOIN role_permissions rp
WHERE r.code LIKE 'faculty_%_staff'
  AND rp.role_id = 15;  -- Copy từ faculty_staff

-- ============================================================
-- 5. Cập nhật ROLE_MODULE_MAP trong login.php sẽ tự nhận
--    vì tất cả đều có prefix 'faculty_'
-- ============================================================

-- Kiểm tra kết quả
SELECT code, name, department FROM roles WHERE code LIKE 'faculty_%' ORDER BY code;
SELECT COUNT(*) AS total_permissions FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE code LIKE 'faculty_%');
