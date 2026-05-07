<?php
// session_start();
require_once '../php/config.php';
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Xử lý cập nhật điểm chuẩn
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_benchmark') {
        $major_id = $_POST['major_id'];
        $year = $_POST['year'];
        $method_code = $_POST['method_code'];
        $combination_id = !empty($_POST['combination_id']) ? $_POST['combination_id'] : null;
        $score = $_POST['score'];
        $quota = $_POST['quota'];
        
        // Kiểm tra đã có chưa
        $check = $conn->prepare("SELECT id FROM cutoff_scores WHERE major_id = ? AND year = ? AND method_code = ? AND (combination_id = ? OR (combination_id IS NULL AND ? IS NULL))");
        $check->bind_param("iisi", $major_id, $year, $method_code, $combination_id, $combination_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE cutoff_scores SET score = ?, quota = ? WHERE major_id = ? AND year = ? AND method_code = ? AND (combination_id = ? OR (combination_id IS NULL AND ? IS NULL))");
            $stmt->bind_param("diiisii", $score, $quota, $major_id, $year, $method_code, $combination_id, $combination_id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO cutoff_scores (major_id, year, method_code, combination_id, score, quota) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisidi", $major_id, $year, $method_code, $combination_id, $score, $quota);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Cập nhật điểm chuẩn thành công!";
        } else {
            $_SESSION['error'] = "Có lỗi xảy ra khi cập nhật điểm chuẩn!";
        }
        header("Location: score-management.php?year=$year");
        exit();
    }
    
    if ($_POST['action'] == 'update_quota') {
        $major_id = $_POST['major_id'];
        $year = $_POST['year'];
        $quota = $_POST['quota'];
        
        // Kiểm tra đã có chưa
        $check = $conn->prepare("SELECT id FROM admission_quota WHERE major_id = ? AND year = ?");
        $check->bind_param("ii", $major_id, $year);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE admission_quota SET quota = ? WHERE major_id = ? AND year = ?");
            $stmt->bind_param("iii", $quota, $major_id, $year);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO admission_quota (major_id, year, quota) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $major_id, $year, $quota);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Cập nhật chỉ tiêu thành công!";
        } else {
            $_SESSION['error'] = "Có lỗi xảy ra khi cập nhật chỉ tiêu!";
        }
        header("Location: score-management.php?year=$year");
        exit();
    }
}

// Lấy danh sách năm có dữ liệu
$years_list = $conn->query("
    SELECT DISTINCT year FROM (
        SELECT YEAR(created_at) as year FROM registrations
        UNION
        SELECT year FROM cutoff_scores
        UNION
        SELECT year FROM admission_quota
    ) as all_years
    ORDER BY year DESC
");

$available_years = [];
while ($y = $years_list->fetch_assoc()) {
    $available_years[] = $y['year'];
}

// Nếu không có năm nào, lấy năm hiện tại và 4 năm trước
if (empty($available_years)) {
    $current_year = date('Y');
    for ($i = 0; $i < 5; $i++) {
        $available_years[] = $current_year - $i;
    }
}

// Năm được chọn (mặc định là năm gần nhất)
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $available_years[0];

// Lấy chỉ tiêu theo năm từ bảng admission_quota
$quotas_by_year = [];
$quota_result = $conn->query("SELECT major_id, quota FROM admission_quota WHERE year = $selected_year");
while ($q = $quota_result->fetch_assoc()) {
    $quotas_by_year[$q['major_id']] = $q['quota'];
}

// Thống kê tổng quan theo ngành cho năm được chọn
$stats_by_major = $conn->query("
    SELECT 
        m.id,
        m.code,
        m.name,
        m.quota as default_quota,
        aq.quota as year_quota,
        COUNT(r.id) as total_applicants,
        COUNT(CASE WHEN d.total_score IS NOT NULL THEN 1 END) as has_score,
        AVG(d.total_score) as avg_score,
        MAX(d.total_score) as max_score,
        MIN(d.total_score) as min_score,
        COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending_count
    FROM majors m
    LEFT JOIN admission_quota aq ON m.id = aq.major_id AND aq.year = $selected_year
    LEFT JOIN registrations r ON m.id = r.major AND YEAR(r.created_at) = $selected_year
    LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
    GROUP BY m.id
    ORDER BY m.code
");

// Lấy điểm chuẩn theo năm được chọn
$benchmarks = $conn->query("
    SELECT cs.*, m.code as major_code, m.name as major_name, am.name as method_name, sc.code as combo_code
    FROM cutoff_scores cs
    LEFT JOIN majors m ON cs.major_id = m.id
    LEFT JOIN admission_methods am ON cs.method_code = am.code
    LEFT JOIN subject_combinations sc ON cs.combination_id = sc.id
    WHERE cs.year = $selected_year
    ORDER BY m.code, cs.method_code
");

// Lấy danh sách phương thức
$methods = $conn->query("SELECT code, name FROM admission_methods WHERE status = 'active' ORDER BY priority");

// Lấy danh sách tổ hợp môn
$combinations = $conn->query("SELECT id, code, name FROM subject_combinations ORDER BY code");

// Phân phối điểm theo ngành cho năm được chọn
$score_distribution = [];
$major_list = [];
$score_ranges = ['0-5', '5-10', '10-15', '15-20', '20-25', '25-30'];

$majors_result = $conn->query("SELECT id, code, name FROM majors ORDER BY code");
while ($major = $majors_result->fetch_assoc()) {
    $major_list[] = $major;
    $distribution = [];
    foreach ($score_ranges as $range) {
        list($min, $max) = explode('-', $range);
        $count = $conn->query("
            SELECT COUNT(*) as total 
            FROM registrations r
            LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
            WHERE r.major = {$major['id']} 
            AND YEAR(r.created_at) = $selected_year
            AND d.total_score BETWEEN $min AND $max
        ")->fetch_assoc()['total'];
        $distribution[] = $count;
    }
    $score_distribution[$major['id']] = $distribution;
}

// Thống kê theo từng năm cho biểu đồ xu hướng
$trend_data = [];
foreach ($available_years as $year) {
    $year_stats = $conn->query("
        SELECT 
            COUNT(DISTINCT r.id) as total_applicants,
            COUNT(DISTINCT CASE WHEN d.total_score IS NOT NULL THEN r.id END) as has_score,
            AVG(d.total_score) as avg_score,
            (SELECT SUM(quota) FROM admission_quota WHERE year = $year) as total_quota
        FROM registrations r
        LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
        WHERE YEAR(r.created_at) = $year
    ")->fetch_assoc();
    
    $trend_data[$year] = $year_stats;
}

$page_title = "Quản lý điểm chuẩn";
require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Quản lý điểm chuẩn</h1>
        </div>
        
        <div class="nav-right">
            <div class="nav-item">
                <button class="nav-link" onclick="window.location.reload()" title="Làm mới">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            
            <div class="nav-item dropdown">
                <button class="nav-link" onclick="toggleDropdown('userDropdown')">
                    <i class="fas fa-user-circle"></i>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <div class="user-info-dropdown">
                        <div class="user-avatar large">
                            <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <h4><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></h4>
                            <p><?php echo $_SESSION['admin_email'] ?? 'admin@example.com'; ?></p>
                        </div>
                    </div>
                    <div class="dropdown-footer">
                        <a href="logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h2>
                    <i class="fas fa-chart-line" style="color: #667eea;"></i>
                    Quản lý điểm chuẩn
                </h2>
                <p>Xem xét số lượng thí sinh và điểm số để xác định điểm chuẩn cho từng ngành</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y'); ?>
            </div>
        </div>

        <!-- Year Filter -->
        <div class="filter-section">
            <form method="GET" class="year-filter-form">
                <div class="filter-group">
                    <label for="year">Chọn năm:</label>
                    <select name="year" id="year" class="year-select" onchange="this.form.submit()">
                        <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                            Năm <?php echo $year; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <a href="quota-management.php?year=<?php echo $selected_year; ?>" class="btn-quota-management">
                        <i class="fas fa-chart-pie"></i> Quản lý chỉ tiêu
                    </a>
                </div>
            </form>
        </div>

        <!-- Trend Chart -->
        <div class="section-title">
            <h3><i class="fas fa-chart-line"></i> Xu hướng tuyển sinh qua các năm</h3>
        </div>

        <div class="trend-section">
            <canvas id="trendChart" style="height: 300px; width: 100%;"></canvas>
        </div>

        <!-- Thống kê tổng quan theo ngành -->
        <div class="section-title">
            <h3><i class="fas fa-university"></i> Thống kê theo ngành - Năm <?php echo $selected_year; ?></h3>
        </div>

        <div class="majors-grid">
            <?php 
            $stats_by_major->data_seek(0);
            while ($major = $stats_by_major->fetch_assoc()): 
                $current_quota = $major['year_quota'] ?? $major['default_quota'] ?? 'Chưa có';
            ?>
            <div class="major-card" onclick="window.location.href='major-detail.php?id=<?php echo $major['id']; ?>&year=<?php echo $selected_year; ?>'">
                <div class="major-header">
                    <span class="major-code"><?php echo $major['code']; ?></span>
                    <h4><?php echo $major['name']; ?></h4>
                </div>
                
                <div class="major-stats">
                    <div class="stat-item">
                        <span class="stat-label">Chỉ tiêu</span>
                        <span class="stat-value"><?php echo $current_quota; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Tổng hồ sơ</span>
                        <span class="stat-value"><?php echo $major['total_applicants']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Đã có điểm</span>
                        <span class="stat-value"><?php echo $major['has_score']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Điểm TB</span>
                        <span class="stat-value"><?php echo number_format($major['avg_score'] ?? 0, 2); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Cao nhất</span>
                        <span class="stat-value"><?php echo number_format($major['max_score'] ?? 0, 2); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Thấp nhất</span>
                        <span class="stat-value"><?php echo number_format($major['min_score'] ?? 0, 2); ?></span>
                    </div>
                </div>
                
                <div class="major-footer">
                    <span class="view-details-link">
                        Xem chi tiết <i class="fas fa-arrow-right"></i>
                    </span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Biểu đồ phân phối điểm theo ngành -->
        <div class="section-title">
            <h3><i class="fas fa-chart-bar"></i> Phân phối điểm theo ngành - Năm <?php echo $selected_year; ?></h3>
        </div>

        <div class="distribution-section">
            <canvas id="distributionChart" style="height: 400px; width: 100%;"></canvas>
        </div>

        <!-- Bảng điểm chuẩn hiện tại -->
        <div class="section-title">
            <h3><i class="fas fa-bullseye"></i> Điểm chuẩn năm <?php echo $selected_year; ?></h3>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã ngành</th>
                            <th>Tên ngành</th>
                            <th>Phương thức</th>
                            <th>Tổ hợp</th>
                            <th>Điểm chuẩn</th>
                            <th>Chỉ tiêu</th>
                            <th>Đã trúng tuyển</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($benchmarks && $benchmarks->num_rows > 0): ?>
                            <?php while ($row = $benchmarks->fetch_assoc()): 
                                // Đếm số thí sinh trúng tuyển theo phương thức và tổ hợp
                                $combo_condition = "1=1";
                                
                                if (!empty($row['combination_id'])) {
                                    $combo_id = (int)$row['combination_id'];
                                    $combo_condition = "r.combination_id = $combo_id";
                                }
                                
                                $sql = "
                                    SELECT COUNT(*) as total 
                                    FROM registrations r
                                    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
                                    WHERE r.major = {$row['major_id']}
                                    AND r.method = '{$row['method_code']}'
                                    AND ($combo_condition)
                                    AND d.total_score >= {$row['score']}
                                    AND YEAR(r.created_at) = $selected_year
                                ";
                                
                                $admitted = $conn->query($sql)->fetch_assoc()['total'];
                            ?>
                            <tr>
                                <td><span class="major-code"><?php echo $row['major_code']; ?></span></td>
                                <td><?php echo $row['major_name']; ?></td>
                                <td><span class="method-badge"><?php echo $row['method_name']; ?></span></td>
                                <td><?php echo $row['combo_code'] ?? 'Tất cả'; ?></td>
                                <td><span class="benchmark-score"><?php echo number_format($row['score'], 2); ?></span></td>
                                <td><?php echo $row['quota'] ?? '---'; ?></td>
                                <td><?php echo $admitted; ?></td>
                                <td>
                                    <button class="btn-edit" onclick="editBenchmark(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox fa-3x"></i>
                                        <p>Chưa có điểm chuẩn nào được thiết lập cho năm <?php echo $selected_year; ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</div>

<!-- Modal thiết lập điểm chuẩn -->
<div class="modal" id="benchmarkModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-cog"></i> <span id="benchmarkModalTitle">Thiết lập điểm chuẩn</span></h3>
            <button class="modal-close" onclick="hideModal('benchmarkModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="benchmarkForm">
            <input type="hidden" name="action" id="benchmarkAction" value="update_benchmark">
            <input type="hidden" name="id" id="benchmarkId">
            <input type="hidden" name="major_id" id="benchmark_major_id">
            <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Ngành:</label>
                    <div class="form-control-static" id="benchmark_major_display"></div>
                </div>
                
                <div class="form-group">
                    <label>Phương thức xét tuyển <span class="required">*</span></label>
                    <select name="method_code" id="benchmark_method" class="form-control" required>
                        <option value="">-- Chọn phương thức --</option>
                        <?php 
                        $methods->data_seek(0);
                        while ($method = $methods->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $method['code']; ?>"><?php echo $method['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tổ hợp môn (để trống nếu áp dụng cho tất cả)</label>
                    <select name="combination_id" id="benchmark_combo" class="form-control">
                        <option value="">-- Tất cả tổ hợp --</option>
                        <?php 
                        $combinations->data_seek(0);
                        while ($combo = $combinations->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $combo['id']; ?>"><?php echo $combo['code']; ?> - <?php echo $combo['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Điểm chuẩn <span class="required">*</span></label>
                    <input type="number" name="score" id="benchmark_score" class="form-control" step="0.01" min="0" max="30" required>
                </div>
                
                <div class="form-group">
                    <label>Chỉ tiêu cho phương thức này</label>
                    <input type="number" name="quota" id="benchmark_quota" class="form-control" min="0">
                    <small class="form-text text-muted">Để trống nếu dùng chỉ tiêu chung của ngành</small>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Gợi ý điểm chuẩn dựa trên dữ liệu hiện tại:</strong>
                        <div id="suggestion"></div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="hideModal('benchmarkModal')">Hủy</button>
                <button type="submit" class="btn-confirm">Lưu điểm chuẩn</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Filter Section */
.filter-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.year-filter-form {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 600;
    color: var(--gray);
    font-size: 14px;
}

.year-select {
    padding: 8px 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    min-width: 150px;
    background: white;
    cursor: pointer;
}

.btn-quota-management {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #4cc9f0, #4895ef);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-quota-management:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(76,201,240,0.3);
    color: white;
}

/* Trend Section */
.trend-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

/* Section Title */
.section-title {
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.section-title h3 {
    font-size: 20px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title h3 i {
    color: #667eea;
}

/* Majors Grid */
.majors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.major-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.major-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: #667eea;
}

.major-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.major-card:hover::before {
    opacity: 1;
}

.major-header {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.major-code {
    display: inline-block;
    padding: 3px 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.major-header h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    line-height: 1.4;
}

.major-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 3px;
}

.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
}

.major-footer {
    text-align: right;
    margin-top: 10px;
}

.view-details-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #667eea;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.major-card:hover .view-details-link {
    transform: translateX(5px);
}

/* Distribution Section */
.distribution-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

/* Form Styles */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.form-group .required {
    color: #f94144;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.form-control-static {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 14px;
    color: var(--dark);
}

.form-text {
    font-size: 11px;
    color: var(--gray);
    margin-top: 3px;
    display: block;
}

/* Info Box */
.info-box {
    display: flex;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-top: 20px;
    border-left: 4px solid #667eea;
}

.info-box i {
    color: #667eea;
    font-size: 20px;
}

.info-box strong {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
}

/* Benchmark Score */
.benchmark-score {
    font-size: 16px;
    font-weight: 700;
    color: #4cc9f0;
    background: rgba(76,201,240,0.1);
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-block;
}

/* Method Badge */
.method-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #f8f9fa;
    border-radius: 20px;
    font-size: 11px;
    color: var(--gray);
}

/* Responsive */
@media (max-width: 768px) {
    .majors-grid {
        grid-template-columns: 1fr;
    }
    
    .major-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .year-filter-form {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .major-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Toggle dropdown
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        document.addEventListener('click', function closeDropdown(e) {
            if (!dropdown.contains(e.target) && !e.target.closest('.nav-link')) {
                dropdown.classList.remove('show');
                document.removeEventListener('click', closeDropdown);
            }
        });
    }
}

// Show modal
function showModal(id) {
    document.getElementById(id).classList.add('show');
}

function hideModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Hiển thị form thiết lập điểm chuẩn
function showBenchmarkForm(majorId, majorCode, majorName) {
    document.getElementById('benchmark_major_id').value = majorId;
    document.getElementById('benchmark_major_display').textContent = `${majorCode} - ${majorName}`;
    document.getElementById('benchmarkAction').value = 'update_benchmark';
    document.getElementById('benchmarkId').value = '';
    document.getElementById('benchmark_method').value = '';
    document.getElementById('benchmark_combo').value = '';
    document.getElementById('benchmark_score').value = '';
    document.getElementById('benchmark_quota').value = '';
    document.getElementById('benchmarkModalTitle').textContent = 'Thêm điểm chuẩn';
    
    // Fetch suggestion
    fetch(`api/get-benchmark-suggestion.php?major_id=${majorId}&year=<?php echo $selected_year; ?>`)
        .then(response => response.json())
        .then(data => {
            let suggestionHtml = '';
            if (data.suggested_score) {
                suggestionHtml = `
                    <p>Điểm chuẩn đề xuất: <strong>${data.suggested_score}</strong></p>
                    <p>Dựa trên ${data.total_applicants} thí sinh, lấy ${data.quota || 100} chỉ tiêu</p>
                    <p class="suggestion-note">(Lấy thí sinh thứ ${Math.min(data.quota || 100, data.total_applicants)} theo điểm từ cao xuống thấp)</p>
                `;
            } else {
                suggestionHtml = '<p>Chưa đủ dữ liệu để đề xuất điểm chuẩn</p>';
            }
            document.getElementById('suggestion').innerHTML = suggestionHtml;
        });
    
    showModal('benchmarkModal');
}

// Sửa điểm chuẩn
function editBenchmark(id) {
    fetch(`api/get-benchmark.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('benchmark_major_id').value = data.major_id;
            document.getElementById('benchmark_major_display').textContent = `${data.major_code} - ${data.major_name}`;
            document.getElementById('benchmarkAction').value = 'update_benchmark';
            document.getElementById('benchmarkId').value = data.id;
            document.getElementById('benchmark_method').value = data.method_code;
            document.getElementById('benchmark_combo').value = data.combination_id || '';
            document.getElementById('benchmark_score').value = data.score;
            document.getElementById('benchmark_quota').value = data.quota || '';
            document.getElementById('benchmarkModalTitle').textContent = 'Sửa điểm chuẩn';
            
            showModal('benchmarkModal');
        });
}

// Khởi tạo biểu đồ
document.addEventListener('DOMContentLoaded', function() {
    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const years = <?php echo json_encode($available_years); ?>;
    const trendData = <?php echo json_encode($trend_data); ?>;
    
    const applicantsData = years.map(year => trendData[year]?.total_applicants || 0);
    const avgScoreData = years.map(year => parseFloat(trendData[year]?.avg_score || 0));
    const quotaData = years.map(year => trendData[year]?.total_quota || 0);
    
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: years.map(year => 'Năm ' + year),
            datasets: [
                {
                    label: 'Số lượng thí sinh',
                    data: applicantsData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    yAxisID: 'y',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Tổng chỉ tiêu',
                    data: quotaData,
                    borderColor: '#f8961e',
                    backgroundColor: 'rgba(248, 150, 30, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#f8961e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    yAxisID: 'y',
                    tension: 0.4,
                    fill: true,
                    borderDash: [5, 5]
                },
                {
                    label: 'Điểm trung bình',
                    data: avgScoreData,
                    borderColor: '#4cc9f0',
                    backgroundColor: 'rgba(76, 201, 240, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#4cc9f0',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    yAxisID: 'y1',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Xu hướng tuyển sinh qua các năm'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Số lượng'
                    },
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Điểm trung bình'
                    },
                    min: 0,
                    max: 30,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Distribution Chart
    const distCtx = document.getElementById('distributionChart').getContext('2d');
    
    const majorLabels = <?php echo json_encode(array_column($major_list, 'name')); ?>;
    const datasets = [];
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43aa8b'];
    
    <?php foreach ($score_ranges as $index => $range): ?>
    datasets.push({
        label: '<?php echo $range; ?> điểm',
        data: [<?php 
            $values = [];
            foreach ($major_list as $major) {
                $values[] = $score_distribution[$major['id']][$index];
            }
            echo implode(',', $values);
        ?>],
        backgroundColor: colors[<?php echo $index; ?>] || 'hsl(<?php echo $index * 40; ?>, 70%, 60%)',
    });
    <?php endforeach; ?>
    
    new Chart(distCtx, {
        type: 'bar',
        data: {
            labels: majorLabels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                },
                title: {
                    display: true,
                    text: 'Phân phối điểm theo ngành - Năm <?php echo $selected_year; ?>',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        display: false
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Số lượng thí sinh'
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('benchmarkModal');
    if (modal && event.target == modal) {
        hideModal('benchmarkModal');
    }
}
</script>