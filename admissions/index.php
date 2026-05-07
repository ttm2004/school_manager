<?php
$pageTitle = 'Dashboard Tuyển sinh';
include __DIR__ . '/includes/header.php';

// Stats
$stats = [
    'total'    => $conn->query("SELECT COUNT(*) as c FROM admission_applications")->fetch_assoc()['c'] ?? 0,
    'new'      => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='new'")->fetch_assoc()['c'] ?? 0,
    'checking' => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='checking'")->fetch_assoc()['c'] ?? 0,
    'approved' => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='approved'")->fetch_assoc()['c'] ?? 0,
    'rejected' => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='rejected'")->fetch_assoc()['c'] ?? 0,
    'enrolled' => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='enrolled'")->fetch_assoc()['c'] ?? 0,
    'today'    => $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0,
    'methods'  => $conn->query("SELECT COUNT(*) as c FROM admission_methods WHERE status='open'")->fetch_assoc()['c'] ?? 0,
];

// 7-day chart
$chartLabels = $chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $cnt = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE DATE(created_at)='$d'")->fetch_assoc()['c'] ?? 0;
    $chartLabels[] = date('d/m', strtotime($d));
    $chartData[]   = (int)$cnt;
}

// By major
$byMajor = $conn->query("
    SELECT m.major_name, COUNT(aa.id) as cnt
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    GROUP BY aa.major_id ORDER BY cnt DESC LIMIT 6
");

// Recent applications
$recent = $conn->query("
    SELECT aa.*, m.major_name, am.method_name
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    ORDER BY aa.created_at DESC LIMIT 8
");
?>

<!-- Stats row -->
<div class="row g-3 mb-4">
<?php
$cards = [
    ['Tổng hồ sơ',    $stats['total'],    'bi-file-earmark-person-fill', 'rgba(13,45,107,.1)',    '#0d2d6b'],
    ['Hồ sơ mới',     $stats['new'],      'bi-inbox-fill',               'rgba(248,150,30,.12)',  '#c97a00'],
    ['Đang xét',      $stats['checking'], 'bi-hourglass-split',          'rgba(59,130,246,.12)',  '#2563eb'],
    ['Đã duyệt',      $stats['approved'], 'bi-check-circle-fill',        'rgba(16,185,129,.12)',  '#059669'],
    ['Từ chối',       $stats['rejected'], 'bi-x-circle-fill',            'rgba(239,68,68,.12)',   '#dc2626'],
    ['Đã nhập học',   $stats['enrolled'], 'bi-person-check-fill',        'rgba(139,92,246,.12)',  '#7c3aed'],
    ['Hôm nay',       $stats['today'],    'bi-calendar-day-fill',        'rgba(245,166,35,.15)',  '#d4891a'],
    ['PT xét tuyển',  $stats['methods'],  'bi-list-check',               'rgba(13,45,107,.08)',   '#1a4fa0'],
];
foreach ($cards as [$label, $val, $icon, $bg, $color]):
?>
<div class="col-6 col-md-3">
    <div class="stat-card">
        <div class="ic" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
        <div class="vl"><?php echo number_format($val); ?></div>
        <div class="lb"><?php echo $label; ?></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up me-2"></i>Hồ sơ 7 ngày gần nhất</div>
            <div class="card-body"><canvas id="lineChart" height="90"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill me-2"></i>Theo ngành</div>
            <div class="card-body d-flex align-items-center"><canvas id="pieChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Quick actions (manager only) -->
<?php if (hasRole('admissions_manager')): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-lightning-charge-fill me-2"></i>Thao tác nhanh</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="auto_review.php" class="btn btn-navy"><i class="bi bi-robot me-2"></i>Chạy xét tuyển tự động</a>
                <a href="results.php" class="btn btn-outline-success"><i class="bi bi-trophy me-2"></i>Xem kết quả xét tuyển</a>
                <a href="reports.php" class="btn btn-outline-primary"><i class="bi bi-bar-chart me-2"></i>Báo cáo thống kê</a>
                <a href="methods.php" class="btn btn-outline-secondary"><i class="bi bi-list-check me-2"></i>Phương thức xét tuyển</a>
                <a href="news.php" class="btn btn-outline-secondary"><i class="bi bi-newspaper me-2"></i>Tin tức tuyển sinh</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent applications -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Hồ sơ mới nhất</span>
        <a href="applications.php" class="btn btn-sm btn-gold">Xem tất cả</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Mã HS</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th><th>Tổng điểm</th><th>Trạng thái</th><th>Ngày nộp</th><th></th></tr></thead>
                <tbody>
                <?php
                $sMap = ['new'=>['Mới','bs-new'],'checking'=>['Đang xét','bs-checking'],'approved'=>['Đã duyệt','bs-approved'],'rejected'=>['Từ chối','bs-rejected'],'enrolled'=>['Nhập học','bs-enrolled']];
                if ($recent && $recent->num_rows > 0):
                    while ($r = $recent->fetch_assoc()):
                    $s = $sMap[$r['status']] ?? [$r['status'],''];
                    $total = ($r['math_score']??0)+($r['literature_score']??0)+($r['english_score']??0);
                ?>
                <tr>
                    <td><code class="small">#<?php echo str_pad($r['id'],5,'0',STR_PAD_LEFT); ?></code></td>
                    <td>
                        <div class="fw-semibold small"><?php echo htmlspecialchars($r['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($r['email']); ?></div>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($r['major_name']??'—'); ?></td>
                    <td class="small text-muted"><?php echo mb_substr($r['method_name']??'—',0,20); ?></td>
                    <td class="fw-bold text-success"><?php echo number_format($total,2); ?></td>
                    <td><span class="bs <?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                    <td class="small text-muted"><?php echo date('d/m/Y',strtotime($r['created_at'])); ?></td>
                    <td><a href="applications.php?view=<?php echo $r['id']; ?>" class="btn btn-sm btn-navy"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Chưa có hồ sơ nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Hồ sơ', data: <?php echo json_encode($chartData); ?>,
            borderColor: '#0d2d6b', backgroundColor: 'rgba(13,45,107,.08)',
            borderWidth: 2.5, pointBackgroundColor: '#f5a623', pointRadius: 5,
            tension: .4, fill: true
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

<?php
$mLabels = $mData = [];
if ($byMajor) while ($r = $byMajor->fetch_assoc()) { $mLabels[] = $r['major_name']??'Khác'; $mData[] = (int)$r['cnt']; }
?>
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($mLabels); ?>,
        datasets: [{ data: <?php echo json_encode($mData); ?>,
            backgroundColor: ['#0d2d6b','#1a4fa0','#f5a623','#d4891a','#10b981','#3b82f6'],
            borderWidth: 0, hoverOffset: 8 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
