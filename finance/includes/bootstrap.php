<?php
if (!defined('FINANCE_BOOTSTRAPPED')) {
    define('FINANCE_BOOTSTRAPPED', true);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/AcademicPolicy.php';
requireAnyRole(['finance_manager', 'finance_staff']);

$_currentPage = basename($_SERVER['PHP_SELF']);
$_userName    = $_SESSION['full_name'] ?? 'Nhân viên';
$_userRoles   = getUserRoles();
$_isManager   = isFinanceManager();

// Auto-create / migrate tables
$conn->query("CREATE TABLE IF NOT EXISTS `tuition_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `semester_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL, `open_date` DATE NOT NULL, `due_date` DATE NOT NULL,
    `status` ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    `note` TEXT NULL, `created_by` INT NULL, `published_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_semester` (`semester_id`), INDEX(`status`), INDEX(`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `period_id` INT NOT NULL,
    `student_id` INT NOT NULL, `semester_id` INT NOT NULL,
    `total_credits` INT NOT NULL DEFAULT 0, `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount` DECIMAL(14,2) NOT NULL DEFAULT 0, `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount` DECIMAL(14,2) NOT NULL DEFAULT 0, `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `status` ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft',
    `note` TEXT NULL, `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ps` (`period_id`,`student_id`),
    INDEX(`semester_id`), INDEX(`student_id`), INDEX(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `invoice_id` INT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `method` ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    `reference` VARCHAR(100) NULL, `note` VARCHAR(255) NULL, `paid_by` INT NULL,
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(`invoice_id`), INDEX(`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migrate schema cu
$chkCol = $conn->query("SHOW COLUMNS FROM `tuition_invoices` LIKE 'period_id'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `tuition_invoices` ADD COLUMN `period_id` INT NOT NULL DEFAULT 0 AFTER `id`");
    $conn->query("ALTER TABLE `tuition_invoices` MODIFY COLUMN `status` ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft'");
    $conn->query("ALTER TABLE `tuition_invoices` DROP INDEX IF EXISTS `uq_student_semester`");
    $conn->query("DELETE FROM `tuition_invoices` WHERE period_id=0");
}
$chkIdx = $conn->query("SHOW INDEX FROM `tuition_invoices` WHERE Key_name='uq_ps'");
if ($chkIdx && $chkIdx->num_rows === 0) {
    $conn->query("ALTER TABLE `tuition_invoices` ADD UNIQUE KEY `uq_ps` (`period_id`,`student_id`)");
}

// Pending count cho badge
$_pendingCount = 0;
if (function_exists('refreshOverdueTuitionInvoices')) {
    refreshOverdueTuitionInvoices();
}
$_pr = $conn->query("SELECT COUNT(*) c FROM tuition_invoices WHERE status IN ('unpaid','partial','overdue')");
if ($_pr) $_pendingCount = (int)$_pr->fetch_assoc()['c'];
