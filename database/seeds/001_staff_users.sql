-- ============================================================
-- Seed 001: Gán roles cho GV hiện có (KHÔNG tạo user mới)
--
-- Mapping GV → Role:
--   gv001 (user_id=10, faculty_id=1 CNTT) → faculty_manager (Trưởng khoa CNTT)
--   gv002 (user_id=11, faculty_id=1 CNTT) → faculty_staff   (Thư ký khoa CNTT)
--   gv003 (user_id=12, faculty_id=1 CNTT) → academic_manager (Trưởng phòng ĐT)
--   gv004 (user_id=13, faculty_id=2 KT)   → faculty_manager (Trưởng khoa KT)
--   gv005 (user_id=14, faculty_id=3 NN)   → faculty_manager (Trưởng khoa NN)
--   gv007 (user_id=15, faculty_id=1 CNTT) → faculty_staff   (Thư ký + GV)
--   gv008 (user_id=16, faculty_id=1 CNTT) → academic_staff  (NV Phòng ĐT)
-- ============================================================
SET NAMES utf8mb4;

-- ── Xóa role cũ nếu có để tránh duplicate ────────────────────
DELETE FROM user_roles WHERE user_id IN (10,11,12,13,14,15,16);

-- ── gv001 (ThS. Phạm Minh Tuấn) → Trưởng khoa CNTT ──────────
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 10, id, 1, 'Trưởng Khoa Công nghệ Thông tin'
FROM roles WHERE code = 'faculty_manager' LIMIT 1;

-- ── gv002 (ThS. Nguyễn Thị Hạnh) → Thư ký khoa CNTT ─────────
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 11, id, 10, 'Thư ký Khoa Công nghệ Thông tin'
FROM roles WHERE code = 'faculty_staff' LIMIT 1;

-- ── gv003 (TS. Trần Quốc Bảo) → Trưởng phòng Đào tạo ────────
-- GV này kiêm nhiệm Trưởng phòng ĐT → có 2 roles
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 12, id, 1, 'Trưởng phòng Đào tạo (kiêm nhiệm)'
FROM roles WHERE code = 'academic_manager' LIMIT 1;

-- ── gv004 (ThS. Lê Thị Mỹ Duyên) → Trưởng khoa Kinh tế ──────
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 13, id, 1, 'Trưởng Khoa Kinh tế'
FROM roles WHERE code = 'faculty_manager' LIMIT 1;

-- ── gv005 (ThS. Nguyễn Quốc Thịnh) → Trưởng khoa Ngoại ngữ ──
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 14, id, 1, 'Trưởng Khoa Ngoại ngữ'
FROM roles WHERE code = 'faculty_manager' LIMIT 1;

-- ── gv007 (Đinh Công Vy) → Thư ký khoa CNTT ─────────────────
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 15, id, 10, 'Thư ký Khoa CNTT (kiêm nhiệm)'
FROM roles WHERE code = 'faculty_staff' LIMIT 1;

-- ── gv008 (Lê Thanh Hoài) → Nhân viên Phòng Đào tạo ─────────
INSERT INTO user_roles (user_id, role_id, granted_by, note)
SELECT 16, id, 12, 'Nhân viên Phòng Đào tạo (kiêm nhiệm)'
FROM roles WHERE code = 'academic_staff' LIMIT 1;

-- ── Thêm role faculty_lecturer cho tất cả GV ─────────────────
-- (để GV có thể vào portal giảng viên khi chọn role)
INSERT IGNORE INTO user_roles (user_id, role_id, granted_by, note)
SELECT t.user_id, r.id, 1, 'Giảng viên'
FROM teachers t
CROSS JOIN roles r
WHERE r.code = 'faculty_lecturer'
  AND t.user_id IN (10,11,12,13,14,15,16);

-- ── Kiểm tra kết quả ─────────────────────────────────────────
SELECT
    u.username,
    u.full_name,
    GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ' | ') AS roles
FROM users u
JOIN user_roles ur ON ur.user_id = u.id
JOIN roles r ON ur.role_id = r.id
WHERE u.id IN (10,11,12,13,14,15,16)
GROUP BY u.id, u.username, u.full_name
ORDER BY u.username;

SELECT 'Seed 001: roles assigned to existing teachers' AS status;
