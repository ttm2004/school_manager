<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Báo cáo Thống kê';
include __DIR__ . '/includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Overview
$overview = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(status='new') as new_count,
        SUM(status='checking') as checking_count,
        SUM(status='approved') as approved_count,
        SUM(status='rejected') as rejected_count,
        SUM(status='enrolled') as enrolled_count
    FROM admission_applications
    WHERE DATE(created_at) BETWEEN '$from' AND '$to'
")->fetch_assoc();

// By major
$byMajor = $conn->query("
    SELECT m.major_name, m.major_code,
        COUNT(aa.id) as total,
        SUM(aa.status='approved') as approved,
        SUM(aa.status='enrolled') as enrolled,
        AVG(aa.math_score + aa.literature_score + aa.english_score) as avg_score,
        MAX(aa.math_score + aa.literature_score + aa.english_score) as max_score,
        MIN(aa.math_score + aa.literature_score + aa.english_score) as min_score
    FROM majors m
    LEFT JOIN admission_applications aa ON aa.major_id = m.id AND DATE(aa.created_at) BETWEEN '$from' AND '$to'
    GROUP BY m.id ORDER BY total DESC
");

// By method
$byMethod = $conn->query("
    SELECT am.method_name, COUNT(aa.id) as total,
        SUM(aa.status='approved') as approved
    FROM admission_methods am
    LEFT JOIN admission_applications aa ON aa.method_id = am.id AND DATE(aa.created_at) BETWEEN '$from' AND '$to'
    GROUP BY am.id ORDER BY total DESC
");

// Daily trend
$daily = $conn->query("
    SELECT DATE(created_at) as d, COUNT(*) as cnt
    FROM admission_applications
    WHERE DATE(created_at) BETWEEN '$from' AND '$to'
    GROUP BY DATE(created_at) ORDER BY d
");
$dLabels = $dData = [];
while ($r = $daily->fetch_assoc()) { $dLabels[] = date('d/m', strtotime($r['d'])); $dData[] = (int)$r['cnt']; }
?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label class="form-label small fw-semibold mb-1">Từ ngày</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Đến ngày</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-bar-chart me-1"></i>Thống kê</button>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Overview stats -->
<div class="row g-3 mb-4">
<?php
$ov = [
    ['Tổng hồ sơ',    $overview['total']??0,         'bi-file-earmark-person-fill','rgba(13,45,107,.1)','#0d2d6b'],
    ['Hồ sơ mới',     $overview['new_count']??0,      'bi-inbox-fill',              'rgba(248,150,30,.12)','#c97a00'],
    ['Đang xét',      $overview['checking_count']??0, 'bi-hourglass-split',         'rgba(59,130,246,.12)','#2563eb'],
    ['Trúng tuyển',   $overview['approved_count']??0, 'bi-check-circle-fill',       'rgba(16,185,129,.12)','#059669'],
    ['Không trúng',   $overview['rejected_count']??0, 'bi-x-circle-fill',           'rgba(239,68,68,.12)','#dc2626'],
    ['Đã nhập học',   $overview['enrolled_count']??0, 'bi-person-check-fill',       'rgba(139,92,246,.12)','#7c3aed'],
];
foreach ($ov as [$lb,$vl,$ic,$bg,$cl]):
?>
<div class="col-6 col-md-2">
    <div class="stat-card text-center">
        <div class="ic mx-auto" style="background:<?php echo $bg;?>;color:<?php echo $cl;?>"><i class="bi <?php echo $ic;?>"></i></div>
        <div class="vl"><?php echo number_format($vl); ?></div>
        <div class="lb"><?php echo $lb; ?></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up me-2"></i>Hồ sơ theo ngày</div>
            <div class="card-body">
                <?php if (empty($dLabels)): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-bar-chart fs-2 d-block mb-2"></i>Không có dữ liệu trong khoảng thời gian này</div>
                <?php else: ?>
                <canvas id="dailyChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill me-2"></i>Phân loại trạng thái</div>
            <div class="card-body d-flex align-items-center">
                <?php if (($overview['total']??0) == 0): ?>
                <div class="text-center text-muted w-100 py-4"><i class="bi bi-pie-chart fs-2 d-block mb-2"></i>Không có dữ liệu</div>
                <?php else: ?>
                <canvas id="statusChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- By major table -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graduation-cap me-2"></i>Thống kê theo ngành</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Ngành</th><th class="text-center">Tổng</th><th class="text-center">Trúng tuyển</th><th class="text-center">Nhập học</th><th class="text-center">Điểm TB</th></tr></thead>
                        <tbody>
                        <?php if ($byMajor && $byMajor->num_rows > 0): while ($r = $byMajor->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?php echo htmlspecialchars($r['major_name']); ?></div>
                                <div class="text-muted" style="font-size:.7rem"><?php echo htmlspecialchars($r['major_code']); ?></div>
                            </td>
                            <td class="text-center fw-bold"><?php echo $r['total']; ?></td>
                            <td class="text-center text-success fw-bold"><?php echo $r['approved']; ?></td>
                            <td class="text-center text-primary fw-bold"><?php echo $r['enrolled']; ?></td>
                            <td class="text-center"><?php echo $r['avg_score'] ? number_format($r['avg_score'],2) : '—'; ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-check me-2"></i>Thống kê theo phương thức</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Phương thức</th><th class="text-center">Tổng</th><th class="text-center">Trúng tuyển</th><th class="text-center">Tỷ lệ</th></tr></thead>
                        <tbody>
                        <?php if ($byMethod && $byMethod->num_rows > 0): while ($r = $byMethod->fetch_assoc()):
                            $pct = $r['total'] > 0 ? round($r['approved']/$r['total']*100,1) : 0;
                        ?>
                        <tr>
                            <td class="small"><?php echo htmlspecialchars($r['method_name']); ?></td>
                            <td class="text-center fw-bold"><?php echo $r['total']; ?></td>
                            <td class="text-center text-success fw-bold"><?php echo $r['approved']; ?></td>
                            <td class="text-center">
                                <div class="d-flex align-items-center gap-1">
                                    <div class="progress flex-grow-1" style="height:6px">
                                        <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                    <small><?php echo $pct; ?>%</small>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($dLabels)): ?>
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($dLabels); ?>,
        datasets: [{
            label: 'Hồ sơ', data: <?php echo json_encode($dData); ?>,
            backgroundColor: 'rgba(13,45,107,.7)', borderRadius: 6
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
<?php endif; ?>
<?php if (($overview['total']??0) > 0): ?>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Mới','Đang xét','Trúng tuyển','Không trúng','Nhập học'],
        datasets: [{ data: [
            <?php echo $overview['new_count']??0; ?>,
            <?php echo $overview['checking_count']??0; ?>,
            <?php echo $overview['approved_count']??0; ?>,
            <?php echo $overview['rejected_count']??0; ?>,
            <?php echo $overview['enrolled_count']??0; ?>
        ], backgroundColor: ['#f5a623','#3b82f6','#10b981','#ef4444','#8b5cf6'], borderWidth: 0, hoverOffset: 8 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
