<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();

$id = intval($_GET['id'] ?? 0);
if (!$id) adm_redirect('registrations.php');

$stmt = $conn->prepare("
    SELECT r.*, m.major_name, m.major_code, am.method_name,
           sc.code as combo_code, sc.name as combo_name,
           p.name as province_name, d.name as district_name,
           ar.status as result_status, ar.total_score as result_score, ar.cutoff_score,
           ac.status as confirm_status, ac.confirmed_at, ac.expiry_date,
           ae.status as enroll_status, ae.student_code, ae.enrolled_at, ae.documents_received, ae.tuition_paid
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    LEFT JOIN adm_methods am ON r.method_code = am.code
    LEFT JOIN adm_subject_combinations sc ON r.combination_id = sc.id
    LEFT JOIN adm_provinces p ON r.province_id = p.id
    LEFT JOIN adm_districts d ON r.district_id = d.id
    LEFT JOIN adm_results ar ON r.id = ar.registration_id
    LEFT JOIN adm_confirmations ac ON r.id = ac.registration_id
    LEFT JOIN adm_enrollments ae ON r.id = ae.registration_id
    WHERE r.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
if (!$reg) adm_redirect('registrations.php');

// Scores
$scoreRow = $conn->query("SELECT * FROM adm_scores WHERE registration_id=$id")->fetch_assoc();

// Logs
$logs = $conn->query("
    SELECT l.*, u.full_name as admin_name
    FROM adm_logs l LEFT JOIN users u ON l.user_id = u.id
    WHERE l.registration_id = $id ORDER BY l.created_at DESC
");

$pageTitle = 'Chi tiết hồ sơ #' . str_pad($id, 6, '0', STR_PAD_LEFT);
include __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_SESSION['adm_success'])): ?>
<div class="alert alert-success auto-dismiss"><?php echo $_SESSION['adm_success']; unset($_SESSION['adm_success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['adm_error'])): ?>
<div class="alert alert-danger auto-dismiss"><?php echo $_SESSION['adm_error']; unset($_SESSION['adm_error']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="registrations.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Quay lại</a>
    <div class="d-flex gap-2">
        <?php if ($reg['status'] === 'pending'): ?>
        <button class="btn btn-sm btn-success" onclick="processReg(<?php echo $id; ?>,'approved')"><i class="fas fa-check me-1"></i>Duyệt hồ sơ</button>
        <button class="btn btn-sm btn-danger" onclick="processReg(<?php echo $id; ?>,'rejected')"><i class="fas fa-times me-1"></i>Từ chối</button>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>In</button>
    </div>
</div>

<div class="row g-3">
    <!-- Left column -->
    <div class="col-lg-8">
        <!-- Personal info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user me-2"></i>Thông tin cá nhân</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php $fields = [
                        ['Họ và tên', $reg['fullname']],
                        ['Ngày sinh', date('d/m/Y', strtotime($reg['birthday']))],
                        ['Giới tính', $reg['gender'] === 'male' ? 'Nam' : ($reg['gender'] === 'female' ? 'Nữ' : 'Khác')],
                        ['CCCD/CMND', $reg['identification']],
                        ['Số điện thoại', $reg['phone']],
                        ['Email', $reg['email']],
                    ];
                    foreach ($fields as [$label, $val]): ?>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1"><?php echo $label; ?></div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($val ?? '—'); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <div class="text-muted small mb-1">Địa chỉ</div>
                        <div class="fw-semibold"><?php
                            $addr = $reg['address'] ?? '';
                            if ($reg['district_name']) $addr .= ', ' . $reg['district_name'];
                            if ($reg['province_name']) $addr .= ', ' . $reg['province_name'];
                            echo htmlspecialchars($addr ?: '—');
                        ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admission info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-graduation-cap me-2"></i>Thông tin tuyển sinh</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php $fields2 = [
                        ['Trường THPT', $reg['school']],
                        ['Năm tốt nghiệp', $reg['graduation_year']],
                        ['Ngành đăng ký', ($reg['major_name'] ?? '—') . ($reg['major_code'] ? ' (' . $reg['major_code'] . ')' : '')],
                        ['Phương thức', $reg['method_name'] ?? '—'],
                        ['Tổ hợp môn', $reg['combo_code'] ? $reg['combo_code'] . ' - ' . $reg['combo_name'] : '—'],
                        ['Ghi chú', $reg['notes'] ?: 'Không có'],
                    ];
                    foreach ($fields2 as [$label, $val]): ?>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1"><?php echo $label; ?></div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($val ?? '—'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Scores -->
        <?php if ($scoreRow): $scores = json_decode($scoreRow['score_data'], true); ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Điểm xét tuyển</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Môn</th><th>Điểm</th></tr></thead>
                        <tbody>
                        <?php foreach ($scores as $k => $v): ?>
                        <tr><td><?php echo ucfirst(str_replace('_',' ',$k)); ?></td><td><?php echo $v; ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="table-primary fw-bold"><td>Tổng điểm</td><td><?php echo $scoreRow['total_score']; ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity log -->
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Lịch sử xử lý</div>
            <div class="card-body">
                <?php if ($logs && $logs->num_rows > 0): while ($log = $logs->fetch_assoc()): ?>
                <div class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 text-muted small" style="width:130px;"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'Hệ thống'); ?></strong>
                        <div class="text-muted small"><?php echo htmlspecialchars($log['description']); ?></div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <p class="text-muted mb-0">Chưa có hoạt động nào.</p>
                <?php endif; ?>
                <div class="d-flex gap-3 mb-0">
                    <div class="flex-shrink-0 text-muted small" style="width:130px;"><?php echo date('d/m/Y H:i', strtotime($reg['created_at'])); ?></div>
                    <div><strong>Hệ thống</strong><div class="text-muted small">Tiếp nhận hồ sơ đăng ký</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4">
        <!-- Status card -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Trạng thái hồ sơ</div>
            <div class="card-body text-center">
                <?php
                $sc = ['pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected'];
                $st = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
                ?>
                <div class="badge-status <?php echo $sc[$reg['status']]; ?> fs-6 mb-3 d-inline-block">
                    <?php echo $st[$reg['status']]; ?>
                </div>
                <div class="text-muted small">Mã hồ sơ: <strong>#<?php echo str_pad($id,6,'0',STR_PAD_LEFT); ?></strong></div>
                <div class="text-muted small">Ngày đăng ký: <strong><?php echo date('d/m/Y H:i', strtotime($reg['created_at'])); ?></strong></div>
            </div>
        </div>

        <!-- Admission result -->
        <?php if ($reg['result_status']): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-trophy me-2"></i>Kết quả xét tuyển</div>
            <div class="card-body">
                <div class="text-center mb-2">
                    <span class="badge-status <?php echo $reg['result_status']==='passed'?'badge-passed':'badge-failed'; ?> fs-6">
                        <?php echo $reg['result_status']==='passed' ? '✓ Trúng tuyển' : '✗ Không trúng tuyển'; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between small mt-2">
                    <span class="text-muted">Điểm của thí sinh</span>
                    <strong><?php echo number_format($reg['result_score'],2); ?></strong>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Điểm chuẩn</span>
                    <strong><?php echo number_format($reg['cutoff_score'],2); ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Confirmation -->
        <?php if ($reg['confirm_status']): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user-check me-2"></i>Xác nhận nhập học</div>
            <div class="card-body">
                <div class="text-center mb-2">
                    <?php $cs = ['confirmed'=>'badge-confirmed','pending'=>'badge-pending','expired'=>'badge-rejected'];
                    $ct = ['confirmed'=>'Đã xác nhận','pending'=>'Chờ xác nhận','expired'=>'Hết hạn']; ?>
                    <span class="badge-status <?php echo $cs[$reg['confirm_status']]; ?>">
                        <?php echo $ct[$reg['confirm_status']]; ?>
                    </span>
                </div>
                <?php if ($reg['confirmed_at']): ?>
                <div class="text-muted small text-center">Lúc: <?php echo date('d/m/Y H:i', strtotime($reg['confirmed_at'])); ?></div>
                <?php endif; ?>
                <?php if ($reg['expiry_date'] && $reg['confirm_status'] === 'pending'): ?>
                <div class="text-muted small text-center">Hạn: <?php echo date('d/m/Y H:i', strtotime($reg['expiry_date'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enrollment -->
        <?php if ($reg['enroll_status']): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-graduation-cap me-2"></i>Nhập học</div>
            <div class="card-body">
                <?php $es = ['processing'=>'badge-pending','completed'=>'badge-enrolled','cancelled'=>'badge-rejected'];
                $et = ['processing'=>'Đang xử lý','completed'=>'Hoàn tất','cancelled'=>'Hủy']; ?>
                <div class="text-center mb-2">
                    <span class="badge-status <?php echo $es[$reg['enroll_status']]; ?>"><?php echo $et[$reg['enroll_status']]; ?></span>
                </div>
                <?php if ($reg['student_code']): ?>
                <div class="text-center"><strong>MSSV: <?php echo htmlspecialchars($reg['student_code']); ?></strong></div>
                <?php endif; ?>
                <div class="mt-2 small">
                    <div class="d-flex justify-content-between">
                        <span>Hồ sơ giấy</span>
                        <span><?php echo $reg['documents_received'] ? '✓' : '✗'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Học phí</span>
                        <span><?php echo $reg['tuition_paid'] ? '✓' : '✗'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick actions -->
        <?php if ($reg['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt me-2"></i>Xử lý nhanh</div>
            <div class="card-body d-grid gap-2">
                <button class="btn btn-success" onclick="processReg(<?php echo $id; ?>,'approved')">
                    <i class="fas fa-check me-2"></i>Duyệt hồ sơ
                </button>
                <button class="btn btn-danger" onclick="processReg(<?php echo $id; ?>,'rejected')">
                    <i class="fas fa-times me-2"></i>Từ chối hồ sơ
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function processReg(id, action) {
    if (!confirm((action==='approved'?'Duyệt':'Từ chối') + ' hồ sơ này?')) return;
    fetch('../api/process_registration.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id, action})
    }).then(r=>r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Lỗi: ' + d.message);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
