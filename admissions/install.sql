-- ============================================================
-- Admissions Module - Migration SQL
-- Run this on database: edu_management
-- ============================================================

-- Provinces
CREATE TABLE IF NOT EXISTS `adm_provinces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Districts
CREATE TABLE IF NOT EXISTS `adm_districts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `province_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20),
  FOREIGN KEY (`province_id`) REFERENCES `adm_provinces`(`id`) ON DELETE CASCADE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subject combinations (A00, D01, ...)
CREATE TABLE IF NOT EXISTS `adm_subject_combinations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `subjects` VARCHAR(200) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admission methods
CREATE TABLE IF NOT EXISTS `adm_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `method_name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `priority` INT DEFAULT 0,
  `status` ENUM('open','closed') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registrations (student applications)
CREATE TABLE IF NOT EXISTS `adm_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fullname` VARCHAR(255) NOT NULL,
  `birthday` DATE NOT NULL,
  `gender` VARCHAR(10),
  `identification` VARCHAR(20) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `province_id` INT,
  `district_id` INT,
  `address` TEXT,
  `graduation_year` YEAR NOT NULL,
  `school` VARCHAR(255),
  `major_id` INT,
  `method_code` VARCHAR(50),
  `combination_id` INT,
  `notes` TEXT,
  `transcript_file` VARCHAR(500),
  `certificate_file` VARCHAR(500),
  `ip_address` VARCHAR(45),
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`email`),
  INDEX (`phone`),
  INDEX (`identification`),
  FOREIGN KEY (`province_id`) REFERENCES `adm_provinces`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`district_id`) REFERENCES `adm_districts`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`combination_id`) REFERENCES `adm_subject_combinations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scores
CREATE TABLE IF NOT EXISTS `adm_scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `method_code` VARCHAR(50) NOT NULL,
  `score_data` JSON,
  `total_score` DECIMAL(6,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`registration_id`) REFERENCES `adm_registrations`(`id`) ON DELETE CASCADE,
  INDEX (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cutoff scores (điểm chuẩn)
CREATE TABLE IF NOT EXISTS `adm_cutoff_scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `major_id` INT NOT NULL,
  `year` YEAR NOT NULL,
  `method_code` VARCHAR(50) NOT NULL,
  `combination_id` INT,
  `score` DECIMAL(6,2) NOT NULL,
  `quota` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`combination_id`) REFERENCES `adm_subject_combinations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admission quota per major per year
CREATE TABLE IF NOT EXISTS `adm_quota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `major_id` INT NOT NULL,
  `year` YEAR NOT NULL,
  `quota` INT NOT NULL DEFAULT 100,
  `confirmed` INT NOT NULL DEFAULT 0,
  UNIQUE KEY `major_year` (`major_id`, `year`),
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admission results
CREATE TABLE IF NOT EXISTS `adm_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `major_id` INT,
  `year` YEAR,
  `method_code` VARCHAR(50),
  `combination_id` INT,
  `total_score` DECIMAL(6,2),
  `cutoff_score` DECIMAL(6,2),
  `status` ENUM('passed','failed') NOT NULL,
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `reg_id` (`registration_id`),
  FOREIGN KEY (`registration_id`) REFERENCES `adm_registrations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enrollment confirmation
CREATE TABLE IF NOT EXISTS `adm_confirmations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `fullname` VARCHAR(255),
  `phone` VARCHAR(20),
  `email` VARCHAR(255),
  `major_id` INT,
  `major_name` VARCHAR(200),
  `total_score` DECIMAL(6,2),
  `method_name` VARCHAR(200),
  `status` ENUM('pending','confirmed','expired') DEFAULT 'pending',
  `confirmed_at` TIMESTAMP NULL,
  `expiry_date` TIMESTAMP NULL,
  UNIQUE KEY `reg_id` (`registration_id`),
  FOREIGN KEY (`registration_id`) REFERENCES `adm_registrations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enrollment (nhập học - done by staff)
CREATE TABLE IF NOT EXISTS `adm_enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `student_code` VARCHAR(50),
  `enrolled_by` INT COMMENT 'user_id of staff',
  `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  `documents_received` TINYINT(1) DEFAULT 0,
  `tuition_paid` TINYINT(1) DEFAULT 0,
  `status` ENUM('processing','completed','cancelled') DEFAULT 'processing',
  UNIQUE KEY `reg_id` (`registration_id`),
  FOREIGN KEY (`registration_id`) REFERENCES `adm_registrations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity logs
CREATE TABLE IF NOT EXISTS `adm_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT,
  `user_id` INT,
  `action` VARCHAR(100),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admission news
CREATE TABLE IF NOT EXISTS `adm_news` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `content` TEXT,
  `image_url` VARCHAR(500),
  `status` ENUM('show','hide') DEFAULT 'show',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Default data
-- ============================================================

INSERT IGNORE INTO `adm_methods` (`code`, `method_name`, `description`, `priority`, `status`) VALUES
('thpt',    'Xét điểm thi THPT',    'Dựa vào kết quả kỳ thi tốt nghiệp THPT', 1, 'open'),
('hocba',   'Xét học bạ',           'Xét kết quả học tập THPT', 2, 'open'),
('dgnl',    'Xét điểm ĐGNL',        'Xét điểm thi đánh giá năng lực', 3, 'open'),
('direct',  'Xét tuyển thẳng',      'Theo quy định của Bộ GD&ĐT', 0, 'open');

INSERT IGNORE INTO `adm_subject_combinations` (`code`, `name`, `subjects`) VALUES
('A00', 'Toán, Lý, Hóa',   'Toán, Vật lý, Hóa học'),
('A01', 'Toán, Lý, Anh',   'Toán, Vật lý, Tiếng Anh'),
('D01', 'Toán, Văn, Anh',  'Toán, Ngữ văn, Tiếng Anh'),
('D07', 'Toán, Hóa, Anh',  'Toán, Hóa học, Tiếng Anh'),
('B00', 'Toán, Hóa, Sinh', 'Toán, Hóa học, Sinh học'),
('C00', 'Văn, Sử, Địa',    'Ngữ văn, Lịch sử, Địa lý');

-- Provinces (63 tỉnh thành)
INSERT IGNORE INTO `adm_provinces` (`name`) VALUES
('Hà Nội'),('Hồ Chí Minh'),('Đà Nẵng'),('Hải Phòng'),('Cần Thơ'),
('An Giang'),('Bà Rịa - Vũng Tàu'),('Bắc Giang'),('Bắc Kạn'),('Bạc Liêu'),
('Bắc Ninh'),('Bến Tre'),('Bình Định'),('Bình Dương'),('Bình Phước'),
('Bình Thuận'),('Cà Mau'),('Cao Bằng'),('Đắk Lắk'),('Đắk Nông'),
('Điện Biên'),('Đồng Nai'),('Đồng Tháp'),('Gia Lai'),('Hà Giang'),
('Hà Nam'),('Hà Tĩnh'),('Hải Dương'),('Hậu Giang'),('Hòa Bình'),
('Hưng Yên'),('Khánh Hòa'),('Kiên Giang'),('Kon Tum'),('Lai Châu'),
('Lâm Đồng'),('Lạng Sơn'),('Lào Cai'),('Long An'),('Nam Định'),
('Nghệ An'),('Ninh Bình'),('Ninh Thuận'),('Phú Thọ'),('Phú Yên'),
('Quảng Bình'),('Quảng Nam'),('Quảng Ngãi'),('Quảng Ninh'),('Quảng Trị'),
('Sóc Trăng'),('Sơn La'),('Tây Ninh'),('Thái Bình'),('Thái Nguyên'),
('Thanh Hóa'),('Thừa Thiên Huế'),('Tiền Giang'),('Trà Vinh'),('Tuyên Quang'),
('Vĩnh Long'),('Vĩnh Phúc'),('Yên Bái');
