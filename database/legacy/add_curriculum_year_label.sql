-- ============================================================
-- Migration: Thêm cột year_label và semester_label vào curriculum
-- Đồng bộ cấu trúc với file CSV chương trình đào tạo
-- Chạy 1 lần trong phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;

-- 1. Thêm cột year_label vào curriculum (VD: '2022-2023')
ALTER TABLE `curriculum`
    ADD COLUMN IF NOT EXISTS `year_label` VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'Năm học, VD: 2022-2023'
        AFTER `suggested_semester`;

-- 2. Thêm cột semester_label vào curriculum (VD: 'Học kỳ 1')
ALTER TABLE `curriculum`
    ADD COLUMN IF NOT EXISTS `semester_label` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Tên học kỳ, VD: Học kỳ 1'
        AFTER `year_label`;

-- 3. Thêm cột total_periods vào subjects (tổng tiết = LT + TH)
ALTER TABLE `subjects`
    ADD COLUMN IF NOT EXISTS `total_periods` INT NULL DEFAULT NULL
        COMMENT 'Tổng số tiết = theory_periods + practice_periods'
        AFTER `practice_periods`;

-- 4. Cập nhật total_periods từ dữ liệu hiện có
UPDATE `subjects`
SET `total_periods` = COALESCE(`theory_periods`, 0) + COALESCE(`practice_periods`, 0)
WHERE `total_periods` IS NULL;

-- 5. Đổi subject_type trong curriculum thành enum chuẩn
-- (đã có sẵn: 'required','elective','general' — giữ nguyên)

-- 6. Thêm index cho year_label để query nhanh hơn
ALTER TABLE `curriculum`
    ADD INDEX IF NOT EXISTS `idx_curriculum_year` (`year_label`);

SELECT 'Migration add_curriculum_year_label hoàn tất!' AS result;
DESCRIBE curriculum;
