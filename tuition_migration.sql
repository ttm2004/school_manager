-- ============================================================
-- Tuition Module Migration v3
-- Chạy trong phpMyAdmin: school_registration
-- ============================================================

-- Bảng yêu cầu chốt học phí từ Phòng Đào tạo → Kế toán
CREATE TABLE IF NOT EXISTS `tuition_requests` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `semester_id`     INT NOT NULL COMMENT 'FK → semesters.id',
    `title`           VARCHAR(255) NOT NULL COMMENT 'VD: Chốt HP HK1 2025-2026',
    `note`            TEXT NULL,
    `status`          ENUM('pending','processing','done') NOT NULL DEFAULT 'pending'
                      COMMENT 'pending=chờ KT xử lý, processing=KT đang tính, done=đã công bố',
    `created_by`      INT NULL COMMENT 'Nhân viên Đào tạo tạo',
    `processed_by`    INT NULL COMMENT 'Nhân viên Kế toán xử lý',
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_semester` (`semester_id`),
    INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng đợt thu học phí (do Kế toán tạo từ request)
CREATE TABLE IF NOT EXISTS `tuition_periods` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `request_id`      INT NULL COMMENT 'FK → tuition_requests.id',
    `semester_id`     INT NOT NULL COMMENT 'FK → semesters.id',
    `title`           VARCHAR(255) NOT NULL,
    `open_date`       DATE NOT NULL COMMENT 'Ngày công bố cho SV',
    `due_date`        DATE NOT NULL COMMENT 'Hạn đóng học phí',
    `status`          ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    `note`            TEXT NULL,
    `created_by`      INT NULL,
    `published_at`    TIMESTAMP NULL,
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
    `semester_id`   INT NOT NULL,
    `total_credits` INT NOT NULL DEFAULT 0,
    `unit_price`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount`  DECIMAL(14,2) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0,
    `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0,
    `status`        ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft',
    `note`          TEXT NULL,
    `created_by`    INT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ps` (`period_id`,`student_id`),
    INDEX(`semester_id`), INDEX(`student_id`), INDEX(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lịch sử thanh toán
CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `amount`     DECIMAL(14,2) NOT NULL,
    `method`     ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    `reference`  VARCHAR(100) NULL,
    `note`       VARCHAR(255) NULL,
    `paid_by`    INT NULL,
    `paid_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(`invoice_id`), INDEX(`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Thêm roles mới vào bảng roles (nếu chưa có)
INSERT IGNORE INTO `roles` (code, name, department, color, is_active) VALUES
('training_manager', 'Trưởng phòng Đào tạo', 'Phòng Đào tạo', '#0ea5e9', 1),
('training_staff',   'Nhân viên Đào tạo',    'Phòng Đào tạo', '#38bdf8', 1),
('finance_manager',  'Trưởng phòng Kế toán', 'Phòng Kế toán', '#10b981', 1),
('finance_staff',    'Nhân viên Kế toán',    'Phòng Kế toán', '#34d399', 1);
