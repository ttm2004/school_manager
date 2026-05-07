<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
$pageTitle = 'Dashboard Tuyển sinh';

// Stats
$total    = $conn->query("SELECT COUNT(*) as c FROM adm_registrations")->fetch_assoc()['c'] ?? 0;
$pending  = $conn->query("SELECT COUNT(*) as c FROM adm_registrations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$approved = $conn->query("SELECT COUNT(*) as c FROM adm_registrations WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$rejected = $conn->query("SELECT COUNT(*) as c FROM adm_registrations WHERE status='rejected'")->fetch_assoc()['c'] ?? 0;
$today    = $conn->query("SELECT COUNT(*) as c FROM adm_registrations WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$passed   = $conn->query("SELECT COUNT(*) as c FROM adm_results WHERE status='passed'")->fetch_assoc()['c'] ?? 0;
$confirmed= $conn->query("SELECT COUNT(*) as c FROM adm_confirmations WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;
$enrolled = $conn->query("SELECT COUNT(*) as c FROM adm_enrollments WHERE status='completed'")->fetch_assoc()['c'] ?? 0;

// 7-day chart
$chartLabels = $chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $cnt = $conn->query("SELECT COUNT(*) as c FROM adm_registrations WHERE DATE(created_at)='$d'")->fetch_assoc()['c'] ?? 0;
    $chartLabels[] = date('d/m', strtotime($d));
    $chartData[] = (int)$cnt;
}

// By major
$byMajor = $conn->query("
    SELECT m.major_name, COUNT(r.id) as cnt
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    GROUP BY r.major_id ORDER BY cnt DESC LIMIT 6
");

// Recent
$recent = $conn->query("
    SELECT r.*, m.major_name
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    ORDER BY r.created_at DESC LIMIT 8
");

include __DIR__ . '/../includes/header.php';
?>
<style>
.stat-icon-1 { background: rgba(13,45,107,.1); color: var(--navy); }
.stat-icon-2 { background: rgba(248,150,30,.12); color: #c97a00; }
.stat-icon-3 { background: rgba(16,185,129,.12); color: #059669; }
.stat-icon-4 { background: rgba(239,68,68,.12); color: #dc2626; }
.stat-icon-5 { background: rgba(245,166,35,.15); color: var(--gold-dark); }
.stat-icon-6 { background: rgba(59,130,246,.12); color: #2563eb; }
.stat-icon-7 { background: rgba(139,92,246,.12); color: #7c3aed; }
.stat-icon-8 { background: rgba(20,184,166,.12); color: #0d9488; }
</style>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Tổng hồ sơ',       $total,     'fa-file-alt',    'stat-icon-1'],
        ['Chờ xử lý',        $pending,   'fa-clock',       'stat-icon-2'],
        ['Đã duyệt',         $approved,  'fa-check-circle','stat-icon-3'],
        ['Từ chối',          $rejected,  'fa-times-circle','stat-icon-4'],
        ['Hôm nay',          $today,     'fa-calendar-day','stat-icon-5'],
        ['Trúng tuyển',      $passed,    'fa-trophy',      'stat-icon-6'],
        ['Đã xác nhận',      $confirmed, 'fa-user-check',  'stat-icon-7'],
        ['Đã nhập học',      $enrolled,  'fa-graduation-cap','stat-icon-8'],
    ];
    foreach ($stats as [$label, $val, $icon, $cls]):
    ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon <?php echo $cls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
            <div class="value"><?php echo number_format($val); ?></div>
            <div class="label"><?php echo $label; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Hồ sơ 7 ngày gần nhất</div>
            <div class="card-body"><canvas id="lineChart" height="100"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Theo ngành</div>
            <div class="card-body"><canvas id="pieChart"></canvas></div>
        </div>
    </div>
</div>

<!-- Recent -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Hồ sơ mới nhất</span>
        <a href="registrations.php" class="btn btn-sm btn-gold">Xem tất cả</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr>
                    <th>Mã HS</th><th>Họ tên</th><th>Ngành</th><th>Ngày đăng ký</th><th>Trạng thái</th><th></th>
                </tr></thead>
                <tbody>
                <?php if ($recent && $recent->num_rows > 0):
                    while ($r = $recent->fetch_assoc()):
                    $sc = ['pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected'];
                    $st = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
                ?>
                <tr>
                    <td><code>#<?php echo str_pad($r['id'],6,'0',STR_PAD_LEFT); ?></code></td>
                    <td><strong><?php echo htmlspecialchars($r['fullname']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($r['phone']); ?></small></td>
                    <td><?php echo htmlspecialchars($r['major_name'] ?? '—'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                    <td><span class="badge-status <?php echo $sc[$r['status']]; ?>">
                        <?php echo $st[$r['status']]; ?></span></td>
                    <td><a href="registration_detail.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-navy">
                        <i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có hồ sơ nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Line chart
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
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Pie chart
<?php
$majorLabels = $majorData = [];
if ($byMajor) { while ($r = $byMajor->fetch_assoc()) { $majorLabels[] = $r['major_name'] ?? 'Khác'; $majorData[] = (int)$r['cnt']; } }
?>
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($majorLabels); ?>,
        datasets: [{ data: <?php echo json_encode($majorData); ?>,
            backgroundColor: ['#0d2d6b','#1a4fa0','#f5a623','#d4891a','#10b981','#3b82f6'],
            borderWidth: 0, hoverOffset: 8 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
