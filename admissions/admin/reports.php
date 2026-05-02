<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Lấy tham số lọc
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Khởi tạo mảng stats với giá trị mặc định
$stats = [];
$stats['overview'] = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

// Thống kê tổng quan - Kiểm tra bảng tồn tại
$table_check = $conn->query("SHOW TABLES LIKE 'registrations'");
if ($table_check->num_rows > 0) {
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM registrations
        WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['overview']['total'] = (int)$row['total'];
        $stats['overview']['pending'] = (int)$row['pending'];
        $stats['overview']['approved'] = (int)$row['approved'];
        $stats['overview']['rejected'] = (int)$row['rejected'];
    }
}

// Thống kê theo ngành
$majors_stats = [];
$result = $conn->query("
    SELECT 
        m.id,
        m.name,
        COUNT(r.id) as total,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM majors m
    LEFT JOIN registrations r ON r.major = m.id 
        AND DATE(r.created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY m.id
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $majors_stats[] = $row;
    }
}

// Thống kê theo phương thức
$method_stats = [];
$result = $conn->query("
    SELECT 
        am.id,
        am.name,
        COUNT(r.id) as total
    FROM admission_methods am
    LEFT JOIN registrations r ON r.method = am.code 
        AND DATE(r.created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY am.id
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $method_stats[] = $row;
    }
}

// Thống kê theo ngày
$daily_stats = [];
$result = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total
    FROM registrations
    WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE(created_at)
    ORDER BY date
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $daily_stats[] = $row;
    }
}

// Hàm an toàn để format số
function safe_number_format($num, $decimals = 0) {
    return $num !== null ? number_format((float)$num, $decimals) : '0';
}

$page_title = "Báo cáo thống kê";
require_once 'includes/header.php';
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
}

/* Stats Card */
.stats-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border: none;
    box-shadow: var(--shadow-md);
    height: 100%;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.stats-card .stats-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3.5rem;
    opacity: 0.15;
}

.stats-card .stats-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.stats-card .stats-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stats-card .stats-percent {
    font-size: 0.8rem;
}

.stats-card.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stats-card.warning {
    background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    color: white;
}

.stats-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.stats-card.danger {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
}

.stats-card.primary .stats-label,
.stats-card.warning .stats-label,
.stats-card.success .stats-label,
.stats-card.danger .stats-label {
    color: rgba(255,255,255,0.8);
}

.stats-card.primary .stats-percent,
.stats-card.warning .stats-percent,
.stats-card.success .stats-percent,
.stats-card.danger .stats-percent {
    color: rgba(255,255,255,0.7);
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    overflow: hidden;
}

.filter-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 15px 20px;
}

.filter-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
}

.filter-card .card-body {
    padding: 20px;
}

.filter-card .form-control,
.filter-card .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 15px;
    transition: all 0.3s;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.filter-card .form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.btn-gradient-primary {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-gradient-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    overflow: hidden;
}

.chart-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 15px 20px;
}

.chart-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-card .card-header h5 i {
    color: #667eea;
}

.chart-card .card-body {
    padding: 20px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    overflow: hidden;
}

.table-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 15px 20px;
}

.table-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-card .card-body {
    padding: 0;
}

.table-custom {
    margin: 0;
}

.table-custom thead th {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 2px solid #e2e8f0;
    padding: 15px 20px;
    font-weight: 600;
    color: #2d3748;
}

.table-custom tbody td {
    padding: 12px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.table-custom tbody tr:hover {
    background: #f8f9fa;
}

.table-custom tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e0;
    margin-bottom: 15px;
}

.empty-state h4 {
    color: #2d3748;
    margin-bottom: 10px;
}

.empty-state p {
    color: #718096;
}

/* Progress Bar */
.progress-custom {
    height: 8px;
    border-radius: 10px;
    background: #e2e8f0;
}

.progress-custom .progress-bar {
    border-radius: 10px;
    background: var(--primary-gradient);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-action {
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-action:hover {
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-card .stats-value {
        font-size: 1.5rem;
    }
    
    .table-custom {
        font-size: 0.85rem;
    }
    
    .table-custom thead th,
    .table-custom tbody td {
        padding: 10px 12px;
    }
}
</style>

<div class="container-fluid px-4">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-chart-line me-2 text-primary"></i>Báo cáo thống kê</h1>
                    <p class="text-muted">Tổng hợp dữ liệu tuyển sinh theo thời gian</p>
                </div>
                <div class="action-buttons">
                    <button class="btn-action btn btn-outline-primary" onclick="exportReport()">
                        <i class="fas fa-file-excel me-2"></i>Xuất Excel
                    </button>
                    <button class="btn-action btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>In báo cáo
                    </button>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <div class="card-header">
                    <h5><i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label"><i class="far fa-calendar-alt me-2"></i>Từ ngày</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label"><i class="far fa-calendar-alt me-2"></i>Đến ngày</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo $to_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn-gradient-primary w-100">
                                <i class="fas fa-chart-simple me-2"></i>Thống kê
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stats-card primary">
                        <div class="stats-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="stats-label">Tổng hồ sơ</div>
                        <div class="stats-value"><?php echo safe_number_format($stats['overview']['total']); ?></div>
                        <div class="stats-percent">Trong kỳ báo cáo</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card warning">
                        <div class="stats-icon"><i class="fas fa-clock"></i></div>
                        <div class="stats-label">Chờ xử lý</div>
                        <div class="stats-value"><?php echo safe_number_format($stats['overview']['pending']); ?></div>
                        <div class="stats-percent">
                            <?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['pending']/$stats['overview']['total']*100, 1) : 0; ?>% tổng số
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card success">
                        <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stats-label">Đã trúng tuyển</div>
                        <div class="stats-value"><?php echo safe_number_format($stats['overview']['approved']); ?></div>
                        <div class="stats-percent">
                            <?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['approved']/$stats['overview']['total']*100, 1) : 0; ?>% tổng số
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card danger">
                        <div class="stats-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="stats-label">Không trúng tuyển</div>
                        <div class="stats-value"><?php echo safe_number_format($stats['overview']['rejected']); ?></div>
                        <div class="stats-percent">
                            <?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['rejected']/$stats['overview']['total']*100, 1) : 0; ?>% tổng số
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i>Biểu đồ hồ sơ theo ngày</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($daily_stats)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <h4>Chưa có dữ liệu</h4>
                                <p>Không có hồ sơ nào trong khoảng thời gian này</p>
                            </div>
                            <?php else: ?>
                            <canvas id="dailyChart" height="300"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie"></i>Phân loại trạng thái</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($stats['overview']['total'] == 0): ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-pie"></i>
                                <h4>Chưa có dữ liệu</h4>
                                <p>Không có hồ sơ nào trong khoảng thời gian này</p>
                            </div>
                            <?php else: ?>
                            <canvas id="statusChart" height="280"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Tables -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="table-card">
                        <div class="card-header">
                            <h5><i class="fas fa-graduation-cap"></i>Thống kê theo ngành</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($majors_stats)): ?>
                            <div class="empty-state">
                                <i class="fas fa-database"></i>
                                <h4>Chưa có dữ liệu</h4>
                                <p>Không có hồ sơ nào trong khoảng thời gian này</p>
                            </div>
                            <?php else: ?>
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Ngành học</th>
                                        <th class="text-center" width="80">Tổng</th>
                                        <th class="text-center" width="80">Trúng tuyển</th>
                                        <th class="text-center" width="100">Tỉ lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($majors_stats as $row): 
                                        $percent = $row['total'] > 0 ? round($row['approved']/$row['total']*100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                            <?php if ($percent >= 80): ?>
                                                <span class="badge bg-success ms-2">Tốt</span>
                                            <?php elseif ($percent >= 50): ?>
                                                <span class="badge bg-warning ms-2">TB</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo safe_number_format($row['total']); ?></td>
                                        <td class="text-center"><?php echo safe_number_format($row['approved']); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold"><?php echo $percent; ?>%</span>
                                                <div class="progress-custom flex-grow-1">
                                                    <div class="progress-bar" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="table-card">
                        <div class="card-header">
                            <h5><i class="fas fa-layer-group"></i>Thống kê theo phương thức</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($method_stats)): ?>
                            <div class="empty-state">
                                <i class="fas fa-database"></i>
                                <h4>Chưa có dữ liệu</h4>
                                <p>Không có hồ sơ nào trong khoảng thời gian này</p>
                            </div>
                            <?php else: ?>
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Phương thức</th>
                                        <th class="text-center" width="100">Số lượng</th>
                                        <th class="text-center" width="120">Tỉ lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($method_stats as $row):
                                        $percent = $stats['overview']['total'] > 0 ? round($row['total']/$stats['overview']['total']*100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="text-center fw-bold"><?php echo safe_number_format($row['total']); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo $percent; ?>%</span>
                                                <div class="progress-custom flex-grow-1">
                                                    <div class="progress-bar" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </div>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($daily_stats)): ?>
// Daily Chart Data
const dailyData = <?php
    $dates = [];
    $counts = [];
    foreach ($daily_stats as $row) {
        $dates[] = date('d/m', strtotime($row['date']));
        $counts[] = $row['total'];
    }
    echo json_encode(['labels' => $dates, 'data' => $counts]);
?>;

// Daily Line Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyData.labels,
        datasets: [{
            label: 'Số hồ sơ',
            data: dailyData.data,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#667eea',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        return `📄 Số hồ sơ: ${context.raw}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0',
                    drawBorder: false
                },
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return value + ' hồ sơ';
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
<?php endif; ?>

<?php if ($stats['overview']['total'] > 0): ?>
// Status Donut Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Chờ xử lý', 'Đã trúng tuyển', 'Không trúng tuyển'],
        datasets: [{
            data: [
                <?php echo $stats['overview']['pending']; ?>,
                <?php echo $stats['overview']['approved']; ?>,
                <?php echo $stats['overview']['rejected']; ?>
            ],
            backgroundColor: ['#f2994a', '#11998e', '#eb3349'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = <?php echo $stats['overview']['total']; ?>;
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Export Report Function
function exportReport() {
    const from = document.querySelector('input[name="from_date"]').value;
    const to = document.querySelector('input[name="to_date"]').value;
    if (from && to) {
        window.location.href = `export-report.php?from_date=${from}&to_date=${to}`;
    } else {
        alert('Vui lòng chọn khoảng thời gian');
    }
}
</script>

