-- ============================================================
-- Faculty Proposal Migration
-- Thêm cột đề xuất phân công GV vào course_sections
-- Chạy 1 lần trong phpMyAdmin hoặc MySQL CLI
-- ============================================================

ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `proposed_teacher_id` INT NULL DEFAULT NULL
        COMMENT 'GV được Khoa/Viện đề xuất (chưa phải phân công chính thức)',
    ADD COLUMN IF NOT EXISTS `proposal_status` ENUM('pending','approved','rejected') NULL DEFAULT NULL
        COMMENT 'Trạng thái đề xuất: pending=chờ duyệt, approved=đã duyệt, rejected=từ chối',
    ADD COLUMN IF NOT EXISTS `proposal_note` TEXT NULL DEFAULT NULL
        COMMENT 'Ghi chú đề xuất từ Khoa/Viện',
    ADD COLUMN IF NOT EXISTS `proposed_by` INT NULL DEFAULT NULL
        COMMENT 'user_id của người tạo đề xuất',
    ADD COLUMN IF NOT EXISTS `proposed_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Thời điểm tạo đề xuất',
    ADD COLUMN IF NOT EXISTS `proposal_reviewed_by` INT NULL DEFAULT NULL
        COMMENT 'user_id của người duyệt (Phòng Đào tạo)',
    ADD COLUMN IF NOT EXISTS `proposal_reviewed_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Thời điểm duyệt/từ chối',
    ADD COLUMN IF NOT EXISTS `proposal_reject_reason` TEXT NULL DEFAULT NULL
        COMMENT 'Lý do từ chối (nếu rejected)';

-- Thêm cột status 'proposed' vào course_sections nếu chưa có
-- (course_sections.status hiện tại: open, closed, cancelled)
-- Không cần thêm vì proposal_status là cột riêng

-- ============================================================
-- Đề xuất mở lớp học phần (proposals từ Khoa lên Phòng ĐT)
-- Dùng course_sections với status='proposed' thay vì bảng mới
-- ============================================================

-- Thêm 'proposed' vào ENUM status của course_sections
ALTER TABLE `course_sections`
    MODIFY COLUMN `status` ENUM('open','closed','cancelled','proposed') NOT NULL DEFAULT 'open'
        COMMENT 'open=đang mở, closed=đã đóng, cancelled=hủy, proposed=Khoa đề xuất mở (chờ Phòng ĐT duyệt)';

-- Thêm cột ghi nhận đề xuất mở lớp
ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `open_proposal_note` TEXT NULL DEFAULT NULL
        COMMENT 'Lý do đề xuất mở lớp từ Khoa/Viện',
    ADD COLUMN IF NOT EXISTS `open_proposed_by` INT NULL DEFAULT NULL
        COMMENT 'user_id người đề xuất mở lớp',
    ADD COLUMN IF NOT EXISTS `open_proposed_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Thời điểm đề xuất mở lớp';

-- ============================================================
-- Kiểm tra kết quả
-- ============================================================
DESCRIBE course_sections;
