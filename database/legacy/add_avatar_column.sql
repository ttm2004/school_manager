-- Thêm cột avatar vào bảng users
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER address;

-- Cập nhật một số avatar mẫu nếu cần
-- UPDATE users SET avatar = 'avatars/default.jpg' WHERE avatar IS NULL;
