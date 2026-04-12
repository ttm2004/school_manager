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

// Thống kê tổng quan
$stats = [];

// Tổng số theo trạng thái
$result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM registrations
    WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
");
$stats['overview'] = $result->fetch_assoc();

// Thống kê theo ngành
$majors_stats = $conn->query("
    SELECT 
        m.name,
        COUNT(r.id) as total,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM majors m
    LEFT JOIN registrations r ON r.major = m.id 
        AND DATE(r.created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY m.id
    ORDER BY total DESC
");

// Thống kê theo phương thức
$method_stats = $conn->query("
    SELECT 
        am.name,
        COUNT(r.id) as total
    FROM admission_methods am
    LEFT JOIN registrations r ON r.method = am.code 
        AND DATE(r.created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY am.id
");

// Thống kê theo ngày
$daily_stats = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total
    FROM registrations
    WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE(created_at)
    ORDER BY date
");


include 'includes/stats.php';
$page_title = "Báo cáo thống kê";
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Báo cáo thống kê</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="exportReport()">
                        <i class="fas fa-file-excel"></i> Xuất Excel
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> In báo cáo
                    </button>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Từ ngày</label>
                            <input type="date" class="form-control" name="from_date" value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Đến ngày</label>
                            <input type="date" class="form-control" name="to_date" value="<?php echo $to_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Lọc dữ liệu
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Overview Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Tổng hồ sơ</h6>
                            <h2 class="mb-0"><?php echo $stats['overview']['total']; ?></h2>
                            <small>Trong kỳ</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Chờ xử lý</h6>
                            <h2 class="mb-0"><?php echo $stats['overview']['pending']; ?></h2>
                            <small><?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['pending']/$stats['overview']['total']*100, 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Đã trúng tuyển</h6>
                            <h2 class="mb-0"><?php echo $stats['overview']['approved']; ?></h2>
                            <small><?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['approved']/$stats['overview']['total']*100, 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">Không trúng tuyển</h6>
                            <h2 class="mb-0"><?php echo $stats['overview']['rejected']; ?></h2>
                            <small><?php echo $stats['overview']['total'] > 0 ? round($stats['overview']['rejected']/$stats['overview']['total']*100, 1) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Biểu đồ hồ sơ theo ngày</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Phân loại trạng thái</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Tables -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Thống kê theo ngành</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ngành học</th>
                                        <th class="text-center">Tổng</th>
                                        <th class="text-center">Trúng tuyển</th>
                                        <th class="text-center">Tỉ lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $majors_stats->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['name']; ?></td>
                                        <td class="text-center"><?php echo $row['total']; ?></td>
                                        <td class="text-center"><?php echo $row['approved']; ?></td>
                                        <td class="text-center">
                                            <?php echo $row['total'] > 0 ? round($row['approved']/$row['total']*100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Thống kê theo phương thức</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Phương thức</th>
                                        <th class="text-center">Số lượng</th>
                                        <th class="text-center">Tỉ lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $method_stats->data_seek(0);
                                    while ($row = $method_stats->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?php echo $row['name']; ?></td>
                                        <td class="text-center"><?php echo $row['total']; ?></td>
                                        <td class="text-center">
                                            <?php echo $stats['overview']['total'] > 0 ? round($row['total']/$stats['overview']['total']*100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
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
// Daily Chart
const dailyData = <?php
    $dates = [];
    $counts = [];
    while ($row = $daily_stats->fetch_assoc()) {
        $dates[] = date('d/m', strtotime($row['date']));
        $counts[] = $row['total'];
    }
    echo json_encode(['labels' => $dates, 'data' => $counts]);
?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyData.labels,
        datasets: [{
            label: 'Số hồ sơ',
            data: dailyData.data,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
            }
        }
    }
});

// Status Chart
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Chờ xử lý', 'Đã trúng tuyển', 'Không trúng tuyển'],
        datasets: [{
            data: [
                <?php echo $stats['overview']['pending']; ?>,
                <?php echo $stats['overview']['approved']; ?>,
                <?php echo $stats['overview']['rejected']; ?>
            ],
            backgroundColor: ['#f8961e', '#4cc9f0', '#f94144']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

function exportReport() {
    const from = document.querySelector('input[name="from_date"]').value;
    const to = document.querySelector('input[name="to_date"]').value;
    window.location.href = `export-report.php?from_date=${from}&to_date=${to}`;
}
</script>

<?php require_once '../../includes/footer.php'; ?>