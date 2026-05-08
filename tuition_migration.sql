-- ============================================================
-- Tuition Module Migration v2
-- Chạy trong phpMyAdmin: school_registration
-- ============================================================

-- Bảng đợt thu học phí (quản lý theo học kỳ)
CREATE TABLE IF NOT EXISTS `tuition_periods` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `semester_id`     INT NOT NULL COMMENT 'FK → semesters.id',
    `title`           VARCHAR(255) NOT NULL COMMENT 'VD: Thu học phí HK1 2025-2026',
    `open_date`       DATE NOT NULL COMMENT 'Ngày bắt đầu thu (công bố cho SV)',
    `due_date`        DATE NOT NULL COMMENT 'Hạn đóng học phí',
    `status`          ENUM('draft','published','closed') NOT NULL DEFAULT 'draft'
                      COMMENT 'draft=nháp, published=đã công bố, closed=đã đóng',
    `note`            TEXT NULL,
    `created_by`      INT NULL,
    `published_at`    TIMESTAMP NULL COMMENT 'Thời điểm công bố',
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_semester` (`semester_id`),
    INDEX (`status`), INDEX (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng hóa đơn học phí
CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `period_id`     INT NOT NULL COMMENT 'FK → tuition_periods.id',
    `student_id`    INT NOT NULL COMMENT 'FK → students.id',
    `semester_id`   INT NOT NULL COMMENT 'FK → semesters.id',
    `total_credits` INT NOT NULL DEFAULT 0,
    `unit_price`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount`  DECIMAL(14,2) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0,
    `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0,
    `status`        ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft'
                    COMMENT 'draft=chưa công bố, unpaid=chưa đóng, ...',
    `note`          TEXT NULL,
    `created_by`    INT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_period_student` (`period_id`, `student_id`),
    INDEX (`semester_id`), INDEX (`student_id`), INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lịch sử thanh toán
CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL COMMENT 'FK → tuition_invoices.id',
    `amount`     DECIMAL(14,2) NOT NULL,
    `method`     ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    `reference`  VARCHAR(100) NULL COMMENT 'Mã giao dịch / số biên lai',
    `note`       VARCHAR(255) NULL,
    `paid_by`    INT NULL COMMENT 'Admin ghi nhận',
    `paid_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`invoice_id`), INDEX (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
