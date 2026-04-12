<?php
// session_start();
require_once '../php/config.php';
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$major_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Lấy thông tin ngành
$major = $conn->query("SELECT * FROM majors WHERE id = $major_id")->fetch_assoc();
if (!$major) {
    header('Location: score-management.php');
    exit();
}

// Lấy chỉ tiêu theo năm từ bảng admission_quota
$quota_data = $conn->query("SELECT quota FROM admission_quota WHERE major_id = $major_id AND year = $year")->fetch_assoc();
$quota = $quota_data ? $quota_data['quota'] : 0; // Mặc định là 0 nếu chưa có chỉ tiêu

// Lấy danh sách năm có dữ liệu
$years = $conn->query("
    SELECT DISTINCT year FROM (
        SELECT YEAR(created_at) as year FROM registrations WHERE major = $major_id
        UNION
        SELECT year FROM cutoff_scores WHERE major_id = $major_id
        UNION
        SELECT year FROM admission_quota WHERE major_id = $major_id
    ) as all_years
    ORDER BY year DESC
");

// Thống kê theo năm
$stats_by_year = [];
for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
    $stats = $conn->query("
        SELECT 
            COUNT(r.id) as total_applicants,
            COUNT(CASE WHEN d.total_score IS NOT NULL THEN 1 END) as has_score,
            AVG(d.total_score) as avg_score,
            MAX(d.total_score) as max_score,
            MIN(d.total_score) as min_score
        FROM registrations r
        LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
        WHERE r.major = $major_id AND YEAR(r.created_at) = $y
    ")->fetch_assoc();

    // Lấy điểm chuẩn của năm
    $benchmark = $conn->query("
        SELECT cs.*, am.name as method_name, sc.code as combo_code
        FROM cutoff_scores cs
        LEFT JOIN admission_methods am ON cs.method_code = am.code
        LEFT JOIN subject_combinations sc ON cs.combination_id = sc.id
        WHERE cs.major_id = $major_id AND cs.year = $y
        ORDER BY cs.method_code
    ");

    $benchmarks = [];
    while ($b = $benchmark->fetch_assoc()) {
        $benchmarks[] = $b;
    }

    // Lấy chỉ tiêu theo năm từ admission_quota
    $yearly_quota = $conn->query("SELECT quota FROM admission_quota WHERE major_id = $major_id AND year = $y")->fetch_assoc();
    
    $stats_by_year[$y] = [
        'stats' => $stats,
        'benchmarks' => $benchmarks,
        'quota' => $yearly_quota ? $yearly_quota['quota'] : 0
    ];
}

// Lấy danh sách thí sinh theo năm
$applicants = $conn->query("
    SELECT r.*, d.total_score, d.score_data, am.name as method_name, sc.code as combo_code
    FROM registrations r
    LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
    LEFT JOIN admission_methods am ON r.method = am.code
    LEFT JOIN subject_combinations sc ON r.combination_id = sc.id
    WHERE r.major = $major_id AND YEAR(r.created_at) = $year
    ORDER BY d.total_score DESC
");

// Lấy điểm chuẩn hiện tại
$current_benchmarks = $conn->query("
    SELECT cs.*, am.name as method_name, sc.code as combo_code
    FROM cutoff_scores cs
    LEFT JOIN admission_methods am ON cs.method_code = am.code
    LEFT JOIN subject_combinations sc ON cs.combination_id = sc.id
    WHERE cs.major_id = $major_id AND cs.year = $year
    ORDER BY cs.method_code
");

$benchmarks_list = [];
while ($b = $current_benchmarks->fetch_assoc()) {
    $benchmarks_list[] = $b;
}

// Tính điểm chuẩn đề xuất và xét kết quả
$scores = [];
$applicants_list = [];
$admission_results = [
    'passed' => 0,
    'failed' => 0,
    'pending' => 0
];

while ($row = $applicants->fetch_assoc()) {
    if ($row['total_score']) {
        $scores[] = $row['total_score'];

        // Xét kết quả dựa trên điểm chuẩn
        $status_map = [
            'approved' => 'passed',
            'rejected' => 'failed',
            'pending' => 'pending'
        ];

        $row['admission_status'] = $status_map[$row['status']] ?? 'pending';
        $row['admission_note'] = '';

        // Tìm điểm chuẩn phù hợp
        $has_benchmark = false;
        foreach ($benchmarks_list as $benchmark) {
            if ($row['method'] == $benchmark['method_code']) {
                $match_combo = !$benchmark['combination_id'] || $row['combination_id'] == $benchmark['combination_id'];
                if ($match_combo) {
                    $has_benchmark = true;
                    if ($row['total_score'] >= $benchmark['score']) {
                        $row['admission_status'] = 'passed';
                        $row['admission_note'] = "Đạt điểm chuẩn {$benchmark['method_name']}";
                        $admission_results['passed']++;
                    } else {
                        $row['admission_status'] = 'failed';
                        $row['admission_note'] = "Không đạt điểm chuẩn {$benchmark['method_name']}";
                        $admission_results['failed']++;
                    }
                    break;
                }
            }
        }

        if (!$has_benchmark) {
            if ($row['admission_status'] == 'pending') {
                $row['admission_note'] = 'Chưa có điểm chuẩn phù hợp';
                $admission_results['pending']++;
            }
        }
    } else {
        $row['admission_status'] = 'no_score';
        $row['admission_note'] = 'Chưa có điểm';
        $admission_results['pending']++;
    }
    $applicants_list[] = $row;
}

// Sắp xếp danh sách: đậu trước, chờ sau, trượt cuối
usort($applicants_list, function ($a, $b) {
    $order = ['passed' => 1, 'pending' => 2, 'no_score' => 2, 'failed' => 3];
    $a_order = $order[$a['admission_status']] ?? 4;
    $b_order = $order[$b['admission_status']] ?? 4;

    if ($a_order == $b_order) {
        return ($b['total_score'] ?? 0) <=> ($a['total_score'] ?? 0);
    }
    return $a_order <=> $b_order;
});

$total_scores = count($scores);
$suggested_score = null;
$rank = null;

if ($total_scores > 0 && $quota > 0) {
    rsort($scores);
    $rank = min($quota, $total_scores);
    $suggested_score = $scores[$rank - 1] ?? $scores[count($scores) - 1];
}

$page_title = "Chi tiết ngành " . $major['code'];
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
            <h1 class="page-title">Chi tiết ngành</h1>
        </div>

        <div class="nav-right">
            <div class="nav-item">
                <a href="score-management.php?year=<?php echo $year; ?>" class="nav-link" title="Quay lại">
                    <i class="fas fa-arrow-left"></i>
                </a>
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
        <!-- Header -->
        <div class="major-header-section">
            <div class="major-info">
                <span class="major-code-large"><?php echo $major['code']; ?></span>
                <h1><?php echo $major['name']; ?></h1>
                <p class="major-description"><?php echo $major['description'] ?? 'Chưa có mô tả'; ?></p>
            </div>
            <div class="major-quota">
                <div class="quota-box">
                    <span class="quota-label">Chỉ tiêu năm <?php echo $year; ?></span>
                    <div class="quota-value-wrapper">
                        <span class="quota-value" id="mainQuota"><?php echo $quota > 0 ? $quota : 'Chưa có'; ?></span>
                        <?php if($quota > 0): ?>
                        <button class="edit-quota-btn" onclick="showQuotaForm()" title="Chỉnh sửa chỉ tiêu">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="quota-box">
                    <span class="quota-label">Đã xét</span>
                    <span class="quota-value"><?php echo $total_scores; ?></span>
                </div>
                <div class="quota-box">
                    <span class="quota-label">Còn lại</span>
                    <span class="quota-value <?php echo ($quota - $total_scores) < 0 ? 'negative' : ''; ?>">
                        <?php echo $quota > 0 ? ($quota - $total_scores) : '---'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Year Filter & Quick Stats -->
        <div class="filter-stats-row">
            <div class="year-filter-section">
                <form method="GET" class="year-form">
                    <input type="hidden" name="id" value="<?php echo $major_id; ?>">
                    <div class="year-selector">
                        <label>Chọn năm:</label>
                        <select name="year" onchange="this.form.submit()" class="year-select">
                            <?php 
                            $years->data_seek(0);
                            while ($y = $years->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $y['year']; ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                                    Năm <?php echo $y['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="result-stats">
                <div class="result-stat passed">
                    <span class="stat-dot"></span>
                    <span class="stat-label">Đậu</span>
                    <span class="stat-value"><?php echo $admission_results['passed']; ?></span>
                </div>
                <div class="result-stat failed">
                    <span class="stat-dot"></span>
                    <span class="stat-label">Trượt</span>
                    <span class="stat-value"><?php echo $admission_results['failed']; ?></span>
                </div>
                <div class="result-stat pending">
                    <span class="stat-dot"></span>
                    <span class="stat-label">Chờ</span>
                    <span class="stat-value"><?php echo $admission_results['pending']; ?></span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_by_year[$year]['stats']['total_applicants']; ?></h3>
                    <p>Tổng hồ sơ</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_by_year[$year]['stats']['has_score']; ?></h3>
                    <p>Đã có điểm</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats_by_year[$year]['stats']['avg_score'] ?? 0, 2); ?></h3>
                    <p>Điểm TB</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats_by_year[$year]['stats']['max_score'] ?? 0, 2); ?></h3>
                    <p>Cao nhất</p>
                </div>
            </div>
        </div>

        <!-- Benchmark Suggestion - Chỉ hiển thị khi có chỉ tiêu -->
        <?php if($quota > 0): ?>
        <div class="suggestion-section">
            <div class="suggestion-card">
                <div class="suggestion-header">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Đề xuất điểm chuẩn năm <?php echo $year; ?></h3>
                </div>
                <div class="suggestion-body">
                    <div class="suggestion-info">
                        <div class="info-row">
                            <span>Số thí sinh có điểm:</span>
                            <strong><?php echo $total_scores; ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Chỉ tiêu:</span>
                            <strong><?php echo $quota; ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Vị trí lấy:</span>
                            <strong>Thí sinh thứ <?php echo $rank; ?></strong>
                        </div>
                        <div class="info-row highlight">
                            <span>Điểm chuẩn đề xuất:</span>
                            <strong class="suggested-score"><?php echo $suggested_score ? number_format($suggested_score, 2) : 'Chưa đủ dữ liệu'; ?></strong>
                        </div>
                    </div>

                    <div class="suggestion-chart">
                        <canvas id="scoreDistributionChart" style="height: 200px;"></canvas>
                    </div>
                </div>
                <div class="suggestion-footer">
                    <button class="btn-apply-suggestion" onclick="applySuggestedScore(<?php echo $suggested_score; ?>)">
                        <i class="fas fa-check"></i> Áp dụng điểm chuẩn này
                    </button>
                    <button class="btn-customize" onclick="showCustomBenchmarkForm()">
                        <i class="fas fa-cog"></i> Tùy chỉnh
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="warning-section">
            <div class="warning-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Chưa có chỉ tiêu cho năm <?php echo $year; ?></h3>
                <p>Vui lòng thiết lập chỉ tiêu tuyển sinh trước khi xét điểm chuẩn.</p>
                <button class="btn-set-quota" onclick="showQuotaForm()">
                    <i class="fas fa-chart-pie"></i> Thiết lập chỉ tiêu ngay
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Current Benchmarks -->
        <div class="benchmarks-section">
            <div class="section-header">
                <h3><i class="fas fa-bullseye"></i> Điểm chuẩn hiện tại</h3>
                <button class="btn-add-benchmark" onclick="showAddBenchmarkForm()">
                    <i class="fas fa-plus"></i> Thêm điểm chuẩn
                </button>
            </div>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Phương thức</th>
                            <th>Tổ hợp</th>
                            <th>Điểm chuẩn</th>
                            <th>Chỉ tiêu</th>
                            <th>Đã trúng tuyển</th>
                            <th>Còn lại</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($benchmarks_list)): ?>
                            <?php foreach ($benchmarks_list as $benchmark):
                                // Đếm số thí sinh trúng tuyển theo phương thức và tổ hợp
                                $combo_condition = "1=1";

                                if (!empty($benchmark['combination_id'])) {
                                    $combo_id = (int)$benchmark['combination_id'];
                                    $combo_condition = "r.combination_id = $combo_id";
                                }

                                $sql = "
                                    SELECT COUNT(*) as total 
                                    FROM registrations r
                                    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
                                    WHERE r.major = $major_id 
                                    AND r.method = '{$benchmark['method_code']}'
                                    AND ($combo_condition)
                                    AND d.total_score >= {$benchmark['score']}
                                    AND YEAR(r.created_at) = $year
                                ";

                                $result = $conn->query($sql);
                                $admitted = $result ? $result->fetch_assoc()['total'] : 0;

                                // Xử lý quota
                                $benchmark_quota = $benchmark['quota'] ?? 0;
                                if (!$benchmark_quota) {
                                    $quota_display = '---';
                                    $remaining = 0;
                                } else {
                                    $quota_display = $benchmark_quota;
                                    $remaining = $benchmark_quota - $admitted;
                                }
                            ?>
                                <tr>
                                    <td><?php echo $benchmark['method_name']; ?></td>
                                    <td><?php echo $benchmark['combo_code'] ?? 'Tất cả'; ?></td>
                                    <td>
                                        <span class="benchmark-score">
                                            <?php echo number_format($benchmark['score'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $quota_display; ?></td>
                                    <td><?php echo $admitted; ?></td>
                                    <td class="<?php echo $remaining < 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php
                                        if (!$benchmark_quota) {
                                            echo '---';
                                        } else {
                                            echo $remaining >= 0 ? $remaining : 'Vượt ' . abs($remaining);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn-icon edit" onclick="editBenchmark(<?php echo $benchmark['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete" onclick="deleteBenchmark(<?php echo $benchmark['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox fa-3x"></i>
                                        <p>Chưa có điểm chuẩn nào cho năm <?php echo $year; ?></p>
                                        <button class="btn-add-benchmark" onclick="showAddBenchmarkForm()">
                                            <i class="fas fa-plus"></i> Thêm điểm chuẩn
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Admission Results -->
        <div class="results-section">
            <div class="section-header">
                <h3><i class="fas fa-graduation-cap"></i> Kết quả xét tuyển</h3>
                <div class="result-actions">
                    <button class="btn-publish" onclick="publishResults()">
                        <i class="fas fa-globe"></i> Công bố kết quả
                    </button>
                    <button class="btn-export" onclick="exportResults()">
                        <i class="fas fa-download"></i> Xuất Excel
                    </button>
                </div>
            </div>

            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterResults('all')">Tất cả</button>
                <button class="filter-tab" onclick="filterResults('passed')">Đậu</button>
                <button class="filter-tab" onclick="filterResults('failed')">Trượt</button>
                <button class="filter-tab" onclick="filterResults('pending')">Chờ xét</button>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table" id="resultsTable">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Mã HS</th>
                                <th>Họ tên</th>
                                <th>Phương thức</th>
                                <th>Tổ hợp</th>
                                <th>Tổng điểm</th>
                                <th>Kết quả</th>
                                <th>Ghi chú</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stt = 1;
                            foreach ($applicants_list as $applicant):
                                $status_class = $applicant['admission_status'];
                                $status_text = [
                                    'passed' => 'Đậu',
                                    'failed' => 'Trượt',
                                    'pending' => 'Chờ xét',
                                    'no_score' => 'Chưa có điểm'
                                ];
                                $status_icon = [
                                    'passed' => 'check-circle',
                                    'failed' => 'times-circle',
                                    'pending' => 'clock',
                                    'no_score' => 'minus-circle'
                                ];
                            ?>
                                <tr class="status-<?php echo $status_class; ?>" data-status="<?php echo $status_class; ?>" data-id="<?php echo $applicant['id']; ?>">
                                    <td><?php echo $stt++; ?></td>
                                    <td><span class="registration-id">#<?php echo str_pad($applicant['id'], 6, '0', STR_PAD_LEFT); ?></span></td>
                                    <td>
                                        <div class="applicant-name">
                                            <strong><?php echo $applicant['fullname']; ?></strong>
                                            <small><?php echo $applicant['phone']; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo $applicant['method_name'] ?? $applicant['method']; ?></td>
                                    <td><?php echo $applicant['combo_code'] ?? '---'; ?></td>
                                    <td class="score-cell <?php echo $status_class; ?>">
                                        <?php echo $applicant['total_score'] ? number_format($applicant['total_score'], 2) : '---'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas fa-<?php echo $status_icon[$status_class]; ?>"></i>
                                            <?php echo $status_text[$status_class]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="admission-note"><?php echo $applicant['admission_note']; ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="registration-detail.php?id=<?php echo $applicant['id']; ?>" class="btn-icon view" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($status_class == 'pending' && $applicant['total_score']): ?>
                                                <button class="btn-icon check" onclick="quickCheck(<?php echo $applicant['id']; ?>)" title="Kiểm tra lại">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</div>

<!-- Modal thêm/sửa điểm chuẩn -->
<div class="modal" id="benchmarkModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-cog"></i> <span id="benchmarkModalTitle">Thiết lập điểm chuẩn</span></h3>
            <button class="modal-close" onclick="hideModal('benchmarkModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="benchmarkForm">
            <input type="hidden" name="action" id="benchmarkAction" value="add">
            <input type="hidden" name="id" id="benchmarkId">
            <input type="hidden" name="major_id" value="<?php echo $major_id; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">

            <div class="modal-body">
                <div class="form-group">
                    <label>Phương thức xét tuyển <span class="required">*</span></label>
                    <select name="method_code" id="benchmarkMethod" class="form-control" required>
                        <option value="">-- Chọn phương thức --</option>
                        <?php
                        $methods = $conn->query("SELECT code, name FROM admission_methods WHERE status = 'active'");
                        while ($method = $methods->fetch_assoc()):
                        ?>
                            <option value="<?php echo $method['code']; ?>"><?php echo $method['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tổ hợp môn</label>
                    <select name="combination_id" id="benchmarkCombo" class="form-control">
                        <option value="">-- Tất cả tổ hợp --</option>
                        <?php
                        $combos = $conn->query("SELECT id, code, name FROM subject_combinations ORDER BY code");
                        while ($combo = $combos->fetch_assoc()):
                        ?>
                            <option value="<?php echo $combo['id']; ?>"><?php echo $combo['code']; ?> - <?php echo $combo['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Điểm chuẩn <span class="required">*</span></label>
                    <input type="number" name="score" id="benchmarkScore" class="form-control" step="0.01" min="0" max="30" required>
                </div>

                <div class="form-group">
                    <label>Chỉ tiêu</label>
                    <input type="number" name="quota" id="benchmarkQuota" class="form-control" min="0">
                    <small class="form-text text-muted">Để trống nếu dùng chỉ tiêu chung của ngành</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="hideModal('benchmarkModal')">Hủy</button>
                <button type="submit" class="btn-confirm">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal chỉnh sửa chỉ tiêu -->
<div class="modal" id="quotaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-chart-pie"></i> Chỉnh sửa chỉ tiêu</h3>
            <button class="modal-close" onclick="hideModal('quotaModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="quotaForm">
            <input type="hidden" name="major_id" id="quota_major_id" value="<?php echo $major_id; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">

            <div class="modal-body">
                <div class="form-group">
                    <label>Ngành:</label>
                    <div class="form-control-static" id="quota_major_display"><?php echo $major['code']; ?> - <?php echo $major['name']; ?></div>
                </div>

                <div class="form-group">
                    <label>Chỉ tiêu năm <?php echo $year; ?> <span class="required">*</span></label>
                    <input type="number" name="quota" id="quota_value" class="form-control" min="0" value="<?php echo $quota; ?>" required>
                    <small class="form-text text-muted">Nhập số lượng chỉ tiêu tuyển sinh cho năm nay</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="hideModal('quotaModal')">Hủy</button>
                <button type="submit" class="btn-confirm">Lưu chỉ tiêu</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Filter Stats Row */
    .filter-stats-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .result-stats {
        display: flex;
        gap: 20px;
        background: white;
        padding: 10px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .result-stat {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stat-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .result-stat.passed .stat-dot {
        background: #4cc9f0;
    }

    .result-stat.failed .stat-dot {
        background: #f94144;
    }

    .result-stat.pending .stat-dot {
        background: #f8961e;
    }

    .stat-value {
        font-weight: 600;
        color: var(--dark);
    }

    /* Filter Tabs */
    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 8px 20px;
        border: 1px solid #e9ecef;
        background: white;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .filter-tab:hover {
        background: #f8f9fa;
        border-color: #667eea;
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-color: transparent;
    }

    /* Results Section */
    .results-section {
        margin-top: 30px;
    }

    .result-actions {
        display: flex;
        gap: 10px;
    }

    .btn-publish,
    .btn-export {
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .btn-publish {
        background: linear-gradient(135deg, #4cc9f0, #4895ef);
        color: white;
    }

    .btn-export {
        background: linear-gradient(135deg, #f8961e, #f3722c);
        color: white;
    }

    .btn-publish:hover,
    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }

    .status-badge.passed {
        background: rgba(76, 201, 240, 0.1);
        color: #4cc9f0;
    }

    .status-badge.failed {
        background: rgba(249, 65, 68, 0.1);
        color: #f94144;
    }

    .status-badge.pending {
        background: rgba(248, 150, 30, 0.1);
        color: #f8961e;
    }

    .status-badge.no_score {
        background: #f8f9fa;
        color: var(--gray);
    }

    /* Row Status */
    tr.status-passed {
        background: rgba(76, 201, 240, 0.02);
    }

    tr.status-failed {
        background: rgba(249, 65, 68, 0.02);
    }

    tr.status-pending {
        background: rgba(248, 150, 30, 0.02);
    }

    /* Admission Note */
    .admission-note {
        color: var(--gray);
        font-size: 11px;
        max-width: 150px;
        display: block;
    }

    /* Major Header */
    .major-header-section {
        background: linear-gradient(135deg, #667eea20, #764ba220);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .major-code-large {
        display: inline-block;
        padding: 5px 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .major-info h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin: 0 0 10px;
    }

    .major-description {
        color: var(--gray);
        margin: 0;
    }

    .major-quota {
        display: flex;
        gap: 20px;
    }

    .quota-box {
        text-align: center;
        padding: 15px 25px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        min-width: 150px;
    }

    .quota-label {
        display: block;
        font-size: 12px;
        color: var(--gray);
        margin-bottom: 5px;
    }

    .quota-value-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .quota-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--dark);
    }

    .quota-value.negative {
        color: #f94144;
    }

    .edit-quota-btn {
        background: none;
        border: none;
        color: #667eea;
        cursor: pointer;
        font-size: 16px;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .edit-quota-btn:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: scale(1.1);
    }

    /* Warning Section */
    .warning-section {
        margin-bottom: 30px;
    }

    .warning-card {
        background: linear-gradient(135deg, #fff3cd, #ffe69c);
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        border: 1px solid #ffc107;
    }

    .warning-card i {
        font-size: 48px;
        color: #f8961e;
        margin-bottom: 15px;
    }

    .warning-card h3 {
        font-size: 20px;
        color: #856404;
        margin-bottom: 10px;
    }

    .warning-card p {
        color: #856404;
        margin-bottom: 20px;
    }

    .btn-set-quota {
        padding: 10px 25px;
        background: linear-gradient(135deg, #f8961e, #f3722c);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-set-quota:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(248, 150, 30, 0.3);
    }

    /* Year Filter */
    .year-filter-section {
        background: white;
        border-radius: 16px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        flex: 1;
    }

    .year-selector {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .year-selector label {
        font-weight: 600;
        color: var(--gray);
    }

    .year-select {
        padding: 8px 15px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        min-width: 150px;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .stat-icon.blue {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
    }

    .stat-icon.green {
        background: rgba(76, 201, 240, 0.1);
        color: #4cc9f0;
    }

    .stat-icon.orange {
        background: rgba(248, 150, 30, 0.1);
        color: #f8961e;
    }

    .stat-icon.purple {
        background: rgba(157, 78, 221, 0.1);
        color: #9d4edd;
    }

    .stat-info h3 {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        color: var(--dark);
    }

    .stat-info p {
        margin: 5px 0 0;
        font-size: 13px;
        color: var(--gray);
    }

    /* Suggestion Section */
    .suggestion-section {
        margin-bottom: 30px;
    }

    .suggestion-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
    }

    .suggestion-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .suggestion-header i {
        font-size: 24px;
    }

    .suggestion-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .suggestion-body {
        padding: 25px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    .suggestion-info {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-row.highlight {
        background: #f8f9fa;
        border-radius: 10px;
        border: none;
        font-size: 16px;
    }

    .suggested-score {
        font-size: 24px;
        color: #667eea;
    }

    .suggestion-footer {
        padding: 20px;
        background: #f8f9fa;
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }

    .btn-apply-suggestion,
    .btn-customize {
        padding: 10px 25px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-apply-suggestion {
        background: linear-gradient(135deg, #4cc9f0, #4895ef);
        color: white;
    }

    .btn-customize {
        background: white;
        color: var(--dark);
        border: 1px solid #e9ecef;
    }

    .btn-apply-suggestion:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(76, 201, 240, 0.3);
    }

    /* Section Header */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-header h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        color: var(--dark);
    }

    .btn-add-benchmark {
        padding: 8px 16px;
        background: linear-gradient(135deg, #4cc9f0, #4895ef);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .btn-add-benchmark:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(76, 201, 240, 0.3);
    }

    /* Registration ID */
    .registration-id {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        font-weight: 600;
        color: #667eea;
        background: rgba(102, 126, 234, 0.1);
        padding: 3px 8px;
        border-radius: 6px;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .major-header-section {
            flex-direction: column;
            text-align: center;
        }

        .major-quota {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }

        .quota-box {
            min-width: 120px;
        }

        .filter-stats-row {
            flex-direction: column;
        }

        .year-filter-section {
            width: 100%;
        }

        .suggestion-body {
            grid-template-columns: 1fr;
        }

        .result-actions {
            width: 100%;
            justify-content: stretch;
        }

        .btn-publish,
        .btn-export {
            flex: 1;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .result-stats {
            width: 100%;
            justify-content: space-around;
        }

        .filter-tabs {
            justify-content: center;
        }

        .quota-value-wrapper {
            flex-direction: column;
        }
    }

    /* Loading Overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 99999;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 100000;
        animation: slideInRight 0.3s ease;
        border-left: 4px solid;
    }

    .toast-notification.success {
        border-left-color: #4cc9f0;
    }

    .toast-notification.error {
        border-left-color: #f94144;
    }

    .toast-notification i {
        font-size: 20px;
    }

    .toast-notification.success i {
        color: #4cc9f0;
    }

    .toast-notification.error i {
        color: #f94144;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ==================== HÀM TIỆN ÍCH ====================

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

    // Show/hide modal
    function showModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function hideModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // Filter results
    function filterResults(status) {
        const tabs = document.querySelectorAll('.filter-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');

        const rows = document.querySelectorAll('#resultsTable tbody tr');
        rows.forEach(row => {
            if (status === 'all') {
                row.style.display = '';
            } else {
                row.style.display = row.dataset.status === status ? '' : 'none';
            }
        });
    }

    // Hiển thị loading
    function showLoading() {
        const loader = document.createElement('div');
        loader.className = 'loading-overlay';
        loader.id = 'loadingOverlay';
        loader.innerHTML = '<div class="loading-spinner"></div><p>Đang xử lý...</p>';
        document.body.appendChild(loader);
    }

    function hideLoading() {
        const loader = document.getElementById('loadingOverlay');
        if (loader) loader.remove();
    }

    // Hiển thị thông báo
    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ==================== HÀM XỬ LÝ ĐIỂM CHUẨN ====================

    // Áp dụng điểm chuẩn đề xuất
    function applySuggestedScore(score) {
        console.log('applySuggestedScore called with score:', score);

        if (!score) {
            showToast('error', 'Chưa có điểm chuẩn đề xuất');
            return;
        }

        // Hiển thị loading
        showLoading();

        // Dữ liệu gửi lên server
        const data = {
            major_id: <?php echo $major_id; ?>,
            year: <?php echo $year; ?>,
            suggested_score: score
        };

        // Gửi request áp dụng điểm chuẩn
        fetch('api/apply-benchmark.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                hideLoading();

                if (result.success) {
                    showToast('success', result.message);

                    // Cập nhật UI ngay lập tức
                    updateResultsUI(result.data);

                    // Reload trang sau 2 giây để hiển thị dữ liệu mới nhất
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showToast('error', result.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra khi áp dụng điểm chuẩn');
            });
    }

    // Hàm cập nhật UI kết quả
    function updateResultsUI(data) {
        // Cập nhật số lượng đậu/rớt
        const resultStats = document.querySelector('.result-stats');
        if (resultStats && data.stats) {
            const passedStat = resultStats.querySelector('.result-stat.passed .stat-value');
            const failedStat = resultStats.querySelector('.result-stat.failed .stat-value');
            const pendingStat = resultStats.querySelector('.result-stat.pending .stat-value');

            if (passedStat) passedStat.textContent = data.stats.passed;
            if (failedStat) failedStat.textContent = data.stats.failed;
            if (pendingStat) pendingStat.textContent = data.stats.pending;
        }

        // Cập nhật từng dòng trong bảng kết quả
        if (data.results) {
            data.results.forEach(result => {
                const row = document.querySelector(`tr[data-id="${result.id}"]`);
                if (row) {
                    // Cập nhật trạng thái
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.className = `status-badge ${result.status}`;
                        statusCell.innerHTML = `
                            <i class="fas fa-${result.status === 'passed' ? 'check-circle' : 'times-circle'}"></i>
                            ${result.status === 'passed' ? 'Đậu' : 'Trượt'}
                        `;
                    }

                    // Cập nhật ghi chú
                    const noteCell = row.querySelector('.admission-note');
                    if (noteCell) {
                        noteCell.textContent = result.note;
                    }

                    // Cập nhật class cho dòng
                    row.className = `status-${result.status}`;
                    row.dataset.status = result.status;
                }
            });
        }
    }

    // Hiển thị form tùy chỉnh
    function showCustomBenchmarkForm() {
        console.log('showCustomBenchmarkForm called');
        document.getElementById('benchmarkAction').value = 'add';
        document.getElementById('benchmarkId').value = '';
        document.getElementById('benchmarkScore').value = '';
        document.getElementById('benchmarkMethod').value = '';
        document.getElementById('benchmarkCombo').value = '';
        document.getElementById('benchmarkQuota').value = '';
        document.getElementById('benchmarkModalTitle').textContent = 'Thêm điểm chuẩn';
        showModal('benchmarkModal');
    }

    // Hiển thị form thêm điểm chuẩn
    function showAddBenchmarkForm() {
        console.log('showAddBenchmarkForm called');
        showCustomBenchmarkForm();
    }

    // Sửa điểm chuẩn
    function editBenchmark(id) {
        console.log('editBenchmark called with id:', id);
        showLoading();
        fetch(`api/get-benchmark.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success === false) {
                    showToast('error', data.message);
                    return;
                }
                document.getElementById('benchmarkAction').value = 'edit';
                document.getElementById('benchmarkId').value = data.id;
                document.getElementById('benchmarkMethod').value = data.method_code;
                document.getElementById('benchmarkCombo').value = data.combination_id || '';
                document.getElementById('benchmarkScore').value = data.score;
                document.getElementById('benchmarkQuota').value = data.quota || '';
                document.getElementById('benchmarkModalTitle').textContent = 'Sửa điểm chuẩn';
                showModal('benchmarkModal');
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra');
            });
    }

    // Xóa điểm chuẩn
    function deleteBenchmark(id) {
        console.log('deleteBenchmark called with id:', id);
        if (confirm('Bạn có chắc chắn muốn xóa điểm chuẩn này?')) {
            showLoading();
            fetch('api/delete-benchmark.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast('success', data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showToast('error', 'Có lỗi xảy ra');
                });
        }
    }

    // ==================== HÀM XỬ LÝ CHỈ TIÊU ====================

    // Hiển thị form chỉnh sửa chỉ tiêu
    function showQuotaForm() {
        showModal('quotaModal');
    }

    // Xử lý submit form chỉ tiêu
    document.addEventListener('DOMContentLoaded', function() {
        const quotaForm = document.getElementById('quotaForm');
        if (quotaForm) {
            quotaForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = {
                    major_id: document.getElementById('quota_major_id').value,
                    year: <?php echo $year; ?>,
                    quota: document.getElementById('quota_value').value
                };

                console.log('Updating quota:', formData);
                showLoading();

                fetch('api/update-quota.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showToast('success', data.message);
                            hideModal('quotaModal');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('error', data.message);
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        showToast('error', 'Có lỗi xảy ra');
                    });
            });
        }
    });

    // ==================== HÀM XỬ LÝ KẾT QUẢ ====================

    // Quick check thí sinh
    function quickCheck(id) {
        console.log('quickCheck called with id:', id);
        showLoading();
        fetch('api/quick-check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const result = data.data;
                    alert(
                        'Thí sinh: ' + result.fullname + '\n' +
                        'Điểm: ' + result.total_score + '\n' +
                        'Điểm chuẩn: ' + result.benchmark_score + '\n' +
                        'Phương thức: ' + result.method + '\n' +
                        'Kết quả: ' + result.result + '\n' +
                        'Ghi chú: ' + result.note
                    );
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'Có lỗi xảy ra');
            });
    }

    // Publish results
    function publishResults() {
        console.log('publishResults called');
        if (confirm('Bạn có chắc chắn muốn công bố kết quả xét tuyển? Thí sinh sẽ có thể tra cứu kết quả.')) {
            showLoading();

            fetch('api/publish-results.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        major_id: <?php echo $major_id; ?>,
                        year: <?php echo $year; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast('success', data.message);
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showToast('error', 'Có lỗi xảy ra');
                });
        }
    }

    // Export results
    function exportResults() {
        console.log('exportResults called');
        window.location.href = `export-results.php?major_id=<?php echo $major_id; ?>&year=<?php echo $year; ?>`;
    }

    // ==================== KHỞI TẠO BIỂU ĐỒ ====================

    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing chart');

        const scores = <?php echo json_encode($scores); ?>;
        const ranges = [0, 5, 10, 15, 20, 25, 30];
        const distribution = new Array(ranges.length - 1).fill(0);

        if (scores && scores.length > 0) {
            scores.forEach(score => {
                for (let i = 0; i < ranges.length - 1; i++) {
                    if (score >= ranges[i] && score < ranges[i + 1]) {
                        distribution[i]++;
                        break;
                    }
                }
            });
        }

        const chartCanvas = document.getElementById('scoreDistributionChart');
        if (chartCanvas) {
            const ctx = chartCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['0-5', '5-10', '10-15', '15-20', '20-25', '25-30'],
                    datasets: [{
                        label: 'Số lượng thí sinh',
                        data: distribution,
                        backgroundColor: '#667eea',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        } else {
            console.error('Chart canvas not found');
        }

        // Xử lý submit form benchmark
        const benchmarkForm = document.getElementById('benchmarkForm');
        if (benchmarkForm) {
            benchmarkForm.addEventListener('submit', function(e) {
                e.preventDefault();

                console.log('Benchmark form submitted');
                const formData = new FormData(this);
                showLoading();

                fetch('api/update-benchmark.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showToast('success', data.message);
                            hideModal('benchmarkModal');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('error', data.message);
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        showToast('error', 'Có lỗi xảy ra');
                    });
            });
        }
    });

    // Đóng modal khi click outside
    window.onclick = function(event) {
        const benchmarkModal = document.getElementById('benchmarkModal');
        if (benchmarkModal && event.target == benchmarkModal) {
            hideModal('benchmarkModal');
        }

        const quotaModal = document.getElementById('quotaModal');
        if (quotaModal && event.target == quotaModal) {
            hideModal('quotaModal');
        }
    }

    // Debug: Kiểm tra các hàm đã được định nghĩa
    console.log('Functions defined:', {
        applySuggestedScore: typeof applySuggestedScore,
        showCustomBenchmarkForm: typeof showCustomBenchmarkForm,
        showAddBenchmarkForm: typeof showAddBenchmarkForm,
        showQuotaForm: typeof showQuotaForm,
        publishResults: typeof publishResults,
        exportResults: typeof exportResults
    });
</script>