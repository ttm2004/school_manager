-- ============================================================
-- Thêm roles cho từng Khoa/Viện cụ thể
-- Chạy file này trong phpMyAdmin → database edu_management
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Thêm cột faculty_id vào bảng roles (nếu chưa có) ─────
ALTER TABLE `roles`
    ADD COLUMN IF NOT EXISTS `faculty_id` INT NULL DEFAULT NULL
        COMMENT 'NULL = role cấp trường, > 0 = role thuộc khoa cụ thể';

-- ── 2. Thêm roles cho từng khoa ──────────────────────────────
-- Khoa Công nghệ Thông tin (faculty_id = 1)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`) VALUES
('faculty_manager_1', 'Trưởng Khoa CNTT',   'Khoa Công nghệ Thông tin', 'Trưởng Khoa Công nghệ Thông tin', '#0d2d6b', 1, 1),
('faculty_staff_1',   'Thư ký Khoa CNTT',   'Khoa Công nghệ Thông tin', 'Thư ký Khoa Công nghệ Thông tin', '#1a4fa0', 1, 1);

-- Khoa Kinh tế (faculty_id = 2)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`) VALUES
('faculty_manager_2', 'Trưởng Khoa Kinh tế', 'Khoa Kinh tế', 'Trưởng Khoa Kinh tế', '#198754', 1, 2),
('faculty_staff_2',   'Thư ký Khoa Kinh tế', 'Khoa Kinh tế', 'Thư ký Khoa Kinh tế', '#20c997', 1, 2);

-- Khoa Ngoại ngữ (faculty_id = 3)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`) VALUES
('faculty_manager_3', 'Trưởng Khoa Ngoại ngữ', 'Khoa Ngoại ngữ', 'Trưởng Khoa Ngoại ngữ', '#6f42c1', 1, 3),
('faculty_staff_3',   'Thư ký Khoa Ngoại ngữ', 'Khoa Ngoại ngữ', 'Thư ký Khoa Ngoại ngữ', '#d63384', 1, 3);

-- Khoa Luật (faculty_id = 4)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`) VALUES
('faculty_manager_4', 'Trưởng Khoa Luật', 'Khoa Luật', 'Trưởng Khoa Luật', '#dc3545', 1, 4),
('faculty_staff_4',   'Thư ký Khoa Luật', 'Khoa Luật', 'Thư ký Khoa Luật', '#e35d6a', 1, 4);

-- Khoa Sư phạm (faculty_id = 5)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`) VALUES
('faculty_manager_5', 'Trưởng Khoa Sư phạm', 'Khoa Sư phạm', 'Trưởng Khoa Sư phạm', '#ffc107', 1, 5),
('faculty_staff_5',   'Thư ký Khoa Sư phạm', 'Khoa Sư phạm', 'Thư ký Khoa Sư phạm', '#fd7e14', 1, 5);

-- ── 3. Cập nhật ROLE_MODULE_MAP trong login.php ──────────────
-- Các role faculty_manager_X và faculty_staff_X đều redirect về /university/faculty/
-- Cần cập nhật hàm getStaffRedirectUrl() để nhận diện pattern faculty_manager_\d+

-- ── 4. Thêm permissions cho các role khoa cụ thể ─────────────
-- (Giống faculty_manager và faculty_staff hiện tại)
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r_new.id, rp.module, rp.permission
FROM role_permissions rp
JOIN roles r_template ON r_template.id = rp.role_id AND r_template.code = 'faculty_manager'
JOIN roles r_new ON r_new.code LIKE 'faculty_manager_%';

INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r_new.id, rp.module, rp.permission
FROM role_permissions rp
JOIN roles r_template ON r_template.id = rp.role_id AND r_template.code = 'faculty_staff'
JOIN roles r_new ON r_new.code LIKE 'faculty_staff_%';

-- ── 5. Kiểm tra kết quả ──────────────────────────────────────
SELECT r.id, r.code, r.name, r.department, r.faculty_id, f.faculty_name
FROM roles r
LEFT JOIN faculties f ON r.faculty_id = f.id
ORDER BY r.faculty_id IS NULL DESC, r.faculty_id, r.code;
