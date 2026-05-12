-- ============================================================
-- Kiểm tra và gán role cho tài khoản nhân viên đào tạo
-- Chạy từng bước trong phpMyAdmin
-- ============================================================

-- BƯỚC 1: Xem tất cả user có role='staff' và role đã được gán
SELECT 
    u.id, u.username, u.full_name, u.role as base_role,
    r.code as assigned_role, r.name as role_name
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
WHERE u.role = 'staff'
ORDER BY u.username;

-- ============================================================
-- BƯỚC 2: Xem user nào chưa có role nào trong user_roles
SELECT u.id, u.username, u.full_name
FROM users u
WHERE u.role = 'staff'
AND u.id NOT IN (SELECT DISTINCT user_id FROM user_roles);

-- ============================================================
-- BƯỚC 3: Gán role academic_staff cho TẤT CẢ user staff chưa có role
-- (Thay đổi nếu muốn gán cho user cụ thể)
INSERT IGNORE INTO user_roles (user_id, role_id, granted_by, note)
SELECT 
    u.id,
    r.id,
    (SELECT id FROM users WHERE role='admin' LIMIT 1),
    'Auto-assigned: academic_staff for staff users'
FROM users u
CROSS JOIN roles r
WHERE u.role = 'staff'
AND r.code = 'academic_staff'
AND u.id NOT IN (SELECT user_id FROM user_roles WHERE role_id = r.id);

-- ============================================================
-- BƯỚC 4: Kiểm tra lại sau khi gán
SELECT 
    u.id, u.username, u.full_name,
    r.code as role_code, r.name as role_name
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
WHERE u.role = 'staff'
ORDER BY u.username;

-- ============================================================
-- HOẶC: Gán role cho 1 user cụ thể theo username
-- Thay 'ten_dang_nhap' bằng username thực tế
-- ============================================================
/*
INSERT IGNORE INTO user_roles (user_id, role_id, granted_by, note)
SELECT 
    u.id,
    r.id,
    (SELECT id FROM users WHERE role='admin' LIMIT 1),
    'Manual assign: academic_staff'
FROM users u
CROSS JOIN roles r
WHERE u.username = 'ten_dang_nhap'   -- <-- đổi thành username thực
AND r.code = 'academic_staff';
*/
