<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tuyensinh');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Site configuration
define('SITE_NAME', 'Đại học Khoa học và Công nghệ');
define('SITE_URL', 'http://localhost/tuyen-sinh');
define('ADMIN_EMAIL', 'admin@hus.edu.vn');

// Email configuration (for contact form)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-password');
define('SMTP_FROM', 'noreply@hus.edu.vn');
define('SMTP_FROM_NAME', 'HUS Tuyển sinh');

// Upload configuration
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// Start session
session_start();

// Helper functions
function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(trim(htmlspecialchars($input)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function displayMessage($message, $type = 'success') {
    return "<div class='alert alert-$type'>$message</div>";
}

// Create tables if not exists
function initDatabase($conn) {
    // Provinces table
    $sql = "CREATE TABLE IF NOT EXISTS provinces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Districts table
    $sql = "CREATE TABLE IF NOT EXISTS districts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        province_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20),
        FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Majors table
    $sql = "CREATE TABLE IF NOT EXISTS majors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(50),
        description TEXT,
        duration INT DEFAULT 4,
        tuition DECIMAL(15,0),
        score_2023 DECIMAL(4,2),
        quota INT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Admission methods table
    $sql = "CREATE TABLE IF NOT EXISTS admission_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        priority INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Subject combinations table
    $sql = "CREATE TABLE IF NOT EXISTS subject_combinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        subjects VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Major combinations table
    $sql = "CREATE TABLE IF NOT EXISTS major_combinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        major_id INT NOT NULL,
        combination_id INT NOT NULL,
        FOREIGN KEY (major_id) REFERENCES majors(id) ON DELETE CASCADE,
        FOREIGN KEY (combination_id) REFERENCES subject_combinations(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Update registrations table with new fields
    $sql = "CREATE TABLE IF NOT EXISTS registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(255) NOT NULL,
        birthday DATE NOT NULL,
        gender VARCHAR(10),
        identification VARCHAR(20) NOT NULL UNIQUE,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        province_id INT,
        district_id INT,
        address TEXT,
        graduation_year YEAR NOT NULL,
        school VARCHAR(255),
        major INT,
        method VARCHAR(50),
        combination_id INT,
        notes TEXT,
        transcript_file VARCHAR(500),
        certificate_file VARCHAR(500),
        avatar_file VARCHAR(500),
        ip_address VARCHAR(45),
        user_agent TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX (email),
        INDEX (phone),
        INDEX (identification),
        FOREIGN KEY (province_id) REFERENCES provinces(id),
        FOREIGN KEY (district_id) REFERENCES districts(id),
        FOREIGN KEY (major) REFERENCES majors(id),
        FOREIGN KEY (combination_id) REFERENCES subject_combinations(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Scores table for admission scores
    $sql = "CREATE TABLE IF NOT EXISTS diemtuyensinh (
        id INT AUTO_INCREMENT PRIMARY KEY,
        registration_id INT NOT NULL,
        method VARCHAR(50) NOT NULL,
        score_data JSON,
        total_score DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
        INDEX (registration_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Contacts table
    $sql = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        subject VARCHAR(255),
        message TEXT,
        status ENUM('new', 'read', 'replied') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (email),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Newsletter table
    $sql = "CREATE TABLE IF NOT EXISTS newsletter (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255),
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Admin users table
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        role ENUM('admin', 'moderator') DEFAULT 'moderator',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        error_log("Error creating admin_users table: " . $conn->error);
    } else {
        // Insert default admin if not exists
        $check = $conn->query("SELECT id FROM admin_users WHERE username = 'admin'");
        if ($check->num_rows == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $conn->query("INSERT INTO admin_users (username, password, email, role) VALUES ('admin', '$password', 'admin@hus.edu.vn', 'admin')");
        }
    }

    // Insert default data if tables are empty
    insertDefaultData($conn);
}

// Insert default data
function insertDefaultData($conn) {
    // Insert provinces if empty
    $check = $conn->query("SELECT COUNT(*) as count FROM provinces");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $provinces = [
            'Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ',
            'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu',
            'Bắc Ninh', 'Bến Tre', 'Bình Định', 'Bình Dương', 'Bình Phước',
            'Bình Thuận', 'Cà Mau', 'Cao Bằng', 'Đắk Lắk', 'Đắk Nông',
            'Điện Biên', 'Đồng Nai', 'Đồng Tháp', 'Gia Lai', 'Hà Giang',
            'Hà Nam', 'Hà Tĩnh', 'Hải Dương', 'Hậu Giang', 'Hòa Bình',
            'Hưng Yên', 'Khánh Hòa', 'Kiên Giang', 'Kon Tum', 'Lai Châu',
            'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định',
            'Nghệ An', 'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Phú Yên',
            'Quảng Bình', 'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị',
            'Sóc Trăng', 'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên',
            'Thanh Hóa', 'Thừa Thiên Huế', 'Tiền Giang', 'Trà Vinh', 'Tuyên Quang',
            'Vĩnh Long', 'Vĩnh Phúc', 'Yên Bái'
        ];
        
        foreach ($provinces as $province) {
            $conn->query("INSERT INTO provinces (name) VALUES ('$province')");
        }
    }

    // Insert majors if empty
    $check = $conn->query("SELECT COUNT(*) as count FROM majors");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $majors = [
            ['7480201', 'Công nghệ thông tin', 'tech', 'Đào tạo cử nhân CNTT chất lượng cao', 4, 15000000, 26.5, 350],
            ['7480101', 'Khoa học máy tính', 'tech', 'Chuyên sâu về AI và khoa học dữ liệu', 4, 18000000, 27.0, 200],
            ['7520207', 'Điện tử viễn thông', 'engineer', 'Đào tạo kỹ sư điện tử viễn thông', 4, 14000000, 24.5, 150],
            ['7460112', 'Toán học', 'science', 'Toán ứng dụng và toán tài chính', 4, 12000000, 23.0, 100],
            ['7440122', 'Vật lý học', 'science', 'Vật lý bán dẫn và vật liệu mới', 4, 12000000, 22.0, 80],
            ['7480103', 'Kỹ thuật phần mềm', 'tech', 'Phát triển phần mềm và ứng dụng', 4, 18000000, 26.0, 180],
            ['7340101', 'Quản trị kinh doanh', 'economic', 'Quản trị doanh nghiệp và khởi nghiệp', 4, 15000000, 25.0, 200],
            ['7520114', 'Kỹ thuật cơ điện tử', 'engineer', 'Robot và tự động hóa', 4, 15000000, 24.0, 120]
        ];
        
        foreach ($majors as $major) {
            $conn->query("INSERT INTO majors (code, name, category, description, duration, tuition, score_2023, quota) 
                         VALUES ('$major[0]', '$major[1]', '$major[2]', '$major[3]', $major[4], $major[5], $major[6], $major[7])");
        }
    }

    // Insert admission methods if empty
    $check = $conn->query("SELECT COUNT(*) as count FROM admission_methods");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $methods = [
            ['thpt', 'Xét điểm thi THPT', 'Dựa vào kết quả kỳ thi tốt nghiệp THPT', 1],
            ['hocba', 'Xét học bạ', 'Xét kết quả học tập THPT', 2],
            ['dgnl', 'Xét điểm ĐGNL', 'Xét điểm thi đánh giá năng lực', 3],
            ['direct', 'Xét tuyển thẳng', 'Theo quy định của Bộ GD&ĐT', 0]
        ];
        
        foreach ($methods as $method) {
            $conn->query("INSERT INTO admission_methods (code, name, description, priority) 
                         VALUES ('$method[0]', '$method[1]', '$method[2]', $method[3])");
        }
    }

    // Insert subject combinations if empty
    $check = $conn->query("SELECT COUNT(*) as count FROM subject_combinations");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $combinations = [
            ['A00', 'Toán, Lý, Hóa', 'Toán, Vật lý, Hóa học'],
            ['A01', 'Toán, Lý, Anh', 'Toán, Vật lý, Tiếng Anh'],
            ['D01', 'Toán, Văn, Anh', 'Toán, Ngữ văn, Tiếng Anh'],
            ['D07', 'Toán, Hóa, Anh', 'Toán, Hóa học, Tiếng Anh'],
            ['B00', 'Toán, Hóa, Sinh', 'Toán, Hóa học, Sinh học'],
            ['C00', 'Văn, Sử, Địa', 'Ngữ văn, Lịch sử, Địa lý']
        ];
        
        foreach ($combinations as $combo) {
            $conn->query("INSERT INTO subject_combinations (code, name, subjects) 
                         VALUES ('$combo[0]', '$combo[1]', '$combo[2]')");
        }
    }
}

// Initialize database on first run
initDatabase($conn);

// Create upload directory if not exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
    mkdir(UPLOAD_DIR . 'registrations/', 0777, true);
    mkdir(UPLOAD_DIR . 'news/', 0777, true);
    mkdir(UPLOAD_DIR . 'avatars/', 0777, true);
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
?>