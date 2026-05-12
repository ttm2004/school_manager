-- ============================================================
-- Module Phòng Đào Tạo — Migration
-- Chạy sau: edu_management.sql, faculty_module_migration.sql
-- ============================================================
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── 1. Thêm cột workflow vào course_sections ─────────────────
ALTER TABLE `course_sections`
    MODIFY COLUMN `status`
        ENUM('draft','proposed','open','full','closed','cancelled')
        NOT NULL DEFAULT 'open'
        COMMENT 'draft=Khoa tạo nháp, proposed=Khoa đề xuất chờ duyệt, open=Phòng ĐT mở chính thức, full=Đủ sĩ số, closed=Đã đóng, cancelled=Hủy',
    ADD COLUMN IF NOT EXISTS `proposal_status`
        ENUM('draft','pending','approved','rejected') NULL DEFAULT NULL
        COMMENT 'Trạng thái đề xuất phân công GV',
    ADD COLUMN IF NOT EXISTS `proposed_teacher_id`   INT  NULL DEFAULT NULL
        COMMENT 'GV được Khoa đề xuất (chờ Phòng ĐT duyệt)',
    ADD COLUMN IF NOT EXISTS `open_proposed_by`      INT  NULL DEFAULT NULL
        COMMENT 'user_id Trưởng khoa gửi đề xuất mở lớp',
    ADD COLUMN IF NOT EXISTS `open_proposed_at`      TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `open_reviewed_by`      INT  NULL DEFAULT NULL
        COMMENT 'user_id Phòng ĐT duyệt/từ chối',
    ADD COLUMN IF NOT EXISTS `open_reviewed_at`      TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `open_reject_reason`    TEXT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `open_proposal_note`    TEXT NULL DEFAULT NULL
        COMMENT 'Ghi chú đề xuất từ Khoa',
    ADD COLUMN IF NOT EXISTS `expected_students`     INT  NULL DEFAULT NULL
        COMMENT 'Dự kiến sĩ số',
    ADD COLUMN IF NOT EXISTS `room_requirement`      VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Yêu cầu phòng học đặc biệt',
    ADD COLUMN IF NOT EXISTS `teaching_mode`
        ENUM('offline','online','hybrid') NOT NULL DEFAULT 'offline',
    ADD COLUMN IF NOT EXISTS `special_equipment`     TEXT NULL DEFAULT NULL;

-- ── 2. Thêm cột active vào semesters ─────────────────────────
ALTER TABLE `semesters`
    MODIFY COLUMN `status`
        ENUM('upcoming','active','open','closed') NOT NULL DEFAULT 'upcoming'
        COMMENT 'upcoming=Sắp tới, active=Đang học, open=Mở đăng ký, closed=Đã kết thúc',
    ADD COLUMN IF NOT EXISTS `grade_submit_deadline` DATE NULL DEFAULT NULL
        COMMENT 'Hạn GV nộp điểm',
    ADD COLUMN IF NOT EXISTS `proposal_deadline`     DATE NULL DEFAULT NULL
        COMMENT 'Hạn Khoa gửi đề xuất mở lớp';

-- ── 3. Bảng academic_notifications — thông báo từ Phòng ĐT ──
CREATE TABLE IF NOT EXISTS `academic_notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`        ENUM(
                    'proposal_approved',   -- Phòng ĐT duyệt đề xuất mở lớp
                    'proposal_rejected',   -- Phòng ĐT từ chối
                    'assignment_approved', -- Phòng ĐT duyệt phân công GV
                    'assignment_rejected',
                    'grade_reminder',      -- Nhắc GV nhập điểm
                    'semester_open',       -- Học kỳ mở đăng ký
                    'general'
                  ) NOT NULL DEFAULT 'general',
    `faculty_id`  INT          NULL DEFAULT NULL COMMENT 'NULL = gửi toàn trường',
    `teacher_id`  INT          NULL DEFAULT NULL COMMENT 'NULL = gửi tất cả GV',
    `ref_id`      INT          NULL DEFAULT NULL COMMENT 'ID của course_section hoặc semester liên quan',
    `ref_type`    VARCHAR(30)  NULL DEFAULT NULL COMMENT 'course_section | semester',
    `title`       VARCHAR(255) NOT NULL,
    `content`     TEXT         NOT NULL,
    `sent_by`     INT          NOT NULL COMMENT 'user_id người gửi',
    `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_acad_notif_faculty`  (`faculty_id`),
    INDEX `idx_acad_notif_teacher`  (`teacher_id`),
    INDEX `idx_acad_notif_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Thông báo từ Phòng Đào tạo đến Khoa/GV';

-- ── 4. Bảng grade_lock — khóa điểm sau khi duyệt ────────────
CREATE TABLE IF NOT EXISTS `grade_locks` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_section_id` INT        NOT NULL,
    `locked_by`       INT          NOT NULL COMMENT 'user_id Phòng ĐT khóa',
    `locked_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `note`            VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_grade_lock_section` (`course_section_id`),
    CONSTRAINT `fk_grade_lock_section`
        FOREIGN KEY (`course_section_id`) REFERENCES `course_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Khóa điểm lớp HP sau khi Phòng ĐT xác nhận';

-- ── 5. Indexes hiệu năng ──────────────────────────────────────
CREATE INDEX IF NOT EXISTS `idx_cs_status`          ON `course_sections` (`status`);
CREATE INDEX IF NOT EXISTS `idx_cs_proposal_status` ON `course_sections` (`proposal_status`);
CREATE INDEX IF NOT EXISTS `idx_cs_proposed_teacher` ON `course_sections` (`proposed_teacher_id`);
CREATE INDEX IF NOT EXISTS `idx_cs_semester_status`  ON `course_sections` (`semester_id`, `status`);

-- ── 6. Kiểm tra ──────────────────────────────────────────────
DESCRIBE course_sections;
DESCRIBE semesters;
SELECT 'academic_notifications' AS tbl, COUNT(*) AS rows FROM academic_notifications
UNION ALL SELECT 'grade_locks', COUNT(*) FROM grade_locks;

SET foreign_key_checks = 1;
