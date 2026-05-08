-- ============================================================
-- Tuition (Học phí) Migration
-- Chạy trong phpMyAdmin trên database: school_registration
-- ============================================================

-- Bảng hóa đơn học phí theo học kỳ
CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`    INT NOT NULL COMMENT 'FK → students.id',
    `semester_id`   INT NOT NULL COMMENT 'FK → semesters.id',
    `total_credits` INT NOT NULL DEFAULT 0 COMMENT 'Tổng tín chỉ đã đăng ký',
    `unit_price`    DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Đơn giá/TC tại thời điểm lập hóa đơn',
    `gross_amount`  DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Học phí gốc = total_credits × unit_price',
    `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Miễn giảm (học bổng, chính sách...)',
    `net_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Số tiền phải đóng = gross - discount',
    `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Đã đóng',
    `due_date`      DATE NULL COMMENT 'Hạn đóng học phí',
    `status`        ENUM('unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'unpaid'
                    COMMENT 'unpaid=chưa đóng, partial=đóng một phần, paid=đã đóng, overdue=quá hạn, waived=miễn',
    `note`          TEXT NULL,
    `created_by`    INT NULL COMMENT 'Admin tạo hóa đơn',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_student_semester` (`student_id`, `semester_id`),
    INDEX (`semester_id`),
    INDEX (`status`),
    INDEX (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lịch sử thanh toán
CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`    INT NOT NULL COMMENT 'FK → tuition_invoices.id',
    `amount`        DECIMAL(14,2) NOT NULL COMMENT 'Số tiền đóng lần này',
    `method`        ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash'
                    COMMENT 'Hình thức: tiền mặt, chuyển khoản, online, khác',
    `reference`     VARCHAR(100) NULL COMMENT 'Mã giao dịch / số biên lai',
    `note`          VARCHAR(255) NULL,
    `paid_by`       INT NULL COMMENT 'Admin ghi nhận',
    `paid_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`invoice_id`),
    INDEX (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng cấu hình học phí (cho phép override theo ngành/học kỳ)
CREATE TABLE IF NOT EXISTS `tuition_configs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `semester_id`   INT NULL COMMENT 'NULL = áp dụng cho tất cả học kỳ',
    `major_id`      INT NULL COMMENT 'NULL = áp dụng cho tất cả ngành',
    `unit_price`    DECIMAL(12,2) NOT NULL COMMENT 'Đơn giá/TC',
    `note`          VARCHAR(255) NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`semester_id`),
    INDEX (`major_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
